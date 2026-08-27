<?php

declare(strict_types=1);

namespace Yii3\Inertia\Tests\Provider;

use Yii3\Inertia\Exception\Message;

/**
 * Data provider for {@see \Yii3\Inertia\Tests\Exception\MessageTest} test cases.
 */
final class MessageProvider
{
    /**
     * @return array<string, array{Message, string}>
     */
    public static function messages(): array
    {
        return [
            'root view renderer configuration failure' => [
                Message::ROOT_VIEW_RENDERER_NOT_CONFIGURED,
                'The Inertia root view renderer is not configured.',
            ],
        ];
    }
}
