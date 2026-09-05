<?php

declare(strict_types=1);

namespace Yii3\Inertia;

use PHPForge\Inertia\ResolvedPageObserver as CoreResolvedPageObserver;

/**
 * Adapts the portable page observer to the existing Yii3 observer contract.
 */
final readonly class ResolvedPageObserver extends CoreResolvedPageObserver implements ResolvedPageObserverInterface {}
