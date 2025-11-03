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
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function urldecode;
use function urlencode;

/**
 * Install Handler
 *
 * Web-based installation interface for the CMS.
 * Guides users through database setup and initial configuration.
 */
class InstallHandler implements RequestHandlerInterface
{
    public function __construct(
        private TemplateRendererInterface $renderer,
        private InstallerService $installerService,
        private DiagnosticsService $diagnosticsService,
        private DatabaseConfigWriter $configWriter,
        private CacheService $cacheService,
        private Adapter $adapter
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Check installation status
        $isInstalled = $this->installerService->isInstalled();
        $dbExists    = $this->installerService->databaseFileExists();

        $error       = null;
        $success     = null;
        $diagnostics = null;
        $step        = 'welcome';
        $formData    = [];
        $queryParams = $request->getQueryParams();

        // Check for step from query params (after redirect)
        if (isset($queryParams['step'])) {
            $step = $queryParams['step'];

            // Set messages based on query params
            if (isset($queryParams['success'])) {
                if ($queryParams['success'] === '1') {
                    $success = 'Operation completed successfully.';
                } else {
                    $success = urldecode($queryParams['success']);
                }
            }
            if (isset($queryParams['error'])) {
                if ($queryParams['error'] === '1') {
                    $error = 'Operation failed. Please check the details below.';
                } else {
                    $error = urldecode($queryParams['error']);
                }
            }

            // If we're on diagnostics step, run them now
            if ($step === 'diagnostics') {
                $diagnostics = $this->diagnosticsService->runAll();
                if ($diagnostics['success']) {
                    $success = 'All system checks passed.';
                } else {
                    $error = 'Some system checks failed. Please resolve the issues before continuing.';
                }
            }
        } elseif ($dbExists && ! isset($queryParams['force'])) {
            // If database exists (even if migrations pending) and no force parameter, show status
            $step = 'installed';
        }

        // Handle POST request
        if ($request->getMethod() === 'POST') {
            $data   = $request->getParsedBody();
            $action = $data['action'] ?? '';

            if ($action === 'diagnostics') {
                // Step 1: Run diagnostics and clear cache
                $cacheResult = $this->cacheService->clearAll();
                $diagnostics = $this->diagnosticsService->runAll();

                if ($diagnostics['success']) {
                    // Redirect to avoid POST refresh loop
                    return new RedirectResponse('/setup?step=diagnostics&success=1');
                } else {
                    return new RedirectResponse('/setup?step=diagnostics&error=1');
                }
            } elseif ($action === 'database-config') {
                // Step 2: Handle database configuration
                // If just proceeding from diagnostics, show the form
                if (isset($data['proceed'])) {
                    return new RedirectResponse('/setup?step=database-config');
                } else {
                    // Save database configuration
                    try {
                        $this->configWriter->write($data);

                        // Clear cache after config write
                        $this->cacheService->clearAll();

                        return new RedirectResponse('/setup?step=test-connection&success=1');
                    } catch (Exception $e) {
                        $errorMsg = urlencode($e->getMessage());
                        return new RedirectResponse('/setup?step=database-config&error=' . $errorMsg);
                    }
                }
            } elseif ($action === 'test-connection') {
                // Step 3: Test database connections
                $connectionTests = $this->testDatabaseConnections();

                if ($connectionTests['success']) {
                    return new RedirectResponse('/setup?step=admin-user&success=1');
                } else {
                    $errorMsg = urlencode($connectionTests['message']);
                    return new RedirectResponse('/setup?step=test-connection&error=' . $errorMsg);
                }
            } elseif ($action === 'install') {
                // Step 4: Run installation with admin user
                $adminData = [
                    'username' => $data['admin_username'] ?? 'admin',
                    'email'    => $data['admin_email'] ?? '',
                    'password' => $data['admin_password'] ?? '',
                ];

                try {
                    $result = $this->installerService->install($adminData);

                    if ($result) {
                        // Clear cache one final time
                        $this->cacheService->clearAll();

                        // Installation successful - redirect to completion page
                        return new RedirectResponse('/setup/complete');
                    } else {
                        return new RedirectResponse('/setup?step=admin-user&error=validation-failed');
                    }
                } catch (Exception $e) {
                    $errorMsg = urlencode($e->getMessage());
                    return new RedirectResponse('/setup?step=admin-user&error=' . $errorMsg);
                }
            }
        }

        // Show setup page
        return new HtmlResponse($this->renderer->render('setup::install', [
            'title'       => 'Contenir CMS Setup',
            'step'        => $step,
            'error'       => $error,
            'success'     => $success,
            'diagnostics' => $diagnostics,
            'formData'    => $formData,
            'isInstalled' => $isInstalled,
            'dbExists'    => $dbExists,
        ]));
    }

    /**
     * Test database connections
     */
    private function testDatabaseConnections(): array
    {
        try {
            // Test CMS database (SQLite)
            $this->adapter->query('SELECT 1', Adapter::QUERY_MODE_EXECUTE);

            return [
                'success' => true,
                'message' => 'CMS database connection successful',
            ];
        } catch (DbRuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
