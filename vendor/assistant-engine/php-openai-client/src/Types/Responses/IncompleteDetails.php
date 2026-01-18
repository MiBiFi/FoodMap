<?php

namespace AssistantEngine\OpenAI\Types\Responses;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class IncompleteDetails implements HydratableInterface
{
    public ?string $reason;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->reason = $data['reason'] ?? null;
        return $instance;
    }
}