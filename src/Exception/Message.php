<?php

declare(strict_types=1);

namespace Yii3\Inertia\Exception;

use function sprintf;

/**
 * Defines adapter exception message templates.
 *
 * Use {@see Message::getMessage()} to format a template with `sprintf()` arguments.
 */
enum Message: string
{
    /**
     * The initial page cannot be rendered without a root-view renderer.
     *
     * Format: "The Inertia root view renderer is not configured."
     */
    case ROOT_VIEW_RENDERER_NOT_CONFIGURED = 'The Inertia root view renderer is not configured.';

    /**
     * Formats the message template with the supplied arguments.
     *
     * @param int|string ...$argument Values inserted into the template.
     *
     * @return string Formatted exception message.
     */
    public function getMessage(int|string ...$argument): string
    {
        return sprintf($this->value, ...$argument);
    }
}
