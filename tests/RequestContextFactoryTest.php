<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests;

use HttpSoft\Message\ServerRequest;
use PHPForge\Inertia\Exception\InvalidRequestContextException;
use PHPForge\Inertia\RequestContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Inertia\RequestContextFactory;

#[Group('request')]
final class RequestContextFactoryTest extends TestCase
{
    public function testBuildsAbsoluteUrlFromRelativePsrUriAndHostHeader(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: '/users?active=1'))
            ->withHeader('Host', 'example.test:8080');
        $factory = new RequestContextFactory();

        $context = $factory->create($request);

        self::assertSame(
            'http://example.test:8080/users?active=1',
            $context->absoluteUrl,
            'Context URL must include the request authority.',
        );
        self::assertSame(
            'http://example.test:8080/login',
            $factory->absoluteUrl($request, '/login'),
            'Relative targets must inherit the request authority.',
        );
        self::assertSame(
            'https://other.test/login',
            $factory->absoluteUrl($request, 'https://other.test/login'),
            'Absolute targets must remain unchanged.',
        );
        self::assertSame(
            '//other.test/login',
            $factory->absoluteUrl($request, '//other.test/login'),
            'Network-path references must remain unchanged.',
        );
    }
    public function testCreatesValidatedContextWithEverySupportedHeader(): void
    {
        $request = new ServerRequest(method: 'patch', uri: 'https://example.test:8443/users?active=1');

        foreach (
            [
                RequestContext::HEADER_ERROR_BAG => 'profile',
                RequestContext::HEADER_EXCEPT_ONCE_PROPS => 'countries',
                RequestContext::HEADER_INERTIA => '1',
                RequestContext::HEADER_INFINITE_SCROLL_MERGE_INTENT => 'prepend',
                RequestContext::HEADER_PARTIAL_COMPONENT => 'Users',
                RequestContext::HEADER_PARTIAL_DATA => 'users',
                RequestContext::HEADER_PARTIAL_EXCEPT => 'private',
                RequestContext::HEADER_PURPOSE => 'prefetch',
                RequestContext::HEADER_RESET => 'users',
                RequestContext::HEADER_VERSION => 'v2',
            ] as $name => $value
        ) {
            $request = $request->withHeader($name, $value);
        }

        $request = $request->withHeader('Authorization', 'Bearer secret');

        $context = (new RequestContextFactory())
            ->create($request);

        self::assertSame(
            'PATCH',
            $context->method,
            'Method must be normalized to uppercase.',
        );
        self::assertSame(
            '/users?active=1',
            $context->url,
            'Relative URL must preserve path and query.',
        );
        self::assertSame(
            'https://example.test:8443/users?active=1',
            $context->absoluteUrl,
            'Absolute URL must preserve scheme, authority, path, and query.',
        );
        self::assertTrue(
            $context->isInertia(),
            'Inertia marker must be detected.',
        );
        self::assertSame(
            'profile',
            $context->errorBag(),
            'Error bag header must be exposed.',
        );
        self::assertSame(
            ['countries'],
            $context->exceptOnceProps(),
            'Except-once props must be parsed into a list.',
        );
        self::assertSame(
            'prepend',
            $context->infiniteScrollMergeIntent(),
            'Merge intent header must be exposed.',
        );
        self::assertTrue($context->isPrefetch(), 'Prefetch purpose must be detected.');
        self::assertFalse(
            $context->hasHeader('Authorization'),
            'Sensitive headers must not enter the context.',
        );
    }

    public function testNormalizesAnEmptyRequestPathToRoot(): void
    {
        $context = (new RequestContextFactory())
            ->create(new ServerRequest(method: 'GET', uri: 'https://example.test'));

        self::assertSame(
            '/',
            $context->url,
            'Relative URL must use the root path.',
        );
        self::assertSame(
            'https://example.test/',
            $context->absoluteUrl,
            'Absolute URL must include the root path.',
        );
    }
    public function testPreservesIpv6HostAuthorityAndRejectsMissingAuthority(): void
    {
        $factory = new RequestContextFactory();
        $ipv6 = (new ServerRequest(method: 'GET', uri: '/status'))
            ->withHeader('Host', '[::1]:8080');

        self::assertSame(
            'http://[::1]:8080/status',
            $factory->create($ipv6)->absoluteUrl,
            'IPv6 authority must preserve brackets and port.',
        );

        $this->expectException(InvalidRequestContextException::class);

        $factory->create(new ServerRequest(method: 'GET', uri: '/status'));
    }

}
