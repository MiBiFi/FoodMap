<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ActionKeypress implements HydratableInterface
{
    public string $type = 'keypress';
    /** @var string[] */
    public array $keys = [];

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->keys = $data['keys'] ?? [];
        return $instance;
    }
}