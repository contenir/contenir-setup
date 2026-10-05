<?php

declare(strict_types=1);

namespace Contenir\Setup\Handler;

use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\InstallerService;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Exception\RuntimeException as DbRuntimeException;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function is_array;
use function is_string;
use function mb_strlen;
use function sprintf;
use function trim;
use function urlencode;

/**
 * Web-based installation wizard: diagnostics, database configuration,
 * connection test and administrator account.
 *
 * Each POST step redirects (post/redirect/get) to `/setup?step=...` with a
 * `success` or `error` message, which the following GET renders. The final
 * step renders the completion page itself.
 *
 * Once InstallerService::isInstalled() is true, every request (GET or POST,
 * whatever its parameters) gets an empty 404 and nothing is run or written.
 *
 * @api
 *
 * @mago-expect lint:cyclomatic-complexity One handler drives every wizard step; splitting it per step is a suggested follow-up.
 */
class InstallHandler implements RequestHandlerInterface
{
    public const int MINIMUM_PASSWORD_LENGTH = 8;

    /**
     * @mago-expect lint:excessive-parameter-list The 0.x constructor signature is kept.
     */
    public function __construct(
        private TemplateRendererInterface $renderer,
        private InstallerService $installerService,
        private DiagnosticsService $diagnosticsService,
        private DatabaseConfigWriter $configWriter,
        private CacheService $cacheService,
        private Adapter $adapter,
    ) {}

    /**
     * The administrator account from the form, or null unless a username, an
     * email and a password of at least MINIMUM_PASSWORD_LENGTH characters
     * were all given. There are no defaults.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array{username: string, email: string, password: string}|null
     *
     * @mago-expect analysis:mixed-assignment Form values are untyped; their types are checked here.
     */
    private static function adminCredentials(array $data): ?array
    {
        $username = $data['admin_username'] ?? null;
        $email    = $data['admin_email'] ?? null;
        $password = $data['admin_password'] ?? null;

        if (
            ! is_string($username)
            || '' === trim($username)
            || ! is_string($email)
            || '' === trim($email)
            || ! is_string($password)
            || mb_strlen($password) < self::MINIMUM_PASSWORD_LENGTH
        ) {
            return null;
        }

        return ['username' => $username, 'email' => $email, 'password' => $password];
    }

    /**
     * The message for a `success` or `error` query parameter: "1" means the
     * generic message, any other string is shown as given.
     */
    private static function message(mixed $value, string $generic): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return '1' === $value ? $generic : $value;
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->installerService->isInstalled()) {
            return new EmptyResponse(404);
        }

        if ('POST' === $request->getMethod()) {
            $body     = $request->getParsedBody();
            $response = $this->handleAction(is_array($body) ? $body : []);
            if (null !== $response) {
                return $response;
            }
        }

        return $this->renderPage($request->getQueryParams());
    }

    /**
     * Step 3: check the database answers a trivial query.
     */
    private function checkConnection(): ResponseInterface
    {
        try {
            $this->adapter->query('SELECT 1', Adapter::QUERY_MODE_EXECUTE);
        } catch (DbRuntimeException $e) {
            return new RedirectResponse('/setup?step=test-connection&error=' . urlencode($e->getMessage()));
        }

        return new RedirectResponse('/setup?step=admin-user&success=1');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function handleAction(array $data): ?ResponseInterface
    {
        return match ($data['action'] ?? '') {
            'diagnostics'     => $this->runDiagnostics(),
            'database-config' => null === ($data['proceed'] ?? null)
                ? $this->saveDatabaseConfig($data)
                : new RedirectResponse('/setup?step=database-config'),
            'test-connection' => $this->checkConnection(),
            'install'         => $this->install($data),
            default           => null,
        };
    }

    /**
     * Step 4: run the installation and create the administrator.
     *
     * @param array<array-key, mixed> $data
     */
    private function install(array $data): ResponseInterface
    {
        $admin = self::adminCredentials($data);
        if (null === $admin) {
            return new RedirectResponse(
                '/setup?step=admin-user&error='
                    . urlencode(sprintf(
                        'An administrator username, email and a password of at least %d characters are required.',
                        self::MINIMUM_PASSWORD_LENGTH,
                    )),
            );
        }

        try {
            $installed = $this->installerService->install($admin);
        } catch (Exception $e) {
            return new RedirectResponse('/setup?step=admin-user&error=' . urlencode($e->getMessage()));
        }

        if (! $installed) {
            return new RedirectResponse('/setup?step=admin-user&error=validation-failed');
        }

        $this->cacheService->clearAll();

        return new HtmlResponse($this->renderer->render('setup::complete', [
            'title'  => 'Installation Complete',
            'errors' => $this->installerService->validate(),
        ]));
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private function renderPage(array $query): ResponseInterface
    {
        try {
            $dbExists = $this->installerService->databaseFileExists();
        } catch (RuntimeException) {
            $dbExists = false;
        }

        $step        = is_string($query['step'] ?? null) ? $query['step'] : null;
        $error       = null;
        $success     = null;
        $diagnostics = null;

        if (null !== $step) {
            $success = self::message($query['success'] ?? null, 'Operation completed successfully.');
            $error   = self::message($query['error'] ?? null, 'Operation failed. Please check the details below.');

            if ('diagnostics' === $step) {
                $diagnostics = $this->diagnosticsService->runAll();
                $success     = $diagnostics['success'] ? 'All system checks passed.' : $success;
                $error       = $diagnostics['success']
                    ? $error
                    : 'Some system checks failed. Please resolve the issues before continuing.';
            }
        }

        if (null === $step) {
            $step = $dbExists && null === ($query['force'] ?? null) ? 'installed' : 'welcome';
        }

        return new HtmlResponse($this->renderer->render('setup::install', [
            'title'       => 'Contenir CMS Setup',
            'step'        => $step,
            'error'       => $error,
            'success'     => $success,
            'diagnostics' => $diagnostics,
            'formData'    => [],
            'isInstalled' => false,
            'dbExists'    => $dbExists,
        ]));
    }

    /**
     * Step 1: clear the cache and run the diagnostics.
     */
    private function runDiagnostics(): ResponseInterface
    {
        $this->cacheService->clearAll();

        return $this->diagnosticsService->runAll()['success']
            ? new RedirectResponse('/setup?step=diagnostics&success=1')
            : new RedirectResponse('/setup?step=diagnostics&error=1');
    }

    /**
     * Step 2: write the database configuration.
     *
     * @param array<array-key, mixed> $data
     */
    private function saveDatabaseConfig(array $data): ResponseInterface
    {
        try {
            $this->configWriter->write($data);
        } catch (Exception $e) {
            return new RedirectResponse('/setup?step=database-config&error=' . urlencode($e->getMessage()));
        }

        $this->cacheService->clearAll();

        return new RedirectResponse('/setup?step=test-connection&success=1');
    }
}
