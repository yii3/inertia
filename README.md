<!-- markdownlint-disable MD041 -->
<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://www.yiiframework.com/image/design/logo/yii3_full_for_dark.svg">
        <source media="(prefers-color-scheme: light)" srcset="https://www.yiiframework.com/image/design/logo/yii3_full_for_light.svg">
        <img src="https://www.yiiframework.com/image/design/logo/yii3_full_for_light.svg" alt="Yii Framework" width="80%">
    </picture>
</p>

<h1 align="center">Inertia for Yii3</h1>
<!-- markdownlint-enable MD041 -->

<p align="center">
    <a href="https://github.com/yii3/inertia/actions/workflows/build.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii3/inertia/build.yml?branch=main&style=for-the-badge&logo=github&label=PHPUnit" alt="PHPUnit">
    </a>
    <a href="https://github.com/yii3/inertia/actions/workflows/mutation.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii3/inertia/mutation.yml?branch=main&style=for-the-badge&logo=github&label=Mutation" alt="Mutation Testing">
    </a>
    <a href="https://github.com/yii3/inertia/actions/workflows/static.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii3/inertia/static.yml?branch=main&style=for-the-badge&logo=github&label=PHPStan" alt="PHPStan">
    </a>
    <a href="https://github.com/yii3/inertia/actions/workflows/security.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii3/inertia/security.yml?branch=main&style=for-the-badge&label=Security&logo=github" alt="Security">
    </a>
</p>

<p align="center">
    <em>Connect Yii3 requests, responses, views, sessions, and redirects to the Inertia protocol</em>
</p>

Server-side [Inertia.js v3](https://inertiajs.com/) integration for Yii3. The package uses constructor injection,
PSR-7 responses, PSR-15 middleware, and Yii Config Plugin configuration. It does not expose a static facade or read
from a service locator.

## Architecture

The packages have deliberately separate responsibilities:

- [`php-forge/inertia`](https://github.com/php-forge/inertia) implements the framework-agnostic protocol, page model,
  prop resolution, headers, redirects, and result objects.
- `yii3/inertia` adapts Yii3 request, response, session, and view services to that core.
- [`php-forge/vite`](https://github.com/php-forge/vite) provides framework-agnostic Vite manifest and development
  server support for the initial document.

React and Vue remain application concerns; this adapter does not ship framework-specific JavaScript packages.

## Requirements

- PHP 8.3 or later.
- A Yii3 application with PSR-17 response and stream factories.
- `yiisoft/session` and `yiisoft/csrf` for flash data, validation errors, and the XSRF cookie flow.
- `yiisoft/request-body-parser` for JSON form submissions.
- `yiisoft/view` for rendering the initial HTML document through the application web view.
- `php-forge/inertia` for the framework-neutral Inertia protocol and prop types.
- `php-forge/vite` for framework-neutral asset resolution.

## Installation

Applications should declare the adapter, the native PHP Forge packages they use, and Yii's request body parser as
direct dependencies:

```shell
composer require yii3/inertia:^0.1 php-forge/inertia:^0.2 php-forge/vite:^0.2 yiisoft/request-body-parser:^1.2
```

For a local sibling checkout, add a Composer path repository:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../inertia",
            "options": {
                "symlink": true,
                "reference": "config"
            }
        }
    ],
    "require": {
        "yii3/inertia": "dev-main",
        "php-forge/inertia": "^0.2",
        "php-forge/vite": "^0.2",
        "yiisoft/request-body-parser": "^1.2"
    }
}
```

The Yii Config Plugin merges `config/params.php` and the web-only `config/di-web.php` automatically.

## Middleware order

Place the middleware around the Yii3 web stack in this order:

```php
use Yii3\Inertia\Middleware\CsrfTokenCookieMiddleware;
use Yii3\Inertia\Middleware\InertiaMiddleware;
use Yiisoft\Csrf\CsrfTokenMiddleware;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Request\Body\RequestBodyParser;
use Yiisoft\RequestProvider\RequestCatcherMiddleware;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Session\SessionMiddleware;

return [
    InertiaMiddleware::class,
    ErrorCatcher::class,
    SessionMiddleware::class,
    RequestBodyParser::class,
    CsrfTokenCookieMiddleware::class,
    CsrfTokenMiddleware::class,
    RequestCatcherMiddleware::class,
    Router::class,
];
```

This order ensures that:

- Inertia headers are added to normal and error responses.
- JSON form bodies are available before CSRF validation.
- The session is open when the readable `XSRF-TOKEN` cookie is generated.
- Mutable shared props are reset before and after every request, including failed requests.

The package configures Yii's CSRF validator to accept `X-XSRF-TOKEN`. Do not encrypt or sign the `XSRF-TOKEN`
cookie with `CookieMiddleware`; the browser client must be able to read and return the masked token.

## Configuration

Override the `yii3/inertia` parameter tree in application configuration:

```php
<?php

declare(strict_types=1);

$manifest = dirname(__DIR__, 2) . '/public/build/.vite/manifest.json';

return [
    'yii3/inertia' => [
        'title' => 'My application',
        'version' => static function () use ($manifest): string|null {
            if (!is_file($manifest)) {
                return null;
            }

            $hash = hash_file('xxh128', $manifest);

            return $hash === false ? null : $hash;
        },
        'shared' => [
            'application' => ['name' => 'My application'],
        ],
        'csrf' => [
            // null enables HTTPS auto-detection. Use true only when trusted-proxy
            // middleware does not normalize the request URI scheme.
            'secure' => null,
        ],
    ],
];
```

The full parameter tree contains:

- `id`, `rootView`, `language`, `charset`, and `title` for the initial document.
- `version`, `shared`, and `errorFlashKey` for page construction.
- `csrf.cookieName`, `headerName`, `parameterName`, `path`, `domain`, `secure`, and `sameSite`.

Configurable services keep constructors to at most four dependencies. Yii's DI definitions apply package parameters
through immutable `with*()` methods, so each configured instance is cloned instead of mutated.

The configured root-view alias is resolved with `Yiisoft\Aliases\Aliases` and rendered by the application's
`Yiisoft\View\WebView`. Custom root views therefore use Yii's configured renderers, themes, common parameters, and
render events instead of a package-owned PHP file loader.

## Vite integration

The application owns its Vite mode and entrypoints. Define the native `PHPForge\Vite\Vite` service directly; the
package injects it into `RootViewRenderer`, which renders the resulting assets with the native stateless
`HtmlRenderer`.

Production example:

```php
<?php

declare(strict_types=1);

use PHPForge\Vite\Configuration\ProductionConfiguration;
use PHPForge\Vite\Vite;

return [
    Vite::class => static fn(): Vite => Vite::create(
        ProductionConfiguration::create(
            manifestPath: dirname(__DIR__, 2) . '/public/build/.vite/manifest.json',
            assetBaseUrl: '/build',
        ),
        entrypoints: ['resources/js/app.ts'],
    ),
];
```

During development, replace the production configuration with the native development configuration:

```php
use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use PHPForge\Vite\Vite;

$vite = Vite::create(
    DevelopmentConfiguration::create('http://localhost:5173'),
    entrypoints: ['resources/js/app.ts'],
);
```

React applications may pass an application-owned `InlineModuleProviderInterface` implementation to
`DevelopmentConfiguration::create()` for the React Refresh preamble. Vue applications do not need a preamble.

## Rendering pages

Inject `Yii3\Inertia\Inertia` into an action and return its PSR-7 response. Use the native PHP Forge prop factories;
this package does not duplicate them.

```php
use PHPForge\Inertia\Prop\Prop;
use PHPForge\Inertia\Prop\ScrollMetadata;
use Psr\Http\Message\ResponseInterface;
use Yii3\Inertia\Inertia;

final readonly class DashboardAction
{
    public function __construct(private Inertia $inertia) {}

    public function __invoke(): ResponseInterface
    {
        $this->inertia->share('auth.user', ['id' => 42, 'name' => 'Ada']);

        return $this->inertia->render('Dashboard', [
            'summary' => static fn(): array => ['projects' => 12],
            'activity' => Prop::defer(static fn(): array => loadActivity(), 'dashboard', rescue: true),
            'audit' => Prop::optional(static fn(): array => loadAudit())->once(),
            'permissions' => Prop::always(['projects.read']),
            'users' => Prop::merge(loadUsers())->append('data', matchOn: 'id'),
            'messages' => Prop::merge(loadMessages())->prepend(),
            'settings' => Prop::merge(loadSettings())->deepMerge(),
            'countries' => Prop::once(static fn(): array => loadCountries())
                ->as('country-list')
                ->until(3600),
            'feed' => Prop::scroll(
                loadFeed(),
                new ScrollMetadata('page', previousPage: null, nextPage: 2, currentPage: 1),
            ),
        ]);
    }
}
```

Plain page, shared, and version closures are invoked without arguments, matching the PHP Forge core contract. Resolve
request-dependent values explicitly in the action or capture the request in a zero-argument closure. Scroll metadata
closures are the exception: the core passes them the resolved scroll value.

Page props replace shared props at the top-level key, matching the official adapter behavior. The response exposes the
top-level shared keys through `sharedProps`, allowing Inertia v3 instant visits to retain shared application data.
Session flash data is emitted only in the page-level `flash` field so it cannot replay from browser-history props.

## Public API

`Yii3\Inertia\Inertia` exposes:

- `render()`, `location()`, `isInertiaRequest()`, `getVersion()`, and `normalizeResponse()`.
- `share()`, `getShared()`, `flushShared()`, and `reset()`.
- Immutable `with*()` methods for service configuration.

All page and prop value objects come directly from `php-forge/inertia`. Vite configuration, resolution, and HTML asset
rendering come directly from `php-forge/vite`; there is no Yii-specific Vite facade or metadata wrapper.

Adapter-owned exception text is centralized in `Yii3\Inertia\Exception\Message`. Exceptions from
`php-forge/inertia`, Yii View, and other dependencies retain their native types and messages.

Debug and telemetry packages may implement `ResolvedPageObserverInterface`. The observer receives the resolved core
`Page` synchronously and must keep captured request data request-scoped. Observation is skipped when no implementation
is bound.

See [the protocol notes](docs/protocol.md) for header and payload details.

## Documentation

For detailed configuration options and advanced usage.

- 🧪 [Testing Guide](docs/testing.md)

## Package information

[![PHP](https://img.shields.io/badge/%3E%3D8.3-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)
[![Yii3](https://img.shields.io/badge/Yii-3-0073AA.svg?style=for-the-badge&logo=yii&logoColor=white)](https://www.yiiframework.com/)
[![Inertia.js 3](https://img.shields.io/badge/Inertia.js-3-9553E9.svg?style=for-the-badge&logoColor=white)](https://inertiajs.com/)

## Project status

[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.com/yii3/inertia/actions/workflows/static.yml)
[![Quality](https://img.shields.io/github/actions/workflow/status/yii3/inertia/quality.yml?branch=main&style=for-the-badge&label=Quality&logo=github)](https://github.com/yii3/inertia/actions/workflows/quality.yml)
[![ECS](https://img.shields.io/github/actions/workflow/status/yii3/inertia/ecs.yml?branch=main&style=for-the-badge&label=ECS&logo=github)](https://github.com/yii3/inertia/actions/workflows/ecs.yml)
[![Dependencies](https://img.shields.io/github/actions/workflow/status/yii3/inertia/dependency-check.yml?branch=main&style=for-the-badge&label=Dependencies&logo=github)](https://github.com/yii3/inertia/actions/workflows/dependency-check.yml)

## Community

[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/Terabytesoftw)
[![Yii Forum](https://img.shields.io/badge/Yii-Forum-0073AA.svg?style=for-the-badge&logo=yii&logoColor=white)](https://forum.yiiframework.com/)
[![Join on Telegram](https://img.shields.io/badge/-Join%20on%20Telegram-26A5E4.svg?style=for-the-badge&logo=telegram&logoColor=white&labelColor=000000)](https://t.me/yii_framework_in_english)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE)
