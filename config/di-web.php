<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Yii3\Inertia\{Inertia, RootViewRenderer};
use Yii3\Inertia\Middleware\CsrfTokenCookieMiddleware;
use Yiisoft\Csrf\{CsrfTokenInterface, CsrfTokenMiddleware};

/**
 * @var array<string, mixed> $params
 */
$config = $params['yii3/inertia'];
$csrf = $config['csrf'];

return [
    RootViewRenderer::class => [
        'withRootView()' => ['rootView' => $config['rootView']],
        'withId()' => ['id' => $config['id']],
        'withLanguage()' => ['language' => $config['language']],
        'withCharset()' => ['charset' => $config['charset']],
        'withTitle()' => ['title' => $config['title']],
    ],
    Inertia::class => [
        'withRootViewRenderer()' => [],
        'withShared()' => ['shared' => $config['shared']],
        'withVersion()' => ['version' => $config['version']],
        'withErrorFlashKey()' => ['errorFlashKey' => $config['errorFlashKey']],
        'withCharset()' => ['charset' => $config['charset']],
        'withProtocol()' => [],
        'withPageObserver()' => [],
    ],
    CsrfTokenCookieMiddleware::class => [
        'withCookieName()' => ['cookieName' => $csrf['cookieName']],
        'withPath()' => ['path' => $csrf['path']],
        'withDomain()' => ['domain' => $csrf['domain']],
        'withSecure()' => ['secure' => $csrf['secure']],
        'withSameSite()' => ['sameSite' => $csrf['sameSite']],
    ],
    CsrfTokenMiddleware::class => static fn(
        ResponseFactoryInterface $responseFactory,
        CsrfTokenInterface $token,
    ): CsrfTokenMiddleware => (new CsrfTokenMiddleware($responseFactory, $token))
        ->withHeaderName($csrf['headerName'])
        ->withParameterName($csrf['parameterName']),
];
