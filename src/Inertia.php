<?php

declare(strict_types=1);

namespace Yii3\Inertia;

use Closure;
use PHPForge\Inertia\Exception\InvalidPropException;
use PHPForge\Inertia\{Header, PageInput, Protocol};
use PHPForge\Inertia\Prop\{
    AlwaysProp,
    DeferredProp,
    MergeProp,
    OnceProp,
    OptionalProp,
    Prop,
    ScrollMetadata,
    ScrollProp,
};
use PHPForge\Inertia\Result\{
    FragmentRedirectResult,
    InertiaPageResult,
    InitialPageResult,
    PageResult,
    ProtocolResult,
    VersionConflictResult
};
use PHPForge\Inertia\Support\DotArray;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Inertia\Exception\{ConfigurationException, Message};
use Yiisoft\Json\Json;
use Yiisoft\RequestProvider\RequestProviderInterface;
use Yiisoft\Session\Flash\FlashInterface;

use function array_key_exists;
use function explode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function strtolower;
use function trim;

/**
 * Constructor-injected Yii adapter for the framework-neutral Inertia protocol, including the prop factories a page
 * needs, so an action names no core class.
 */
final class Inertia
{
    /**
     * Character set appended to the `Content-Type` header of rendered responses.
     */
    private string $charset = 'UTF-8';

    /**
     * Shared props configured at build time and restored on every reset.
     *
     * @var array<string, mixed>
     */
    private array $configuredShared = [];

    /**
     * Flash key holding the validation errors exposed to the page as the `errors` prop.
     */
    private string $errorFlashKey = 'errors';

    /**
     * Observer notified with every resolved page, or `null` when no observer is registered.
     */
    private ResolvedPageObserverInterface|null $pageObserver = null;

    /**
     * Framework-neutral protocol core deciding page, redirect, and location results.
     */
    private Protocol $protocol;

    /**
     * Factory translating PSR-7 server requests into protocol request contexts.
     */
    private readonly RequestContextFactory $requestContextFactory;

    /**
     * Renderer of the initial HTML document, or `null` until one is configured.
     */
    private RootViewRenderer|null $rootViewRenderer = null;

    /**
     * Shared props of the current request, discarded when the request ends.
     *
     * @var array<string, mixed>
     */
    private array $shared = [];

    /**
     * Asset version compared against the client version, as a literal value or a closure resolving one.
     *
     * @var (Closure(): (int|string|null))|int|string|null
     */
    private Closure|int|string|null $version = null;

    /**
     * Creates a new instance.
     *
     * @param RequestProviderInterface $requestProvider Provider of the server request being handled.
     * @param ResponseFactoryInterface $responseFactory Factory creating the responses returned to the client.
     * @param StreamFactoryInterface $streamFactory Factory creating the bodies of the rendered responses.
     * @param FlashInterface $flash Session flash storage consumed as the `errors` and `flash` page props.
     */
    public function __construct(
        private readonly RequestProviderInterface $requestProvider,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly FlashInterface $flash,
    ) {
        $this->protocol = Protocol::create();
        $this->requestContextFactory = new RequestContextFactory();
    }

    /**
     * Creates a prop that is always included, bypassing partial-reload filtering.
     */
    public function always(mixed $value): AlwaysProp
    {
        return Prop::always($value);
    }

    /**
     * Creates a prop that deep-merges with the existing client-side data during partial reloads.
     */
    public function deepMerge(mixed $value): MergeProp
    {
        return Prop::merge($value)->deepMerge();
    }

    /**
     * Creates a prop whose evaluation is postponed until the client requests it.
     *
     * @param (Closure(): mixed) $callback Closure resolved when the client requests the prop.
     * @param string $group Group name batching deferred requests.
     * @param bool $rescue Whether a failing callback is rescued and reported as page metadata.
     */
    public function defer(Closure $callback, string $group = 'default', bool $rescue = false): DeferredProp
    {
        return Prop::defer($callback, $group, $rescue);
    }

    /**
     * Removes every shared prop registered for the current request.
     */
    public function flushShared(): void
    {
        $this->shared = [];
    }

    /**
     * Returns a shared prop addressed by a dot-notated key, or every shared prop when no key is given.
     *
     * @param string|null $key Dot-notated path of the shared prop, or `null` to return all shared props.
     * @param mixed $default Value returned when the path is not registered.
     *
     * @return mixed Shared prop value, the expanded shared props when `$key` is `null`, or `$default` when the path
     * is missing.
     */
    public function getShared(string|null $key = null, mixed $default = null): mixed
    {
        $shared = DotArray::expand($this->shared);

        if ($key === null) {
            return $shared;
        }

        $value = $shared;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Returns the asset version sent to the client, evaluating the configured closure when needed.
     *
     * @return int|string|null Asset version, or `null` when unset or resolved to an unsupported value.
     */
    public function getVersion(): int|string|null
    {
        $version = $this->version;

        if ($version instanceof Closure) {
            $version = $version();
        }

        return is_int($version) || is_string($version) ? $version : null;
    }

    /**
     * Checks whether a request carries the Inertia handshake header.
     *
     * @param ServerRequestInterface|null $request Request to inspect, or `null` to inspect the current request.
     *
     * @return bool `true` when the request is an Inertia visit, `false` otherwise.
     */
    public function isInertiaRequest(ServerRequestInterface|null $request = null): bool
    {
        return $this->requestContextFactory
            ->create($request ?? $this->requestProvider->get())
            ->isInertia();
    }

    /**
     * Sends the client to an external URL through the Inertia location protocol.
     *
     * @param string $url Target URL; a root-relative path is expanded against the current request origin.
     *
     * @throws \InvalidArgumentException when the resolved URL is not a valid absolute HTTP or HTTPS URL.
     *
     * @return ResponseInterface Location response for Inertia visits, or a plain redirect response otherwise.
     */
    public function location(string $url): ResponseInterface
    {
        $request = $this->requestProvider->get();
        $context = $this->requestContextFactory->create($request);
        $result = $this->protocol->location(
            $context,
            $this->requestContextFactory->absoluteUrl($request, $url),
        );

        return $this->responseFromResult($result);
    }

    /**
     * Creates a prop that merges with the existing client-side data during partial reloads instead of replacing it.
     */
    public function merge(mixed $value): MergeProp
    {
        return Prop::merge($value);
    }

    /**
     * Normalizes a downstream response through the core redirect protocol.
     *
     * Advertises the Inertia header in `Vary` on every response, rewrites redirects into the status code and headers
     * the client expects, and empties the body of a fragment redirect.
     *
     * @param ServerRequestInterface $request Request the response was produced for.
     * @param ResponseInterface $response Response returned by the downstream handler.
     *
     * @throws \InvalidArgumentException when the `Location` header is not a valid redirect target.
     *
     * @return ResponseInterface Response carrying the protocol status code and headers.
     */
    public function normalizeResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $context = $this->requestContextFactory->create($request);

        if (!$context->isInertia()) {
            return $this->mergeVary($response, Header::INERTIA->value);
        }

        $location = $response->getHeaderLine('Location');

        if ($location === '' || !in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)) {
            return $this->mergeVary($response, Header::INERTIA->value);
        }

        $result = $this->protocol->redirect($context, $location, $response->getStatusCode());

        if ($result instanceof FragmentRedirectResult) {
            $response = $response
                ->withoutHeader('Location')
                ->withoutHeader(Header::INERTIA->value)
                ->withoutHeader('Content-Length')
                ->withBody($this->streamFactory->createStream());
        }

        return $this->applyResult($response, $result);
    }

    /**
     * Creates a prop the client may retain and omit from subsequent requests.
     *
     * @param (Closure(): mixed) $callback Closure resolved when the prop is not already available to the client.
     */
    public function once(Closure $callback): OnceProp
    {
        return Prop::once($callback);
    }

    /**
     * Creates a prop resolved only when a partial reload explicitly requests it.
     *
     * @param (Closure(): mixed) $callback Closure resolved when the client requests the prop.
     */
    public function optional(Closure $callback): OptionalProp
    {
        return Prop::optional($callback);
    }

    /**
     * Renders a page as the Inertia JSON payload, or as the initial HTML document on a first visit.
     *
     * @param string $component Name of the client-side component resolved by the client adapter.
     * @param array<string, mixed> $props Page props, merged over the shared props of the current request.
     * @param array<string, mixed> $viewData Extra variables exposed to the root view on the initial render.
     *
     * @throws ConfigurationException when the initial HTML render runs without a configured root view renderer.
     * @throws \JsonException when the page cannot be encoded as JSON.
     *
     * @return ResponseInterface Page response, or a version-conflict response when the client asset version differs.
     */
    public function render(string $component, array $props = [], array $viewData = []): ResponseInterface
    {
        $request = $this->requestProvider->get();
        $context = $this->requestContextFactory->create($request);
        $version = $this->getVersion() ?? '';
        $probe = $this->protocol->page(
            $context,
            PageInput::create($component, [], $version),
        );

        if ($probe instanceof VersionConflictResult) {
            return $this->responseFromResult($probe);
        }

        [$errors, $flash] = $this->consumeFlashes();

        $input = PageInput::create($component, $props, $version)
            ->withSharedProps($this->shared)
            ->withErrors($errors)
            ->withFlash($flash);

        $result = $this->protocol->page($context, $input);

        if ($result instanceof PageResult) {
            $this->pageObserver?->observe($result->page());
        }

        return $this->responseFromResult($result, $viewData);
    }

    /**
     * Restores the shared props to the configured set, discarding the props added during the request.
     */
    public function reset(): void
    {
        $this->shared = $this->configuredShared;
    }

    /**
     * Creates a prop carrying one page of an infinite list plus its pagination metadata.
     *
     * @param mixed $value Paginated data exposed as the prop value.
     * @param (Closure(mixed): mixed)|ScrollMetadata $metadata Pagination metadata, or a callback receiving the
     * resolved value.
     * @param string $wrapper Dot-notated merge path within the prop value.
     */
    public function scroll(mixed $value, ScrollMetadata|Closure $metadata, string $wrapper = 'data'): ScrollProp
    {
        return Prop::scroll($value, $metadata, $wrapper);
    }

    /**
     * Creates the pagination metadata of a scroll prop.
     *
     * @param string $pageName Query parameter carrying the page cursor.
     * @param int|string|null $previousPage Cursor of the previous page, or `null` on the first page.
     * @param int|string|null $nextPage Cursor of the next page, or `null` on the last page.
     * @param int|string|null $currentPage Cursor of the current page, or `null` when unknown.
     *
     * @throws InvalidPropException when `$pageName` is empty or contains control characters.
     */
    public function scrollMetadata(
        string $pageName,
        int|string|null $previousPage = null,
        int|string|null $nextPage = null,
        int|string|null $currentPage = null,
    ): ScrollMetadata {
        return new ScrollMetadata($pageName, $previousPage, $nextPage, $currentPage);
    }

    /**
     * Registers a shared prop, or a map of shared props, for every page rendered during the request.
     *
     * @param array<string, mixed>|string $key Dot-notated prop path, or a map of paths to values.
     * @param mixed $value Prop value; ignored when `$key` is an `array`.
     */
    public function share(array|string $key, mixed $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $path => $item) {
                $this->shared[$path] = $item;
            }

            return;
        }

        $this->shared[$key] = $value;
    }

    /**
     * Returns a new instance with the specified character set.
     *
     * @param string $charset Character set appended to the `Content-Type` header of rendered responses.
     *
     * @return self New instance with the specified character set.
     */
    public function withCharset(string $charset): self
    {
        $new = clone $this;
        $new->charset = $charset;

        return $new;
    }

    /**
     * Returns a new instance with the specified error flash key.
     *
     * @param string $errorFlashKey Flash key holding the validation errors exposed as the `errors` prop.
     *
     * @return self New instance with the specified error flash key.
     */
    public function withErrorFlashKey(string $errorFlashKey): self
    {
        $new = clone $this;
        $new->errorFlashKey = $errorFlashKey;

        return $new;
    }

    /**
     * Returns a new instance with the specified page observer.
     *
     * @param ResolvedPageObserverInterface|null $pageObserver Observer notified with every resolved page, or `null`
     * to remove the current observer.
     *
     * @return self New instance with the specified page observer.
     */
    public function withPageObserver(ResolvedPageObserverInterface|null $pageObserver = null): self
    {
        $new = clone $this;
        $new->pageObserver = $pageObserver;

        return $new;
    }

    /**
     * Returns a new instance with the specified protocol core.
     *
     * @param Protocol $protocol Protocol core deciding page, redirect, and location results.
     *
     * @return self New instance with the specified protocol core.
     */
    public function withProtocol(Protocol $protocol): self
    {
        $new = clone $this;
        $new->protocol = $protocol;

        return $new;
    }

    /**
     * Returns a new instance with the specified root view renderer.
     *
     * @param RootViewRenderer $rootViewRenderer Renderer producing the initial HTML document.
     *
     * @return self New instance with the specified root view renderer.
     */
    public function withRootViewRenderer(RootViewRenderer $rootViewRenderer): self
    {
        $new = clone $this;
        $new->rootViewRenderer = $rootViewRenderer;

        return $new;
    }

    /**
     * Returns a new instance with the specified shared props, applied to every request.
     *
     * @param array<string, mixed> $shared Shared props restored on every reset.
     *
     * @return self New instance with the specified shared props.
     */
    public function withShared(array $shared): self
    {
        $new = clone $this;
        $new->configuredShared = $shared;
        $new->shared = $shared;

        return $new;
    }

    /**
     * Returns a new instance with the specified asset version.
     *
     * @param (Closure(): (int|string|null))|int|string|null $version Asset version compared against the client
     * version, as a literal value or a closure resolving one.
     *
     * @return self New instance with the specified asset version.
     */
    public function withVersion(Closure|int|string|null $version): self
    {
        $new = clone $this;
        $new->version = $version;

        return $new;
    }

    /**
     * Applies protocol headers to a response, merging `Vary` instead of overwriting it.
     *
     * @param ResponseInterface $response Response to decorate.
     * @param array<string, string> $headers Protocol headers to apply, keyed by header name.
     *
     * @return ResponseInterface Response carrying the applied headers.
     */
    private function applyHeaders(ResponseInterface $response, array $headers): ResponseInterface
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'vary') {
                $response = $this->mergeVary($response, $value);
            } else {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Applies the status code and headers of a protocol result to an existing response.
     *
     * @param ResponseInterface $response Response to decorate.
     * @param ProtocolResult $result Protocol result supplying the status code and headers.
     *
     * @return ResponseInterface Response carrying the status code and headers of the result.
     */
    private function applyResult(ResponseInterface $response, ProtocolResult $result): ResponseInterface
    {
        return $this->applyHeaders($response->withStatus($result->statusCode()), $result->headers());
    }

    /**
     * Reads the session flash messages, separating the error entry from the remaining ones.
     *
     * @return array{array<string, mixed>, array<string, mixed>} Validation errors keyed by field name, followed by
     * the remaining flash messages.
     */
    private function consumeFlashes(): array
    {
        $flashes = [];

        foreach ($this->flash->getAll() as $key => $value) {
            if (is_string($key)) {
                $flashes[$key] = $value;
            }
        }

        $errors = [];

        if (array_key_exists($this->errorFlashKey, $flashes)) {
            $errorValue = $flashes[$this->errorFlashKey];

            if (is_array($errorValue)) {
                foreach ($errorValue as $key => $value) {
                    if (is_string($key)) {
                        $errors[$key] = $value;
                    }
                }
            } else {
                $errors['message'] = $errorValue;
            }

            unset($flashes[$this->errorFlashKey]);
        }

        return [$errors, $flashes];
    }

    /**
     * Merges header names into the `Vary` header, keeping the existing tokens and dropping duplicates.
     *
     * @param ResponseInterface $response Response whose `Vary` header is extended.
     * @param string $value Comma-separated header names to add.
     *
     * @return ResponseInterface Response carrying the merged `Vary` header.
     */
    private function mergeVary(ResponseInterface $response, string $value): ResponseInterface
    {
        $values = [];

        foreach ([$response->getHeaderLine('Vary'), $value] as $header) {
            foreach (explode(',', $header) as $token) {
                $token = trim($token);

                if ($token !== '' && !isset($values[strtolower($token)])) {
                    $values[strtolower($token)] = $token;
                }
            }
        }

        return $response->withHeader('Vary', implode(', ', $values));
    }

    /**
     * Builds a response from a protocol result, rendering the JSON payload or the initial HTML document.
     *
     * @param ProtocolResult $result Protocol result supplying the status code, headers, and page.
     * @param array<string, mixed> $viewData Extra variables exposed to the root view on the initial render.
     *
     * @throws ConfigurationException when an initial page render runs without a configured root view renderer.
     * @throws \JsonException when the page cannot be encoded as JSON.
     *
     * @return ResponseInterface Response carrying the status code, headers, and body of the result.
     */
    private function responseFromResult(ProtocolResult $result, array $viewData = []): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($result->statusCode())
            ->withBody($this->streamFactory->createStream());
        $response = $this->applyHeaders($response, $result->headers());

        if ($result instanceof InertiaPageResult) {
            return $response
                ->withHeader('Content-Type', 'application/json; charset=' . $this->charset)
                ->withBody($this->streamFactory->createStream(Json::encode($result->page())));
        }

        if ($result instanceof InitialPageResult) {
            $rootViewRenderer = $this->rootViewRenderer ?? throw new ConfigurationException(
                Message::ROOT_VIEW_RENDERER_NOT_CONFIGURED->getMessage(),
            );

            return $response
                ->withHeader('Content-Type', 'text/html; charset=' . $this->charset)
                ->withBody(
                    $this->streamFactory->createStream(
                        $rootViewRenderer->render($result->page(), $viewData),
                    ),
                );
        }

        return $response;
    }
}
