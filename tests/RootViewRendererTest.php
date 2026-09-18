<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests;

use PHPForge\Inertia\Page;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Inertia\RootViewRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\View\Exception\ViewNotFoundException;
use Yiisoft\View\WebView;

use function basename;
use function dirname;
use function file_put_contents;
use function rename;
use function strpos;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[Group('view')]
final class RootViewRendererTest extends TestCase
{
    private const string DEFAULT_ROOT_VIEW = __DIR__ . '/../resources/views/app.php';

    public function testConfigurationMethodsAreImmutable(): void
    {
        $path = $this->createView('<?= $id ?>|<?= $language ?>|<?= $charset ?>|<?= $title ?>');
        $renderer = $this->renderer($path);

        $configured = $renderer
            ->withId('root')
            ->withLanguage('es')
            ->withCharset('ISO-8859-1')
            ->withTitle('Configured');

        try {
            self::assertSame(
                'app|en|UTF-8|Yii3 Inertia',
                $renderer->render(new Page('Home', [], '/', '')),
                'Original renderer must retain its defaults.',
            );
            self::assertSame(
                'root|es|ISO-8859-1|Configured',
                $configured->render(new Page('Home', [], '/', '')),
                'Configured renderer must expose the updated values.',
            );
        } finally {
            unlink($path);
        }
    }

    public function testConfigurationMethodsReturnNewInstances(): void
    {
        $renderer = $this->renderer('/unused');

        self::assertNotSame(
            $renderer,
            $renderer->withCharset('ISO-8859-1'),
            'Charset configuration must be isolated in a new instance.',
        );
        self::assertNotSame(
            $renderer,
            $renderer->withId('root'),
            'Identifier configuration must be isolated in a new instance.',
        );
        self::assertNotSame(
            $renderer,
            $renderer->withLanguage('es'),
            'Language configuration must be isolated in a new instance.',
        );
        self::assertNotSame(
            $renderer,
            $renderer->withRootView('/custom'),
            'Root-view configuration must be isolated in a new instance.',
        );
        self::assertNotSame(
            $renderer,
            $renderer->withTitle('Configured'),
            'Title configuration must be isolated in a new instance.',
        );
    }

    public function testDefaultRootViewLeavesNoUnresolvedViewPlaceholders(): void
    {
        $html = $this->renderer(self::DEFAULT_ROOT_VIEW)->render(new Page('Home', [], '/', ''));

        self::assertStringNotContainsString(
            '<![CDATA[YII-BLOCK-',
            $html,
            'Placeholders must be substituted.',
        );
    }

    public function testMissingRootViewUsesYiiViewException(): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'missing-inertia-view-');

        self::assertNotFalse(
            $temporaryPath,
            'Temporary path for the missing view must be created.',
        );
        self::assertTrue(
            unlink($temporaryPath),
            'Temporary file for the missing view must be removed.',
        );

        $rootView = "{$temporaryPath}.php";

        $renderer = $this->renderer($rootView);

        $this->expectException(ViewNotFoundException::class);
        $this->expectExceptionMessage($rootView);

        $renderer->render(new Page('Home', [], '/', ''));
    }

    public function testRegisteredAssetsAreInjectedIntoDefaultRootViewPlaceholders(): void
    {
        $view = new WebView();

        $view->registerCssFile('/build/app.css');
        $view->registerJsFile('/build/app.js', options: ['type' => 'module']);
        $view->registerLink(['rel' => 'modulepreload', 'href' => '/build/vendor.js']);

        $html = $this->renderer(self::DEFAULT_ROOT_VIEW, view: $view)->render(new Page('Home', [], '/', ''));

        self::assertStringContainsString(
            '<link rel="stylesheet" href="/build/app.css">',
            $html,
            'Stylesheet must reach the document.',
        );
        self::assertStringContainsString(
            '<link rel="modulepreload" href="/build/vendor.js">',
            $html,
            'Preload hint must reach the document.',
        );
        self::assertStringContainsString(
            '<script src="/build/app.js" type="module"></script>',
            $html,
            'Module script must reach the document.',
        );
        self::assertLessThan(
            strpos($html, '</head>'),
            strpos($html, '/build/app.css'),
            'Stylesheet must land in the head placeholder.',
        );
        self::assertGreaterThan(
            strpos($html, '<div id="app"></div>'),
            strpos($html, '/build/app.js'),
            'Module script must land in the body-end placeholder.',
        );
    }

    public function testRootViewAliasIsResolvedBeforeYiiViewRendering(): void
    {
        $path = $this->createView('resolved');

        $aliases = new Aliases(['@rootView' => dirname($path)]);

        try {
            self::assertSame(
                'resolved',
                $this->renderer('@rootView/' . basename($path), $aliases)->render(new Page('Home', [], '/', '')),
                'Alias must resolve to the expected view file.',
            );
        } finally {
            unlink($path);
        }
    }

    public function testRootViewIsRenderedInYiiWebViewContext(): void
    {
        $path = $this->createView('<?= $this::class ?>');

        try {
            self::assertSame(
                WebView::class,
                $this->renderer($path)->render(new Page('Home', [], '/', '')),
                'Template context must be the Yii web view.',
            );
        } finally {
            unlink($path);
        }
    }

    public function testViewDataIsAvailableAsVariablesAndAsAnAggregate(): void
    {
        $path = $this->createView('<?= $custom ?>:<?= $viewData[\'custom\'] ?>');

        try {
            self::assertSame(
                'value:value',
                $this->renderer($path)->render(new Page('Home', [], '/', ''), ['custom' => 'value']),
                'Individual and aggregate variables must contain identical data.',
            );
        } finally {
            unlink($path);
        }
    }

    private function createView(string $content): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'inertia-view-');

        self::assertNotFalse(
            $temporaryPath,
            'Temporary view file must be created.',
        );

        $path = "{$temporaryPath}.php";

        self::assertTrue(
            rename($temporaryPath, $path),
            'Temporary view file must receive a PHP extension.',
        );

        file_put_contents($path, $content);

        return $path;
    }

    private function renderer(
        string $rootView,
        Aliases|null $aliases = null,
        WebView|null $view = null,
    ): RootViewRenderer {
        $rootViewRenderer = new RootViewRenderer(
            aliases: $aliases ?? new Aliases(),
            view: $view ?? new WebView(),
        );

        return $rootViewRenderer->withRootView($rootView);
    }
}
