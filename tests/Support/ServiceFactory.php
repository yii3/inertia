<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Support;

use Closure;
use HttpSoft\Message\{ResponseFactory, StreamFactory};
use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use PHPForge\Vite\Vite;
use Psr\Http\Message\ServerRequestInterface;
use Yii3\Inertia\{Inertia, ResolvedPageObserverInterface, RootViewRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\RequestProvider\RequestProvider;
use Yiisoft\View\WebView;

final class ServiceFactory
{
    /**
     * @param array<string, mixed> $shared
     * @param (Closure(): (int|string|null))|int|string|null $version
     */
    public static function create(
        ServerRequestInterface $request,
        FakeFlash|null $flash = null,
        array $shared = [],
        Closure|int|string|null $version = null,
        ResolvedPageObserverInterface|null $pageObserver = null,
    ): Inertia {
        $provider = new RequestProvider();

        $provider->set($request);

        $inertia = new Inertia(
            requestProvider: $provider,
            responseFactory: new ResponseFactory(),
            streamFactory: new StreamFactory(),
            flash: $flash ?? new FakeFlash(),
        );

        return $inertia
            ->withRootViewRenderer(self::createRootViewRenderer())
            ->withShared($shared)
            ->withVersion($version)
            ->withPageObserver($pageObserver);
    }

    public static function createRootViewRenderer(): RootViewRenderer
    {
        $aliases = new Aliases();

        $vite = Vite::create(
            DevelopmentConfiguration::create('http://localhost:5173'),
            entrypoints: ['resources/js/app.js'],
        );

        $rootViewRenderer = new RootViewRenderer(
            aliases: $aliases,
            view: new WebView(),
            vite: $vite,
        );

        $renderer = $rootViewRenderer
            ->withRootView(dirname(__DIR__, 2) . '/resources/views/app.php')
            ->withTitle('Test Application');

        return $renderer;
    }
}
