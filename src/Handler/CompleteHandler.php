<?php

declare(strict_types=1);

namespace Contenir\Setup\Handler;

use Contenir\Setup\Service\InstallerService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Shows the completion page after a successful installation, with any
 * validation warnings. Redirects to /setup while not installed.
 *
 * @api
 */
class CompleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private TemplateRendererInterface $renderer,
        private InstallerService $installerService,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->installerService->isInstalled()) {
            return new RedirectResponse('/setup');
        }

        return new HtmlResponse($this->renderer->render('setup::complete', [
            'title'  => 'Installation Complete',
            'errors' => $this->installerService->validate(),
        ]));
    }
}
