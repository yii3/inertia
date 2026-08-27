<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Middleware;

use HttpSoft\Message\{Response, ServerRequest};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ServerRequestInterface, UriInterface};
use Yii3\Inertia\Middleware\CsrfTokenCookieMiddleware;
use Yii3\Inertia\Tests\Support\{CallbackHandler, FakeCsrfToken};

final class CsrfTokenCookieMiddlewareTest extends TestCase
{
    public function testConfigurationMethodsAreImmutable(): void
    {
        $middleware = new CsrfTokenCookieMiddleware(new FakeCsrfToken('token'));

        $configured = $middleware
            ->withCookieName('CUSTOM-TOKEN')
            ->withDomain('example.test')
            ->withPath('/admin')
            ->withSameSite('Strict')
            ->withSecure(false);

        $request = new ServerRequest(method: 'GET', uri: 'http://example.test/');
        $handler = new CallbackHandler(static fn(): Response => new Response());

        $defaultCookie = $middleware
            ->process($request, $handler)
            ->getHeaderLine('Set-Cookie');
        $configuredCookie = $configured
            ->process($request, $handler)
            ->getHeaderLine('Set-Cookie');

        self::assertStringContainsString(
            'XSRF-TOKEN=token',
            $defaultCookie,
            'Default cookie name and token must be preserved.',
        );
        self::assertStringContainsString(
            'Path=/',
            $defaultCookie,
            'Default cookie path must be root.',
        );
        self::assertStringContainsString(
            'SameSite=Lax',
            $defaultCookie,
            'Default SameSite policy must be `Lax`.',
        );
        self::assertStringContainsString(
            'CUSTOM-TOKEN=token',
            $configuredCookie,
            'Configured cookie name must be used.',
        );
        self::assertStringContainsString(
            'Domain=example.test',
            $configuredCookie,
            'Configured domain must be included.',
        );
        self::assertStringContainsString(
            'Path=/admin',
            $configuredCookie,
            'Configured path must be included.',
        );
        self::assertStringContainsString(
            'SameSite=Strict',
            $configuredCookie,
            'Configured SameSite policy must be used.',
        );
        self::assertStringNotContainsString(
            'Secure',
            $configuredCookie,
            'Disabled secure flag must be omitted.',
        );
    }

    public function testConfigurationMethodsReturnNewInstances(): void
    {
        $middleware = new CsrfTokenCookieMiddleware(new FakeCsrfToken('token'));

        self::assertNotSame(
            $middleware,
            $middleware->withCookieName('CUSTOM-TOKEN'),
            'Cookie-name configuration must use a new instance.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withDomain('example.test'),
            'Domain configuration must use a new instance.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withPath('/admin'),
            'Path configuration must use a new instance.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withSameSite('Strict'),
            'SameSite configuration must use a new instance.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withSecure(false),
            'Secure-flag configuration must use a new instance.',
        );
    }

    public function testHttpsSchemeDetectionIsCaseInsensitive(): void
    {
        $uri = self::createStub(UriInterface::class);

        $uri
            ->method('getScheme')
            ->willReturn('HTTPS');

        $request = self::createStub(ServerRequestInterface::class);

        $request
            ->method('getUri')
            ->willReturn($uri);

        $middleware = new CsrfTokenCookieMiddleware(new FakeCsrfToken('token'));

        $response = $middleware->process(
            $request,
            new CallbackHandler(static fn(): Response => new Response()),
        );

        self::assertStringContainsString(
            'Secure',
            $response->getHeaderLine('Set-Cookie'),
            'Uppercase HTTPS must enable the secure flag.',
        );
    }

    public function testIssuesReadableSameSiteCookieAndDetectsHttps(): void
    {
        $middleware = new CsrfTokenCookieMiddleware(new FakeCsrfToken('masked+token'));

        $response = $middleware->process(
            new ServerRequest(method: 'GET', uri: 'https://example.test/'),
            new CallbackHandler(static fn(): Response => new Response()),
        );

        $cookie = $response->getHeaderLine('Set-Cookie');

        self::assertStringContainsString(
            'XSRF-TOKEN=masked%2Btoken',
            $cookie,
            'Token must be URL-encoded in the cookie.',
        );
        self::assertStringContainsString(
            'Path=/',
            $cookie,
            'Cookie path must default to root.',
        );
        self::assertStringContainsString(
            'Secure',
            $cookie,
            'HTTPS requests must enable the secure flag.',
        );
        self::assertStringContainsString(
            'SameSite=Lax',
            $cookie,
            'Cookie must use the default SameSite policy.',
        );
        self::assertStringNotContainsString(
            'HttpOnly',
            $cookie,
            'Token cookie must remain readable by JavaScript.',
        );
    }
}
