<?php

declare(strict_types=1);

namespace Yii3\Inertia\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Yii3\Inertia\Inertia;

/**
 * Applies protocol-wide response normalization and isolates mutable request state.
 */
final readonly class InertiaMiddleware implements MiddlewareInterface
{
    /**
     * Creates a new instance.
     *
     * @param Inertia $inertia Adapter whose request state is isolated and whose responses are normalized.
     */
    public function __construct(private Inertia $inertia) {}

    /**
     * Restores the configured shared props around the inner handler and normalizes the response it returns.
     *
     * @param ServerRequestInterface $request Request passed to the inner handler.
     * @param RequestHandlerInterface $handler Inner handler producing the response.
     *
     * @return ResponseInterface Response carrying the protocol status code and headers.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->inertia->reset();

        try {
            return $this->inertia->normalizeResponse(
                $request,
                $handler->handle($request),
            );
        } finally {
            $this->inertia->reset();
        }
    }
}
