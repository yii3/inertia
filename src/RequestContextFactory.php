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
    public function absoluteUrl(ServerRequestInterface $request, string $url): string
    {
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }

        $context = $this->create($request);

        $origin = substr($context->absoluteUrl, 0, -strlen($context->url));

        return "{$origin}{$url}";
    }

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
