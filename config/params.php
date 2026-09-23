<?php

declare(strict_types=1);

use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use Yiisoft\Cookies\Cookie;

return [
    'yii3/inertia' => [
        'id' => 'app',
        'rootView' => '@yii3InertiaViews/app.php',
        'language' => 'en',
        'charset' => 'UTF-8',
        'title' => 'Yii3 Inertia',
        'version' => null,
        'shared' => [],
        'errorFlashKey' => 'errors',
        'csrf' => [
            'cookieName' => 'XSRF-TOKEN',
            'headerName' => 'X-XSRF-TOKEN',
            'parameterName' => '_csrf',
            'path' => '/',
            'domain' => null,
            'secure' => null,
            'sameSite' => Cookie::SAME_SITE_LAX,
        ],
    ],
    // Registers the Inertia panel with `yii3/debug`; inert when the debugger is not installed.
    'yii3/debug' => [
        'collectors' => ['inertia' => InertiaCollector::class],
        'panels' => ['inertia' => InertiaPanel::class],
    ],
    'yiisoft/aliases' => [
        'aliases' => [
            '@yii3InertiaViews' => dirname(__DIR__) . '/resources/views',
        ],
    ],
];
