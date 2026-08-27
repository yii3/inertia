<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Support;

use Yiisoft\Csrf\CsrfTokenInterface;

final readonly class FakeCsrfToken implements CsrfTokenInterface
{
    public function __construct(private string $value = 'csrf-token') {}

    public function getValue(): string
    {
        return $this->value;
    }

    public function validate(string $token): bool
    {
        return $token === $this->value;
    }
}
