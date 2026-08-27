<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Provider;

/**
 * Data provider for {@see \Yii3\Inertia\Tests\InertiaCoreIntegrationTest} test cases.
 */
final class InertiaCoreIntegrationProvider
{
    /**
     * @return array<string, array{string}>
     */
    public static function invalidSharedPaths(): array
    {
        return [
            'comma' => ['auth,user'],
            'control character' => ["auth\nuser"],
            'empty path' => [''],
            'empty segment' => ['auth..user'],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function redirectStatuses(): array
    {
        return [
            'found redirect' => [302],
            'permanent method-preserving redirect' => [308],
            'permanent redirect' => [301],
            'see-other redirect' => [303],
            'temporary redirect' => [307],
        ];
    }
}
