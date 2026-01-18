<?php

namespace AssistantEngine\OpenAI\Types\Responses;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ResponseTextConfig implements HydratableInterface
{
    /** @var array|null Configuration for text formatting (could be expanded into its own class) */
    public ?array $format;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->format = $data['format'] ?? null;
        return $instance;
    }
}