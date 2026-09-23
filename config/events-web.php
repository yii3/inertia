<?php

declare(strict_types=1);

use PHPForge\Inertia\Debug\InertiaCollector;
use PHPForge\Inertia\Event\ProtocolResultCreated;

// Routes protocol results to the Inertia collector `yii3/debug` reads; it only buffers until the debugger starts it.
return [
    ProtocolResultCreated::class => [InertiaCollector::class],
];
