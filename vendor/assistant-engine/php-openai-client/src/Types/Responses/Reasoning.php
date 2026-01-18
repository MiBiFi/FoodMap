<?php

namespace AssistantEngine\OpenAI\Types\Responses;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class Reasoning implements HydratableInterface
{
    /** @var mixed|null Effort associated with the reasoning */
    public $effort = null;

    /** @var string|null A list of summary items (could be objects in a complete implementation) */
    public ?string $generate_summary = null;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->effort = $data['effort'] ?? null;
        $instance->generate_summary = $data['generate_summary'] ?? null;
        return $instance;
    }
}