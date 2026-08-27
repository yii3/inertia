<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Middleware;

use HttpSoft\Message\{Response, ServerRequest};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Inertia\Middleware\InertiaMiddleware;
use Yii3\Inertia\Tests\Support\{CallbackHandler, ServiceFactory};

#[Group('middleware')]
#[Group('inertia')]
final class InertiaMiddlewareTest extends TestCase
{
    public function testConvertsFragmentRedirectUnlessRequestIsPrefetch(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/start'))
            ->withHeader('X-Inertia', 'true');

        $middleware = new InertiaMiddleware(ServiceFactory::create($request));

        $handler = new CallbackHandler(
            static fn(): Response => (new Response(302))
                ->withHeader('Location', '/users#active'),
        );

        $response = $middleware->process($request, $handler);

        self::assertSame(
            409,
            $response->getStatusCode(),
            'Fragment redirect must use the conflict status.',
        );
        self::assertSame(
            'https://example.test/users#active',
            $response->getHeaderLine('X-Inertia-Redirect'),
            'Redirect header must contain an absolute URL.',
        );
        self::assertFalse(
            $response->hasHeader('Location'),
            'Converted response must omit the standard redirect header.',
        );
        self::assertFalse(
            $response->hasHeader('X-Inertia'),
            'Converted response must not be marked as a page response.',
        );
        self::assertSame(
            '',
            (string) $response->getBody(),
            'Converted response body must be empty.',
        );

        $prefetch = $middleware->process($request->withHeader('Purpose', 'prefetch'), $handler);

        self::assertSame(
            302,
            $prefetch->getStatusCode(),
            'Prefetch redirect must retain its original status.',
        );
        self::assertSame(
            '/users#active',
            $prefetch->getHeaderLine('Location'),
            'Prefetch redirect must retain its relative location.',
        );
    }
    public function testNormalizesMutationRedirectAndVaryWithoutDuplicates(): void
    {
        $request = (new ServerRequest(method: 'PATCH', uri: 'https://example.test/profile'))
            ->withHeader('X-Inertia', 'true');

        $middleware = new InertiaMiddleware(ServiceFactory::create($request));

        $response = $middleware->process(
            $request,
            new CallbackHandler(
                static fn(): Response => (new Response(302))
                    ->withHeader('Location', '/profile')
                    ->withHeader('Vary', 'Accept-Encoding'),
            ),
        );

        self::assertSame(
            303,
            $response->getStatusCode(),
            'Mutation redirect must use the see-other status.',
        );
        self::assertSame(
            'Accept-Encoding, X-Inertia',
            $response->getHeaderLine('Vary'),
            'Vary must include the Inertia marker exactly once.',
        );
    }

    public function testResetsMutableSharedPropsAfterRequest(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/'))->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create($request, shared: ['app' => 'Example']);

        $inertia->share('request.stale', 41);

        $middleware = new InertiaMiddleware($inertia);
        $middleware->process(
            $request,
            new CallbackHandler(
                static function () use ($inertia): Response {
                    self::assertNull(
                        $inertia->getShared('request.stale'),
                        'Request-scoped props must be cleared before delegation.',
                    );

                    $inertia->share('request.user', 42);

                    return new Response();
                },
            ),
        );

        self::assertNull(
            $inertia->getShared('request.user'),
            'Handler mutations must be cleared after delegation.',
        );
        self::assertSame(
            'Example',
            $inertia->getShared('app'),
            'Persistent shared props must survive request cleanup.',
        );
    }
}
