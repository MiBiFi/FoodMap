<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ActionType implements HydratableInterface
{
    public string $type = 'type';
    public string $text;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->text = $data['text'] ?? '';
        return $instance;
    }
}