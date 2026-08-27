<?php

declare(strict_types=1);

namespace Yii3\Inertia\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Yiisoft\Cookies\Cookie;
use Yiisoft\Csrf\CsrfTokenInterface;

use function strtolower;

/**
 * Publishes Yii's masked CSRF token through Inertia's readable XSRF cookie.
 */
final class CsrfTokenCookieMiddleware implements MiddlewareInterface
{
    private string $cookieName = 'XSRF-TOKEN';
    private string|null $domain = null;
    private string $path = '/';
    private string $sameSite = Cookie::SAME_SITE_LAX;
    private bool|null $secure = null;

    public function __construct(private readonly CsrfTokenInterface $token) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $secure = $this->secure ?? strtolower($request->getUri()->getScheme()) === 'https';

        $cookie = new Cookie(
            name: $this->cookieName,
            value: $this->token->getValue(),
            domain: $this->domain,
            path: $this->path,
            secure: $secure,
            httpOnly: false,
            sameSite: $this->sameSite,
        );

        return $cookie->addToResponse($response);
    }

    public function withCookieName(string $cookieName): self
    {
        $new = clone $this;
        $new->cookieName = $cookieName;

        return $new;
    }

    public function withDomain(string|null $domain): self
    {
        $new = clone $this;
        $new->domain = $domain;

        return $new;
    }

    public function withPath(string $path): self
    {
        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    public function withSameSite(string $sameSite): self
    {
        $new = clone $this;
        $new->sameSite = $sameSite;

        return $new;
    }

    public function withSecure(bool|null $secure): self
    {
        $new = clone $this;
        $new->secure = $secure;

        return $new;
    }
}
