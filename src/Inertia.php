<?php

declare(strict_types=1);

namespace Yii3\Inertia;

use Closure;
use PHPForge\Inertia\{PageInput, Protocol};
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
 * Constructor-injected Yii adapter for the framework-neutral Inertia protocol.
 */
final class Inertia
{
    private string $charset = 'UTF-8';
    /**
     * @var array<string, mixed>
     */
    private array $configuredShared = [];
    private string $errorFlashKey = 'errors';
    private ResolvedPageObserverInterface|null $pageObserver = null;
    private Protocol $protocol;
    private readonly RequestContextFactory $requestContextFactory;
    private RootViewRenderer|null $rootViewRenderer = null;
    /**
     * @var array<string, mixed>
     */
    private array $shared = [];
    /**
     * @var (Closure(): (int|string|null))|int|string|null
     */
    private Closure|int|string|null $version = null;

    public function __construct(
        private readonly RequestProviderInterface $requestProvider,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly FlashInterface $flash,
    ) {
        $this->protocol = Protocol::create();
        $this->requestContextFactory = new RequestContextFactory();
    }

    public function flushShared(): void
    {
        $this->shared = [];
    }

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

    public function getVersion(): int|string|null
    {
        $version = $this->version;

        if ($version instanceof Closure) {
            $version = $version();
        }

        return is_int($version) || is_string($version) ? $version : null;
    }

    public function isInertiaRequest(ServerRequestInterface|null $request = null): bool
    {
        return $this->requestContextFactory
            ->create($request ?? $this->requestProvider->get())
            ->isInertia();
    }

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
     * Normalizes a downstream response through the core redirect protocol.
     */
    public function normalizeResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $context = $this->requestContextFactory->create($request);

        if (!$context->isInertia()) {
            return $this->mergeVary($response, 'X-Inertia');
        }

        $location = $response->getHeaderLine('Location');

        if ($location === '' || !in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)) {
            return $this->mergeVary($response, 'X-Inertia');
        }

        $result = $this->protocol->redirect($context, $location, $response->getStatusCode());

        if ($result instanceof FragmentRedirectResult) {
            $response = $response
                ->withoutHeader('Location')
                ->withoutHeader('X-Inertia')
                ->withoutHeader('Content-Length')
                ->withBody($this->streamFactory->createStream());
        }

        return $this->applyResult($response, $result);
    }

    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $viewData
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

    public function reset(): void
    {
        $this->shared = $this->configuredShared;
    }

    /**
     * @param array<string, mixed>|string $key
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

    public function withCharset(string $charset): self
    {
        $new = clone $this;
        $new->charset = $charset;

        return $new;
    }

    public function withErrorFlashKey(string $errorFlashKey): self
    {
        $new = clone $this;
        $new->errorFlashKey = $errorFlashKey;

        return $new;
    }

    public function withPageObserver(ResolvedPageObserverInterface|null $pageObserver = null): self
    {
        $new = clone $this;
        $new->pageObserver = $pageObserver;

        return $new;
    }

    public function withProtocol(Protocol $protocol): self
    {
        $new = clone $this;
        $new->protocol = $protocol;

        return $new;
    }

    public function withRootViewRenderer(RootViewRenderer $rootViewRenderer): self
    {
        $new = clone $this;
        $new->rootViewRenderer = $rootViewRenderer;

        return $new;
    }

    /**
     * @param array<string, mixed> $shared
     */
    public function withShared(array $shared): self
    {
        $new = clone $this;
        $new->configuredShared = $shared;
        $new->shared = $shared;

        return $new;
    }

    /**
     * @param (Closure(): (int|string|null))|int|string|null $version
     */
    public function withVersion(Closure|int|string|null $version): self
    {
        $new = clone $this;
        $new->version = $version;

        return $new;
    }

    /**
     * @param array<string, string> $headers
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

    private function applyResult(ResponseInterface $response, ProtocolResult $result): ResponseInterface
    {
        return $this->applyHeaders($response->withStatus($result->statusCode()), $result->headers());
    }

    /**
     * @return array{array<string, mixed>, array<string, mixed>}
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
     * @param array<string, mixed> $viewData
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
