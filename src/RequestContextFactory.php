<?php

declare(strict_types=1);

namespace Yii3\Inertia;

use PHPForge\Inertia\RequestContext;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Adapts PSR-7 server requests to validated protocol request contexts.
 */
final readonly class RequestContextFactory
{
    /**
     * Expands a root-relative path into an absolute URL built from the request origin.
     *
     * @param ServerRequestInterface $request Request supplying the scheme and authority of the origin.
     * @param string $url URL to expand; absolute and protocol-relative URLs are returned unchanged.
     *
     * @return string Absolute URL, or the original URL when no expansion applies.
     */
    public function absoluteUrl(ServerRequestInterface $request, string $url): string
    {
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }

        $context = $this->create($request);

        $origin = substr($context->absoluteUrl, 0, -strlen($context->url));

        return "{$origin}{$url}";
    }

    /**
     * Creates a protocol request context from a PSR-7 server request.
     *
     * @param ServerRequestInterface $request Request supplying the method, URI, and headers.
     *
     * @return RequestContext Context carrying the method, relative URL, absolute URL, and flattened headers.
     */
    public function create(ServerRequestInterface $request): RequestContext
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $value) {
            $headers[$name] = is_string($name) ? $request->getHeaderLine($name) : $value;
        }

        $uri = $request->getUri();
        $path = $uri->getPath() === '' ? '/' : $uri->getPath();
        $query = $uri->getQuery();
        $url = $query === '' ? $path : $path . '?' . $query;
        $scheme = $uri->getScheme() === '' ? 'http' : $uri->getScheme();
        $authority = $uri->getAuthority() === '' ? $request->getHeaderLine('Host') : $uri->getAuthority();

        return new RequestContext(
            method: $request->getMethod(),
            url: $url,
            absoluteUrl: "{$scheme}://{$authority}{$url}",
            headers: $headers,
        );
    }
}
