<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Support;

use Yiisoft\Session\Flash\FlashInterface;

use function array_key_exists;
use function is_array;

final class FakeFlash implements FlashInterface
{
    public int $getAllCalls = 0;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values = []) {}

    public function add(string $key, mixed $value = true, bool $removeAfterAccess = true): void
    {
        $current = $this->values[$key] ?? [];

        $current = is_array($current) ? $current : [$current];

        $current[] = $value;
        $this->values[$key] = $current;
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $this->getAllCalls++;

        return $this->values;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function removeAll(): void
    {
        $this->values = [];
    }

    public function set(string $key, mixed $value = true, bool $removeAfterAccess = true): void
    {
        $this->values[$key] = $value;
    }
}
