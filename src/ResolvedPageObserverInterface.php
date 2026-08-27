<?php

declare(strict_types=1);

namespace Yii3\Inertia;

use PHPForge\Inertia\Page;

/**
 * Observes a page immediately after the protocol core resolves it.
 */
interface ResolvedPageObserverInterface
{
    public function observe(Page $page): void;
}
