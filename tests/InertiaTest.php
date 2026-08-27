<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests;

use HttpSoft\Message\{ResponseFactory, ServerRequest, StreamFactory};
use PHPForge\Inertia\Prop\Prop;
use PHPForge\Inertia\Protocol;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Inertia\Exception\{ConfigurationException, Message};
use Yii3\Inertia\Inertia;
use Yii3\Inertia\Tests\Support\{FakeFlash, ServiceFactory};
use Yiisoft\Json\Json;
use Yiisoft\RequestProvider\RequestProvider;

#[Group('inertia')]
final class InertiaTest extends TestCase
{
    public function testConfigurationMethodsAreImmutable(): void
    {
        $inertia = ServiceFactory::create(
            (new ServerRequest(method: 'GET', uri: 'https://example.test/'))->withHeader('X-Inertia', 'true'),
        );

        $configured = $inertia
            ->withShared(['application' => ['name' => 'Configured']])
            ->withVersion('configured-version');

        self::assertSame(
            [],
            $inertia->getShared(),
            'The original shared data must remain unchanged.',
        );
        self::assertNull(
            $inertia->getVersion(),
            'The original version must remain unset.',
        );
        self::assertSame(
            'Configured',
            $configured->getShared('application.name'),
            'The configured nested shared value must be available.',
        );
        self::assertSame(
            'configured-version',
            $configured->getVersion(),
            'The configured asset version must be available.',
        );
    }

    public function testConfigurationMethodsReturnNewInstances(): void
    {
        $inertia = ServiceFactory::create(new ServerRequest(method: 'GET', uri: 'https://example.test/'));

        self::assertNotSame(
            $inertia,
            $inertia->withCharset('ISO-8859-1'),
            'Charset configuration must return a new service instance.',
        );
        self::assertNotSame(
            $inertia,
            $inertia->withErrorFlashKey('validation'),
            'Error-flash configuration must return a new service instance.',
        );
        self::assertNotSame(
            $inertia,
            $inertia->withPageObserver(null),
            'Page-observer configuration must return a new service instance.',
        );
        self::assertNotSame(
            $inertia,
            $inertia->withProtocol(Protocol::create()),
            'Protocol configuration must return a new service instance.',
        );
        self::assertNotSame(
            $inertia,
            $inertia->withRootViewRenderer(ServiceFactory::createRootViewRenderer()),
            'Root-view configuration must return a new service instance.',
        );
        self::assertNotSame(
            $inertia,
            $inertia->withShared([]),
            'Shared-data configuration must return a new service instance.',
        );
        self::assertNotSame(
            $inertia,
            $inertia->withVersion(null),
            'Version configuration must return a new service instance.',
        );
    }

    public function testConfiguredDotPathsUseTheSameNestedRepresentationAsRuntimeShares(): void
    {
        $inertia = ServiceFactory::create(
            new ServerRequest(method: 'GET', uri: 'https://example.test/'),
            shared: ['auth.user' => ['id' => 42]],
        );

        self::assertSame(
            ['id' => 42],
            $inertia->getShared('auth.user'),
            'Dot-path lookup must expose the configured leaf value.',
        );
        self::assertSame(
            ['auth' => ['user' => ['id' => 42]]],
            $inertia->getShared(),
            'Configured dot paths must expand into nested shared data.',
        );
    }

    public function testErrorsUseJsonObjectErrorBagAndFlashIsOnlyTopLevel(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/'))
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Error-Bag', 'profile');

        $inertia = ServiceFactory::create(
            $request,
            new FakeFlash(['errors' => ['email' => 'Invalid.'], 'success' => 'Saved.']),
        );

        $body = (string) $inertia->render('Home')->getBody();

        $page = Json::decode($body);

        self::assertIsArray(
            $page,
            'The rendered page must decode to an array.',
        );
        self::assertArrayHasKey(
            'props',
            $page,
            'The page payload must contain its props container.',
        );
        self::assertIsArray(
            $page['props'],
            'The props container must be an array.',
        );
        self::assertArrayHasKey(
            'errors',
            $page['props'],
            'The props container must expose validation errors.',
        );
        self::assertArrayHasKey(
            'flash',
            $page,
            'Flash data must be exposed at the page level.',
        );
        self::assertSame(
            ['profile' => ['email' => 'Invalid.']],
            $page['props']['errors'],
            'Validation errors must use the requested error bag.',
        );
        self::assertArrayNotHasKey(
            'flash',
            $page['props'],
            'Flash data must not be nested inside props.',
        );
        self::assertSame(
            ['success' => 'Saved.'],
            $page['flash'],
            'Only non-error flash entries must be exposed.',
        );

        $emptyBody = (string) ServiceFactory::create($request)->render('Home')->getBody();

        self::assertStringContainsString(
            '"errors":{}',
            $emptyBody,
            'An empty error collection must serialize as a JSON object.',
        );
    }

    public function testExceptOnlyPartialReloadIncludesOptionalPropsNotExcluded(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/dashboard'))
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Partial-Component', 'Dashboard')
            ->withHeader('X-Inertia-Partial-Except', 'private');

        $inertia = ServiceFactory::create($request);

        $response = $inertia->render(
            'Dashboard',
            [
                'optional' => Prop::optional(static fn(): string => 'included'),
                'private' => Prop::optional(static fn(): string => 'excluded'),
            ],
        );

        $page = Json::decode((string) $response->getBody());

        self::assertIsArray(
            $page,
            'The partial response must decode to an array.',
        );
        self::assertArrayHasKey(
            'props',
            $page,
            'The partial response must contain its props container.',
        );
        self::assertIsArray(
            $page['props'],
            'The partial props container must be an array.',
        );
        self::assertArrayHasKey(
            'optional',
            $page['props'],
            'A non-excluded optional prop must be included.',
        );
        self::assertSame(
            'included',
            $page['props']['optional'],
            'The included optional prop must resolve its value.',
        );
        self::assertArrayNotHasKey(
            'private',
            $page['props'],
            'An explicitly excluded prop must remain absent.',
        );
    }

    public function testInitialHtmlSerializesEmptyErrorsAsObject(): void
    {
        $response = ServiceFactory::create(new ServerRequest(method: 'GET', uri: 'https://example.test/'))
            ->render('Home');

        self::assertStringContainsString(
            '"props":{"errors":{}}',
            (string) $response->getBody(),
            'Initial page errors must serialize as a JSON object.',
        );
    }

    public function testInitialHtmlUsesSafeExternalPageDataScriptBeforeRoot(): void
    {
        $inertia = ServiceFactory::create(new ServerRequest(method: 'GET', uri: 'https://example.test/'));

        $response = $inertia->render('Home', ['unsafe' => '</script><script>alert(1)</script>']);

        $html = (string) $response->getBody();

        self::assertSame(
            'text/html; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'Initial responses must declare the HTML content type.',
        );
        self::assertStringContainsString(
            '<script data-page="app" type="application/json">',
            $html,
            'Page data must be emitted through a JSON script element.',
        );
        self::assertStringNotContainsString(
            '</script><script>alert(1)</script>',
            $html,
            'Embedded closing tags must not remain executable markup.',
        );
        self::assertStringContainsString(
            '\\u003C\\/script\\u003E',
            $html,
            'Closing tags in page data must be safely escaped.',
        );
        self::assertLessThan(
            strpos($html, '<div id="app"></div>'),
            strpos($html, '<script data-page="app"'),
            'Page data must appear before the application root element.',
        );
    }

    public function testInitialRenderRequiresConfiguredRootViewRenderer(): void
    {
        $requestProvider = new RequestProvider();

        $requestProvider->set(new ServerRequest(method: 'GET', uri: 'https://example.test/'));

        $inertia = new Inertia(
            $requestProvider,
            new ResponseFactory(),
            new StreamFactory(),
            new FakeFlash(),
        );

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            Message::ROOT_VIEW_RENDERER_NOT_CONFIGURED->getMessage(),
        );

        $inertia->render('Home');
    }

    public function testOnceMetadataSurvivesOmittedValueAndPartialSelectionRefreshesIt(): void
    {
        $fullRequest = (new ServerRequest(method: 'GET', uri: 'https://example.test/settings'))
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Except-Once-Props', 'countries');

        $inertia = ServiceFactory::create($fullRequest);

        $fullPage = Json::decode(
            (string) $inertia->render(
                'Settings',
                [
                    'countries' => Prop::once(static fn(): array => ['CL']),
                ],
            )
            ->getBody(),
        );

        self::assertIsArray(
            $fullPage,
            'The full response must decode to an array.',
        );
        self::assertArrayHasKey(
            'props',
            $fullPage,
            'The full response must contain its props container.',
        );
        self::assertIsArray(
            $fullPage['props'],
            'The full response props must be an array.',
        );
        self::assertArrayNotHasKey(
            'countries',
            $fullPage['props'],
            'An omitted once prop must not expose its value.',
        );
        self::assertArrayHasKey(
            'onceProps',
            $fullPage,
            'The full response must retain once-prop metadata.',
        );
        self::assertSame(
            ['countries' => ['prop' => 'countries', 'expiresAt' => null]],
            $fullPage['onceProps'],
            'Omitted once props must retain their metadata entry.',
        );

        $partialRequest = $fullRequest
            ->withHeader('X-Inertia-Partial-Component', 'Settings')
            ->withHeader('X-Inertia-Partial-Data', 'countries');

        $partialInertia = ServiceFactory::create($partialRequest);
        $partialPage = Json::decode(
            (string) $partialInertia->render(
                'Settings',
                [
                    'countries' => Prop::optional(static fn(): array => ['CL'])->once(),
                ],
            )
            ->getBody(),
        );

        self::assertIsArray(
            $partialPage,
            'The partial response must decode to an array.',
        );
        self::assertArrayHasKey(
            'props',
            $partialPage,
            'The partial response must contain its props container.',
        );
        self::assertIsArray(
            $partialPage['props'],
            'The partial response props must be an array.',
        );
        self::assertArrayHasKey(
            'countries',
            $partialPage['props'],
            'A selected once prop must expose its refreshed value.',
        );
        self::assertSame(
            ['CL'],
            $partialPage['props']['countries'],
            'The selected once prop must resolve the expected data.',
        );
        self::assertArrayHasKey(
            'onceProps',
            $partialPage,
            'The partial response must retain once-prop metadata.',
        );
        self::assertSame(
            ['countries' => ['prop' => 'countries', 'expiresAt' => null]],
            $partialPage['onceProps'],
            'Refreshed once props must retain their metadata entry.',
        );
    }

    public function testPagePropsReplaceSharedTopLevelValuesAndSharedKeysAreExposed(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/'))
            ->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create(
            $request,
            shared: [
                'auth' => ['user' => ['id' => 1], 'permissions' => ['admin']],
                'application' => ['name' => 'Example'],
            ],
        );
        $page = Json::decode(
            (string) $inertia->render(
                'Home',
                [
                    'auth' => ['user' => null],
                ],
            )
            ->getBody(),
        );

        self::assertIsArray(
            $page,
            'The rendered page must decode to an array.',
        );
        self::assertArrayHasKey(
            'props',
            $page,
            'The page payload must contain its props container.',
        );
        self::assertIsArray(
            $page['props'],
            'The props container must be an array.',
        );
        self::assertArrayHasKey(
            'auth',
            $page['props'],
            'Page-level authentication data must be present.',
        );
        self::assertArrayHasKey(
            'sharedProps',
            $page,
            'The page payload must list configured shared keys.',
        );
        self::assertSame(
            ['user' => null],
            $page['props']['auth'],
            'Page-level data must replace the matching shared top-level value.',
        );
        self::assertSame(
            ['auth', 'application'],
            $page['sharedProps'],
            'Shared-key metadata must preserve configured top-level keys.',
        );
    }

    public function testPartialOnlyResolvesRequestedLazyPropsAndAlwaysProps(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/dashboard'))
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Partial-Component', 'Dashboard')
            ->withHeader('X-Inertia-Partial-Data', 'optional,deferred')
            ->withHeader('X-Inertia-Partial-Except', 'deferred');

        $inertia = ServiceFactory::create($request);

        $response = $inertia->render(
            'Dashboard',
            [
                'regular' => static fn(): string => 'regular',
                'optional' => Prop::optional(static fn(): string => 'optional'),
                'deferred' => Prop::defer(static fn(): string => 'deferred'),
                'auth' => Prop::always(['name' => 'Ada']),
            ],
        );

        $page = Json::decode((string) $response->getBody());

        self::assertIsArray(
            $page,
            'The partial response must decode to an array.',
        );
        self::assertArrayHasKey(
            'props',
            $page,
            'The partial response must contain its props container.',
        );
        self::assertSame(
            ['optional' => 'optional', 'auth' => ['name' => 'Ada'], 'errors' => []],
            $page['props'],
            'Only selected optional, always, and error props must remain.',
        );
    }
    public function testRendersInitialJsonWithV3PropsAndMetadata(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/dashboard?tab=all'))
            ->withHeader('X-Inertia', 'true');

        $inertia = ServiceFactory::create(
            $request,
            new FakeFlash(['errors' => ['email' => 'Invalid.'], 'success' => 'Saved.']),
            ['auth' => ['name' => 'Ada']],
        );

        $response = $inertia->render(
            'Dashboard',
            [
                'optional' => Prop::optional(static fn(): string => 'optional'),
                'deferred' => Prop::defer(static fn(): string => 'deferred', 'metrics'),
                'users' => Prop::merge([['id' => 1]])->matchOn('id'),
                'messages' => Prop::merge([1, 2])->prepend(),
                'settings' => Prop::merge(['theme' => 'dark'])->deepMerge(),
                'countries' => Prop::once(static fn(): array => ['CL'])->as('country-list'),
            ],
        );

        $page = Json::decode((string) $response->getBody());

        self::assertIsArray(
            $page,
            'The protocol response must decode to an array.',
        );
        self::assertArrayHasKey(
            'url',
            $page,
            'The protocol payload must expose the current URL.',
        );
        self::assertArrayHasKey(
            'version',
            $page,
            'The protocol payload must expose its asset version.',
        );
        self::assertArrayHasKey(
            'props',
            $page,
            'The protocol payload must contain its props container.',
        );
        self::assertIsArray(
            $page['props'],
            'The protocol props container must be an array.',
        );
        self::assertArrayHasKey(
            'errors',
            $page['props'],
            'The protocol props must expose validation errors.',
        );
        self::assertArrayHasKey(
            'flash',
            $page,
            'The protocol payload must expose top-level flash data.',
        );
        self::assertArrayHasKey(
            'deferredProps',
            $page,
            'The payload must expose deferred-prop metadata.',
        );
        self::assertArrayHasKey(
            'mergeProps',
            $page,
            'The payload must expose merge-prop metadata.',
        );
        self::assertArrayHasKey(
            'prependProps',
            $page,
            'The payload must expose prepend-prop metadata.',
        );
        self::assertArrayHasKey(
            'deepMergeProps',
            $page,
            'The payload must expose deep-merge metadata.',
        );
        self::assertArrayHasKey(
            'matchPropsOn',
            $page,
            'The payload must expose merge-match metadata.',
        );
        self::assertArrayHasKey(
            'onceProps',
            $page,
            'The payload must expose once-prop metadata.',
        );
        self::assertArrayHasKey(
            'sharedProps',
            $page,
            'The payload must expose shared-key metadata.',
        );
        self::assertSame(
            200,
            $response->getStatusCode(),
            'A successful protocol response must use status 200.',
        );
        self::assertSame(
            'application/json; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'Protocol responses must declare the JSON content type.',
        );
        self::assertSame(
            'true',
            $response->getHeaderLine('X-Inertia'),
            'Protocol responses must identify themselves with the Inertia header.',
        );
        self::assertSame(
            'X-Inertia',
            $response->getHeaderLine('Vary'),
            'Protocol responses must vary by the Inertia request header.',
        );
        self::assertSame(
            '/dashboard?tab=all',
            $page['url'],
            'The page URL must preserve its path and query string.',
        );
        self::assertSame(
            '',
            $page['version'],
            'An unset asset version must serialize as an empty string.',
        );
        self::assertArrayNotHasKey(
            'optional',
            $page['props'],
            'Unrequested optional props must remain absent from a full response.',
        );
        self::assertArrayNotHasKey(
            'deferred',
            $page['props'],
            'Deferred props must remain absent until explicitly requested.',
        );
        self::assertSame(
            ['email' => 'Invalid.'],
            $page['props']['errors'],
            'Validation errors must be exposed inside the props container.',
        );
        self::assertSame(
            ['success' => 'Saved.'],
            $page['flash'],
            'Non-error flash data must be exposed at the page level.',
        );
        self::assertSame(
            ['metrics' => ['deferred']],
            $page['deferredProps'],
            'Deferred metadata must group props under their declared group.',
        );
        self::assertSame(
            ['users'],
            $page['mergeProps'],
            'Merge metadata must list mergeable props.',
        );
        self::assertSame(
            ['messages'],
            $page['prependProps'],
            'Prepend metadata must list prepended props.',
        );
        self::assertSame(
            ['settings'],
            $page['deepMergeProps'],
            'Deep-merge metadata must list nested merge props.',
        );
        self::assertSame(
            ['users.id'],
            $page['matchPropsOn'],
            'Match metadata must include the qualified match key.',
        );
        self::assertSame(
            ['country-list' => ['prop' => 'countries', 'expiresAt' => null]],
            $page['onceProps'],
            'Once metadata must use the declared alias and expiration.',
        );
        self::assertSame(
            ['auth'],
            $page['sharedProps'],
            'Shared metadata must list configured top-level keys.',
        );
    }

    public function testResetRestoresOnlyConfiguredSharedProps(): void
    {
        $inertia = ServiceFactory::create(
            (new ServerRequest(method: 'GET', uri: 'https://example.test/'))
                ->withHeader('X-Inertia', 'true'),
            shared: ['app' => ['name' => 'Example']],
        );

        $inertia->share('auth.user', ['id' => 1]);

        self::assertSame(
            ['id' => 1],
            $inertia->getShared('auth.user'),
            'Runtime shared data must be available before state restoration.',
        );

        $inertia->reset();

        self::assertNull(
            $inertia->getShared('auth.user'),
            'Runtime-only shared data must be removed during state restoration.',
        );
        self::assertSame(
            'Example',
            $inertia->getShared('app.name'),
            'Configured shared data must survive state restoration.',
        );
    }

    public function testVersionConflictAndLocationResponses(): void
    {
        $request = (new ServerRequest(method: 'GET', uri: 'https://example.test/dashboard'))
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', 'old');

        $inertia = ServiceFactory::create($request, version: 'new');

        $response = $inertia->render('Dashboard');

        self::assertSame(
            409,
            $response->getStatusCode(),
            'An asset-version conflict must use status 409.',
        );
        self::assertSame(
            'https://example.test/dashboard',
            $response->getHeaderLine('X-Inertia-Location'),
            'A version conflict must request a visit to the current URL.',
        );
        self::assertSame(
            'new',
            $response->getHeaderLine('X-Inertia-Version'),
            'A version conflict must advertise the current asset version.',
        );
        self::assertSame('', (string) $response->getBody(), 'A version-conflict response must have an empty body.');

        $location = $inertia->location('/login');

        self::assertSame(
            409,
            $location->getStatusCode(),
            'An external visit response must use status 409.',
        );
        self::assertSame(
            'https://example.test/login',
            $location->getHeaderLine('X-Inertia-Location'),
            'A relative visit target must resolve against the request origin.',
        );
    }
}
