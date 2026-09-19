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
    /**
     * Name of the cookie carrying the masked CSRF token.
     */
    private string $cookieName = 'XSRF-TOKEN';

    /**
     * Domain the cookie is issued for, or `null` to scope it to the current host.
     */
    private string|null $domain = null;

    /**
     * Path the cookie is scoped to.
     */
    private string $path = '/';

    /**
     * `SameSite` policy applied to the cookie.
     */
    private string $sameSite = Cookie::SAME_SITE_LAX;

    /**
     * Whether the cookie is restricted to HTTPS, or `null` to follow the request scheme.
     */
    private bool|null $secure = null;

    /**
     * Creates a new instance.
     *
     * @param CsrfTokenInterface $token Source of the masked CSRF token published to the client.
     */
    public function __construct(private readonly CsrfTokenInterface $token) {}

    /**
     * Adds the masked CSRF token to the response as a cookie the client reads to sign later requests.
     *
     * @param ServerRequestInterface $request Request supplying the scheme used when the `secure` flag is undecided.
     * @param RequestHandlerInterface $handler Inner handler producing the response.
     *
     * @return ResponseInterface Response carrying the CSRF token cookie.
     */
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

    /**
     * Returns a new instance with the specified cookie name.
     *
     * @param string $cookieName Name of the cookie read by the client.
     *
     * @return self New instance with the specified cookie name.
     */
    public function withCookieName(string $cookieName): self
    {
        $new = clone $this;
        $new->cookieName = $cookieName;

        return $new;
    }

    /**
     * Returns a new instance with the specified cookie domain.
     *
     * @param string|null $domain Domain the cookie is issued for, or `null` to scope it to the current host.
     *
     * @return self New instance with the specified cookie domain.
     */
    public function withDomain(string|null $domain): self
    {
        $new = clone $this;
        $new->domain = $domain;

        return $new;
    }

    /**
     * Returns a new instance with the specified cookie path.
     *
     * @param string $path Path the cookie is scoped to.
     *
     * @return self New instance with the specified cookie path.
     */
    public function withPath(string $path): self
    {
        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    /**
     * Returns a new instance with the specified `SameSite` policy.
     *
     * @param string $sameSite `SameSite` policy applied to the cookie, such as {@see Cookie::SAME_SITE_LAX}.
     *
     * @return self New instance with the specified `SameSite` policy.
     */
    public function withSameSite(string $sameSite): self
    {
        $new = clone $this;
        $new->sameSite = $sameSite;

        return $new;
    }

    /**
     * Returns a new instance with the specified `secure` flag.
     *
     * @param bool|null $secure Whether the cookie is restricted to HTTPS, or `null` to follow the request scheme.
     *
     * @return self New instance with the specified `secure` flag.
     */
    public function withSecure(bool|null $secure): self
    {
        $new = clone $this;
        $new->secure = $secure;

        return $new;
    }
}
