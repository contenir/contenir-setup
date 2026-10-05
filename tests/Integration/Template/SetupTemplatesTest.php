<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration\Template;

use Contenir\Setup\ConfigProvider;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\TemplatePathStack;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Renders the shipped templates with laminas-view for every wizard step.
 */
#[Group('integration')]
final class SetupTemplatesTest extends TestCase
{
    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function completeStates(): array
    {
        return [
            'no warnings'   => [[], 'Administrator account created'],
            'with warnings' => [['No <administrator> user found'], 'No &lt;administrator&gt; user found'],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function installSteps(): array
    {
        $passed = [
            'success' => true,
            'results' => ['php_version' => ['success' => true, 'message' => 'PHP <checked>']],
            'errors'  => [],
        ];
        $failed = [
            'success' => false,
            'results' => ['php_version' => ['success' => false, 'message' => 'PHP <checked>']],
            'errors'  => ['php_version' => 'PHP <checked>'],
        ];

        return [
            'installed'          => [
                ['step' => 'installed', 'dbExists' => true, 'isInstalled' => true],
                'System Already Installed',
            ],
            'installed, pending' => [['step' => 'installed', 'dbExists' => false], 'Pending'],
            'welcome'            => [['step' => 'welcome'], 'Welcome to Contenir CMS'],
            'diagnostics passed' => [['step' => 'diagnostics', 'diagnostics' => $passed], 'PHP &lt;checked&gt;'],
            'diagnostics failed' => [['step' => 'diagnostics', 'diagnostics' => $failed], 'PHP &lt;checked&gt;'],
            'database config'    => [['step' => 'database-config'], 'Database Configuration'],
            'test connection'    => [['step' => 'test-connection'], 'Test Database Connection'],
            'admin user'         => [['step' => 'admin-user'], 'Create Administrator Account'],
            'escaped messages'   => [
                ['step' => 'welcome', 'error' => '<b>bad</b>', 'success' => 'ok'],
                '&lt;b&gt;bad&lt;/b&gt;',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $variables
     */
    #[Test]
    #[DataProvider('installSteps')]
    public function rendersEveryInstallStep(array $variables, string $expected): void
    {
        $html = $this->render('install', [
            'title'       => 'Contenir CMS Setup',
            'error'       => null,
            'success'     => null,
            'diagnostics' => null,
            'formData'    => [],
            'isInstalled' => false,
            'dbExists'    => false,
            ...$variables,
        ]);

        static::assertStringContainsString($expected, $html);
    }

    /**
     * @param list<string> $errors
     */
    #[Test]
    #[DataProvider('completeStates')]
    public function rendersTheCompletionPage(array $errors, string $expected): void
    {
        $html = $this->render('complete', ['title' => 'Installation Complete', 'errors' => $errors]);

        static::assertStringContainsString($expected, $html);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function render(string $template, array $variables): string
    {
        $renderer = new PhpRenderer();
        $renderer->setResolver(new TemplatePathStack([
            'script_paths' => (new ConfigProvider())->getTemplates()['paths']['setup'],
        ]));

        $model = new ViewModel($variables);
        $model->setTemplate($template);

        return $renderer->render($model);
    }
}
