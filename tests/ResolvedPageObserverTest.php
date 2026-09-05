<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests;

use PHPForge\Inertia\Page;
use PHPUnit\Framework\TestCase;
use Yii3\Inertia\ResolvedPageObserver;

/**
 * Verifies compatibility between the portable observer and Yii3's existing contract.
 */
final class ResolvedPageObserverTest extends TestCase
{
    public function testForwardsTheCorePageThroughTheExistingContract(): void
    {
        $observed = [];

        $observer = new ResolvedPageObserver(
            static function (array $payload, array $sharedKeys) use (&$observed): void {
                $observed = [$payload, $sharedKeys];
            },
        );

        $page = new Page('Home', ['answer' => 42, 'errors' => ['email' => 'Invalid']], '/', 'v1');

        $observer->observe($page);

        self::assertEquals(
            [$page->toArray(), []],
            $observed,
            'The adapter must delegate serialization to the portable observer.',
        );
    }
}
