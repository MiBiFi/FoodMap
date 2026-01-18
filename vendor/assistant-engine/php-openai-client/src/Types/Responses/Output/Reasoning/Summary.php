<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Reasoning;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

/**
 * Class Summary
 *
 * A short summary of the reasoning used by the model when generating the response.
 */
class Summary implements HydratableInterface
{
    /** @var string The short summary text. */
    public string $text;

    /** @var string The type of the object. Always "summary_text". */
    public string $type = 'summary_text';

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->text = $data['text'] ?? '';
        return $instance;
    }
}