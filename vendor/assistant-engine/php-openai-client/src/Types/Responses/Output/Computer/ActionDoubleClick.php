<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ActionDoubleClick implements HydratableInterface
{
    public string $type = 'double_click';
    public int $x;
    public int $y;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->x = isset($data['x']) ? (int)$data['x'] : 0;
        $instance->y = isset($data['y']) ? (int)$data['y'] : 0;
        return $instance;
    }
}