<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ActionClick implements HydratableInterface
{
    // Allowed values for button: "left", "right", "wheel", "back", "forward"
    public const BUTTON_LEFT = 'left';
    public const BUTTON_RIGHT = 'right';
    public const BUTTON_WHEEL = 'wheel';
    public const BUTTON_BACK = 'back';
    public const BUTTON_FORWARD = 'forward';

    public string $type = 'click';
    public string $button;
    public int $x;
    public int $y;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->button = $data['button'] ?? '';
        $instance->x = isset($data['x']) ? (int)$data['x'] : 0;
        $instance->y = isset($data['y']) ? (int)$data['y'] : 0;
        return $instance;
    }
}