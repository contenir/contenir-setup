<?php

declare(strict_types=1);

namespace Contenir\Setup\Handler;

use Contenir\Setup\Service\InstallerService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Setup Complete Handler
 *
 * Displays completion message after successful installation.
 */
class CompleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private TemplateRendererInterface $renderer,
        private InstallerService $installerService
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Verify installation is complete
        if (! $this->installerService->isInstalled()) {
            return new RedirectResponse('/setup');
        }

        // Validate installation
        $errors = $this->installerService->validate();

        return new HtmlResponse($this->renderer->render('setup::complete', [
            'title'  => 'Installation Complete',
            'errors' => $errors,
        ]));
    }
}
