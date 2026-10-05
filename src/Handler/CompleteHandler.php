<?php

declare(strict_types=1);

namespace Contenir\Setup\Handler;

use Contenir\Setup\Service\InstallerServiceInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The former completion page route. InstallHandler now renders the
 * completion page in the response to the final step, so this handler only
 * redirects to /setup while the CMS is not installed, and answers an empty
 * 404 once it is, like every other setup route.
 *
 * @api
 */
final class CompleteHandler implements RequestHandlerInterface
{
    /**
     * @mago-expect analysis:unused-property The renderer is kept for constructor compatibility.
     */
    public function __construct(
        private TemplateRendererInterface $renderer,
        private InstallerServiceInterface $installerService,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->installerService->isInstalled()
            ? new EmptyResponse(404)
            : new RedirectResponse('/setup');
    }
}
