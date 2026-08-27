<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Support;

use PHPForge\Inertia\Page;
use Yii3\Inertia\ResolvedPageObserverInterface;

final class RecordingPageObserver implements ResolvedPageObserverInterface
{
    /**
     * @var list<Page>
     */
    public array $pages = [];

    public function observe(Page $page): void
    {
        $this->pages[] = $page;
    }
}
