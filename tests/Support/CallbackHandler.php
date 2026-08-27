<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Support;

use Closure;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CallbackHandler implements RequestHandlerInterface
{
    /**
     * @var Closure(ServerRequestInterface): ResponseInterface
     */
    private Closure $callback;

    /**
     * @param callable(ServerRequestInterface): ResponseInterface $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = Closure::fromCallable($callback);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->callback)($request);
    }
}
