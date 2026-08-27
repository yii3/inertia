<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests;

use DateTimeImmutable;
use HttpSoft\Message\{Response, ServerRequest};
use PHPForge\Inertia\Exception\{InvalidPageInputException, Message as CoreMessage, PropResolutionException};
use PHPForge\Inertia\Prop\{AlwaysProp, Prop, ScrollMetadata};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Inertia\Tests\Provider\InertiaCoreIntegrationProvider;
use Yii3\Inertia\Tests\Support\{FakeFlash, RecordingPageObserver, ServiceFactory};
use Yiisoft\Json\Json;

/**
 * Verifies integration between the Yii adapter and the framework-neutral protocol core.
 *
 * {@see InertiaCoreIntegrationProvider} for test case data providers.
 */
#[Group('inertia')]
#[Group('integration')]
final class InertiaCoreIntegrationTest extends TestCase
{
    public function testCoreRejectsConflictingSharedPropPaths(): void
    {
        $inertia = ServiceFactory::create(new ServerRequest(method: 'GET', uri: 'https://example.test/'));

        $inertia->share('profile', 'scalar');
        $inertia->share('profile.name', 'Ada');

        $this->expectException(InvalidPageInputException::class);
        $this->expectExceptionMessage(
            CoreMessage::PROP_PATH_CONFLICT->getMessage('profile.name', 'profile'),
        );

        $inertia->render('Home');
    }
    public function testDeferredRescueAndNativeScrollMetadata(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/dashboard'))
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Partial-Component', 'Dashboard')
            ->withHeader('X-Inertia-Partial-Data', 'metrics,feed');

        $inertia = ServiceFactory::create($request);
        $deferred = Prop::defer(
            static function (): never {
                throw new RuntimeException('metrics failed');
            },
            rescue: true,
        );

        self::assertTrue(
            $deferred->rescuesFailures(),
            'Enabled rescue mode must report that failures are rescued.',
        );
        self::assertFalse(
            $deferred->rescue(false)->rescuesFailures(),
            'Deferred props should no longer rescue failures after rescue is disabled.',
        );

        $response = $inertia->render('Dashboard', [
            'metrics' => $deferred,
            'feed' => Prop::scroll(
                ['data' => [['id' => 1]]],
                new ScrollMetadata('page', null, 2, 1),
            ),
        ]);

        $page = Json::decode((string) $response->getBody());

        self::assertIsArray(
            $page,
            'The protocol response should decode to a page array.',
        );
        self::assertArrayHasKey(
            'props',
            $page,
            'The page should expose resolved props.',
        );
        self::assertIsArray(
            $page['props'],
            'Resolved props should be represented as an array.',
        );
        self::assertArrayHasKey(
            'rescuedProps',
            $page,
            'The page should identify props whose failures were rescued.',
        );
        self::assertArrayHasKey(
            'mergeProps',
            $page,
            'The page should expose merge metadata.',
        );
        self::assertArrayHasKey(
            'scrollProps',
            $page,
            'The page should expose scroll metadata.',
        );
        self::assertIsArray(
            $page['scrollProps'],
            'Scroll metadata should be represented as an array.',
        );
        self::assertArrayHasKey(
            'feed',
            $page['scrollProps'],
            'Scroll metadata should include the feed prop.',
        );
        self::assertIsArray(
            $page['scrollProps']['feed'],
            'The feed scroll metadata should be represented as an array.',
        );
        self::assertArrayHasKey(
            'pageName',
            $page['scrollProps']['feed'],
            'The feed scroll metadata should include its page parameter.',
        );
        self::assertArrayNotHasKey(
            'metrics',
            $page['props'],
            'A rescued prop should be omitted from resolved props.',
        );
        self::assertSame(
            ['metrics'],
            $page['rescuedProps'],
            'The page should identify metrics as the rescued prop.',
        );
        self::assertSame(
            ['feed.data'],
            $page['mergeProps'],
            'The feed data path should be registered for merging.',
        );
        self::assertSame(
            'page',
            $page['scrollProps']['feed']['pageName'],
            'The feed should use the configured page parameter.',
        );
    }

    public function testNativePropsAndZeroArgumentCallbacksResolveThroughCore(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/dashboard'))
            ->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create($request);

        $expires = new DateTimeImmutable('@2000000000');

        $response = $inertia->render(
            'Dashboard',
            [
                'path' => static fn(): string => $request->getUri()->getPath(),
                'wrapped' => static fn(): AlwaysProp => Prop::always($request->getMethod()),
                'nested' => static fn(): array => [
                    'method' => $request->getMethod(...),
                ],
                'records' => Prop::merge(['data' => [], 'notifications' => []])
                    ->append('data', 'id')
                    ->prepend('notifications', 'uuid'),
                'cache' => Prop::once(static fn(): string => 'cached')
                    ->as('cache-key')
                    ->fresh()
                    ->until($expires),
                'uncached' => static fn(): string => 'fresh',
                'native' => Prop::always('core'),
            ],
        );

        $page = Json::decode((string) $response->getBody());

        self::assertIsArray(
            $page,
            'The protocol response should decode to a page array.',
        );
        self::assertArrayHasKey(
            'props',
            $page,
            'The page should expose the resolved native props.',
        );
        self::assertIsArray(
            $page['props'],
            'Resolved native props should be represented as an array.',
        );
        self::assertArrayHasKey(
            'mergeProps',
            $page,
            'The page should expose merge metadata.',
        );
        self::assertArrayHasKey(
            'prependProps',
            $page,
            'The page should expose prepend metadata.',
        );
        self::assertArrayHasKey(
            'matchPropsOn',
            $page,
            'The page should expose merge-match metadata.',
        );
        self::assertArrayHasKey(
            'onceProps',
            $page,
            'The page should expose once-prop metadata.',
        );
        self::assertArrayHasKey(
            'path',
            $page['props'],
            'The resolved props should include the request path.',
        );
        self::assertArrayHasKey(
            'wrapped',
            $page['props'],
            'The resolved props should include the wrapped value.',
        );
        self::assertArrayHasKey(
            'nested',
            $page['props'],
            'The resolved props should include the nested callback value.',
        );
        self::assertArrayHasKey(
            'uncached',
            $page['props'],
            'The resolved props should include the plain callback value.',
        );
        self::assertArrayHasKey(
            'native',
            $page['props'],
            'The resolved props should include the native core value.',
        );
        self::assertSame(
            '/dashboard',
            $page['props']['path'],
            'A zero-argument callback should capture the request path.',
        );
        self::assertSame(
            'GET',
            $page['props']['wrapped'],
            'The wrapped prop should preserve the request method.',
        );
        self::assertSame(
            ['method' => 'GET'],
            $page['props']['nested'],
            'Nested callbacks should resolve their captured request values.',
        );
        self::assertSame(
            'fresh',
            $page['props']['uncached'],
            'Plain callbacks should resolve their returned value.',
        );
        self::assertSame(
            'core',
            $page['props']['native'],
            'Native core props should retain their value.',
        );
        self::assertSame(
            ['records.data'],
            $page['mergeProps'],
            'Only the configured record data path should be merged.',
        );
        self::assertSame(
            ['records.notifications'],
            $page['prependProps'],
            'Only the configured notification path should be prepended.',
        );
        self::assertSame(
            ['records.data.id', 'records.notifications.uuid'],
            $page['matchPropsOn'],
            'Merge matching should retain both configured identity paths.',
        );
        self::assertSame(
            ['cache-key' => ['prop' => 'cache', 'expiresAt' => 2_000_000_000_000]],
            $page['onceProps'],
            'Once metadata should preserve the cache key, prop name, and expiration time.',
        );
    }

    public function testNonInertiaLocationAndResponseNormalizationRemainPsrCompatible(): void
    {
        $request = new ServerRequest(method: 'GET', uri: 'https://example.test/start');

        $inertia = ServiceFactory::create($request);

        $location = $inertia->location('/login');
        $normalized = $inertia->normalizeResponse(
            $request,
            (new Response(201))
                ->withHeader('Vary', 'Accept-Encoding'),
        );

        self::assertSame(
            302,
            $location->getStatusCode(),
            'A non-protocol location response should use a temporary redirect.',
        );
        self::assertSame(
            'https://example.test/login',
            $location->getHeaderLine('Location'),
            'A relative location should resolve against the current request URI.',
        );
        self::assertSame(
            201,
            $normalized->getStatusCode(),
            'A non-redirect response should preserve its status code.',
        );
        self::assertSame(
            'Accept-Encoding, X-Inertia',
            $normalized->getHeaderLine('Vary'),
            'The protocol header should be appended to the existing Vary value.',
        );

        $invalidRedirect = $inertia->normalizeResponse(
            $request,
            (new Response(302))
                ->withHeader('Location', 'relative'),
        );

        self::assertSame(
            302,
            $invalidRedirect->getStatusCode(),
            'A redirect with a relative target should preserve its status code.',
        );
        self::assertSame(
            'relative',
            $invalidRedirect->getHeaderLine('Location'),
            'A non-root-relative redirect target should remain unchanged.',
        );
    }

    public function testObserverReceivesCorePageButNotVersionConflict(): void
    {
        $observer = new RecordingPageObserver();
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/dashboard'))
            ->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create($request, pageObserver: $observer);

        $inertia->render('Dashboard', ['count' => 1]);

        self::assertCount(
            1,
            $observer->pages,
            'The observer should receive exactly one rendered page.',
        );
        self::assertSame(
            'Dashboard',
            $observer->pages[0]->component,
            'The observed page should retain its component name.',
        );
        $observedPage = $observer->pages[0];

        $conflictRequest = $request->withHeader('X-Inertia-Version', 'old');

        $flash = new FakeFlash(['notice' => 'preserve me']);

        ServiceFactory::create(
            $conflictRequest,
            flash: $flash,
            version: 'new',
            pageObserver: $observer,
        )->render('Dashboard');

        self::assertSame(
            [$observedPage],
            $observer->pages,
            'A version conflict should not notify the observer again.',
        );
        self::assertSame(
            0,
            $flash->getAllCalls,
            'A version conflict must not read flash data.',
        );
    }

    public function testPropCallbacksAreInvokedWithoutArguments(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/'))
            ->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create($request);

        $this->expectException(PropResolutionException::class);
        $this->expectExceptionMessage(
            'invalid',
        );

        $inertia->render(
            'Home',
            [
                'invalid' => static fn(string $required): string => $required,
            ],
        );
    }

    #[DataProviderExternal(InertiaCoreIntegrationProvider::class, 'invalidSharedPaths')]
    public function testRejectsInvalidSharedPaths(string $path): void
    {
        $inertia = ServiceFactory::create(new ServerRequest(method: 'GET', uri: 'https://example.test/'));

        $this->expectException(InvalidPageInputException::class);
        $this->expectExceptionMessage(
            CoreMessage::PAGE_KEY_INVALID->getMessage('shared props'),
        );

        $inertia->share($path, 1);
        $inertia->render('Home');
    }

    public function testRejectsReservedErrorsSharedPath(): void
    {
        $inertia = ServiceFactory::create(new ServerRequest(method: 'GET', uri: 'https://example.test/'));

        $this->expectException(InvalidPageInputException::class);
        $this->expectExceptionMessage(
            CoreMessage::RESERVED_ERRORS_PROP->getMessage(),
        );

        $inertia->share('errors', ['message' => 'Invalid request.']);
        $inertia->render('Home');
    }

    #[DataProviderExternal(InertiaCoreIntegrationProvider::class, 'redirectStatuses')]
    public function testResponseNormalizationRecognizesEverySupportedRedirectStatus(int $statusCode): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/start'))
            ->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create($request);

        $response = $inertia->normalizeResponse(
            $request,
            (new Response($statusCode))
                ->withHeader('Location', '/users#active'),
        );

        self::assertSame(
            409,
            $response->getStatusCode(),
            "Redirect status $statusCode must be normalized.",
        );
        self::assertSame(
            'https://example.test/users#active',
            $response->getHeaderLine('X-Inertia-Redirect'),
            'The normalized response should expose an absolute protocol redirect target.',
        );
    }

    public function testResponseNormalizationSkipsMissingLocationsAndUnsupportedStatuses(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/start'))
            ->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create($request);

        self::assertSame(
            302,
            $inertia->normalizeResponse($request, new Response(302))->getStatusCode(),
            'A redirect without a location should retain its original status.',
        );
        self::assertSame(
            201,
            $inertia->normalizeResponse(
                $request,
                (new Response(201))->withHeader('Location', '/users#active'),
            )->getStatusCode(),
            'An unsupported redirect status should remain unchanged.',
        );
    }

    public function testScalarErrorFlashAndNonStringKeysAreNormalizedByAdapter(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/'))
            ->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create(
            $request,
            new FakeFlash(['errors' => 'Invalid request.', 'notice' => 'Try again.']),
        );
        $page = Json::decode((string) $inertia->render('Home')->getBody());

        self::assertIsArray(
            $page,
            'The protocol response should decode to a page array.',
        );
        self::assertArrayHasKey(
            'props',
            $page,
            'The page should expose normalized props.',
        );
        self::assertIsArray(
            $page['props'],
            'Normalized props should be represented as an array.',
        );
        self::assertArrayHasKey(
            'errors',
            $page['props'],
            'Normalized props should contain an errors entry.',
        );
        self::assertArrayHasKey(
            'flash',
            $page,
            'The page should expose non-error flash data.',
        );
        self::assertSame(
            ['message' => 'Invalid request.'],
            $page['props']['errors'],
            'A scalar error should be wrapped in a message entry.',
        );
        self::assertSame(
            ['notice' => 'Try again.'],
            $page['flash'],
            'Non-error flash data should remain available at page level.',
        );
    }
    public function testSharedHelpersAndRequestMarkerCompatibility(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/'))
            ->withHeader('X-Inertia', '1');

        $inertia = ServiceFactory::create($request, shared: ['application' => ['name' => 'Example']]);

        self::assertTrue(
            $inertia->isInertiaRequest(),
            'A truthy protocol marker should identify an Inertia request.',
        );
        self::assertFalse(
            $inertia->isInertiaRequest(new ServerRequest(method: 'GET', uri: 'https://example.test/')),
            'A request without the protocol marker should not be identified as Inertia.',
        );
        self::assertSame(
            ['application' => ['name' => 'Example']],
            $inertia->getShared(),
            'The complete shared state should retain its initial nested data.',
        );
        self::assertSame(
            'fallback',
            $inertia->getShared('missing', 'fallback'),
            'A missing shared key should return the provided fallback.',
        );

        $inertia->share(
            [
                'auth.user' => ['id' => 7],
                'locale' => 'en',
            ],
        );

        self::assertSame(
            ['id' => 7],
            $inertia->getShared('auth.user'),
            'A dotted shared key should resolve its nested value.',
        );
        self::assertSame(
            'en',
            $inertia->getShared('locale'),
            'A top-level shared key should resolve its value.',
        );

        $inertia->flushShared();

        self::assertSame(
            [],
            $inertia->getShared(),
            'After flushing, the shared state should be empty.',
        );
    }

    public function testVaryNormalizationTrimsAndDeduplicatesCaseInsensitively(): void
    {
        $request = new ServerRequest(method: 'GET', uri: 'https://example.test/start');

        $response = ServiceFactory::create($request)->normalizeResponse(
            $request,
            (new Response(204))
                ->withHeader('Vary', ' Accept-Encoding , ACCEPT-ENCODING, X-INERTIA '),
        );

        self::assertSame(
            'Accept-Encoding, X-INERTIA',
            $response->getHeaderLine('Vary'),
            'Vary values should be trimmed and deduplicated without changing the retained casing.',
        );
    }

    public function testVersionCallbacksAreInvokedWithoutArguments(): void
    {
        $request = new ServerRequest(method: 'GET', uri: 'https://example.test/releases/current');

        $zeroArgument = ServiceFactory::create($request, version: static fn(): int => 42);

        self::assertSame(
            42,
            $zeroArgument->getVersion(),
            'A zero-argument version callback should return its captured value.',
        );
    }
}
