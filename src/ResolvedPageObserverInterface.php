<?php

declare(strict_types=1);

namespace Yii3\Inertia;

use PHPForge\Inertia\Page;

/**
 * Observes a page immediately after the protocol core resolves it.
 */
interface ResolvedPageObserverInterface
{
    /**
     * Receives the page resolved by the protocol core, before the response is created.
     *
     * @param Page $page Resolved page carrying the component name, props, URL, and asset version.
     */
    public function observe(Page $page): void;
}
