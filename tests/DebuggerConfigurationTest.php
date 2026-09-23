<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests;

use Closure;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Inertia\Event\ProtocolResultCreated;
use PHPForge\Inertia\{PageInput, Protocol, RequestContext};
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function class_exists;
use function dirname;
use function is_array;
use function spl_autoload_functions;
use function spl_autoload_register;
use function spl_autoload_unregister;

/**
 * Integration tests for the `params`, `events-web`, and `di-web` groups registering the Inertia collector and panel
 * with `yii3/debug`.
 */
final class DebuggerConfigurationTest extends TestCase
{
    public function testDiWebBuildsTheCollectorWithTheHostCapturePolicy(): void
    {
        $definition = self::definitions()[InertiaCollector::class] ?? null;

        self::assertInstanceOf(
            Closure::class,
            $definition,
            'Collector must have a container definition.',
        );

        $collector = $definition(new CapturePolicy());

        self::assertInstanceOf(
            InertiaCollector::class,
            $collector,
            'Definition must build the Inertia collector.',
        );

        $collector->startup();

        Protocol::create(eventDispatcher: $collector)->page(
            new RequestContext(
                'GET',
                '/home?token=abc',
                'https://example.test/home?token=abc',
                ['X-Inertia' => 'true'],
            ),
            PageInput::create(
                'Home',
                ['answer' => 42, 'password' => 'secret'],
                '',
            ),
        );

        $capture = $collector->capture();

        self::assertIsArray(
            $capture,
            'A protocol result must be captured.',
        );

        $page = $capture['page'] ?? null;

        self::assertIsArray(
            $page,
            'Capture must carry the page.',
        );

        $props = $page['props'] ?? null;

        self::assertIsArray(
            $props,
            'Capture must carry the page props.',
        );
        self::assertSame(
            42,
            $props['answer'] ?? null,
            'Plain props must survive.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $props['password'] ?? null,
            'Sensitive props must follow the host policy.',
        );
        self::assertSame(
            '/home?token=%5Bredacted%5D',
            $page['url'] ?? null,
            'Sensitive query values must follow the host policy.',
        );
    }

    #[RunInSeparateProcess]
    public function testDiWebDeclaresNoCollectorWithoutDebugCore(): void
    {
        $loaders = spl_autoload_functions();

        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
        }

        // Hides Debug Core from the autoloaders, as an application installing `yii3/inertia` alone sees it.
        spl_autoload_register(
            static function (string $class) use ($loaders): void {
                if ($class === CapturePolicy::class) {
                    return;
                }

                foreach ($loaders as $loader) {
                    $loader($class);
                }
            },
        );

        self::assertFalse(
            class_exists(CapturePolicy::class),
            'Debug Core must be hidden from this process.',
        );
        self::assertArrayNotHasKey(
            InertiaCollector::class,
            self::definitions(),
            'The container must autowire the collector without redaction.',
        );
    }

    public function testEventsWebRoutesProtocolResultsToTheCollector(): void
    {
        self::assertSame(
            [ProtocolResultCreated::class => [InertiaCollector::class]],
            self::load('events-web.php', []),
            'Protocol results must reach the collector the debugger reads.',
        );
    }

    public function testParamsRegisterTheCollectorAndThePanelWithTheDebugger(): void
    {
        self::assertSame(
            [
                'collectors' => ['inertia' => InertiaCollector::class],
                'panels' => ['inertia' => InertiaPanel::class],
            ],
            self::params()['yii3/debug'] ?? null,
            'Collector and panel must share the stable `inertia` ID.',
        );
    }

    /**
     * Loads `config/di-web.php` with the packaged params in scope.
     *
     * @return array<array-key, mixed> Definitions the package declares for a web request.
     */
    private static function definitions(): array
    {
        return self::load('di-web.php', self::params());
    }

    /**
     * Loads one packaged configuration file the way `yiisoft/config` does, with `$params` in scope.
     *
     * @param string $file File name under `config/`.
     * @param array<array-key, mixed> $params Params visible to the file.
     *
     * @throws RuntimeException When the file does not return an array.
     *
     * @return array<array-key, mixed> Configuration the file returns.
     */
    private static function load(string $file, array $params): array
    {
        $path = dirname(__DIR__) . "/config/{$file}";

        $loader = static function (array $params) use ($path): mixed {
            return require $path;
        };

        $config = $loader($params);

        if (is_array($config) === false) {
            throw new RuntimeException("Packaged configuration \"{$path}\" must return an array.");
        }

        return $config;
    }

    /**
     * Loads `config/params.php`.
     *
     * @return array<array-key, mixed> Params the package declares.
     */
    private static function params(): array
    {
        return self::load('params.php', []);
    }
}
