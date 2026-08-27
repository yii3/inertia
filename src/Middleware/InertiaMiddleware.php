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
    public function __construct(private Inertia $inertia) {}

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
