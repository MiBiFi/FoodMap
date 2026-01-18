<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ActionScroll implements HydratableInterface
{
    public string $type = 'scroll';
    public int $scroll_x;
    public int $scroll_y;
    public int $x;
    public int $y;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->scroll_x = isset($data['scroll_x']) ? (int)$data['scroll_x'] : 0;
        $instance->scroll_y = isset($data['scroll_y']) ? (int)$data['scroll_y'] : 0;
        $instance->x = isset($data['x']) ? (int)$data['x'] : 0;
        $instance->y = isset($data['y']) ? (int)$data['y'] : 0;
        return $instance;
    }
}