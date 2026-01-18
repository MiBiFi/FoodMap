<?php

namespace AssistantEngine\OpenAI\Types\Responses\Tool;

use AssistantEngine\OpenAI\Contracts\Tool;

class ComputerTool  implements Tool
{
    public float $display_height;
    public float $display_width;
    public string $environment;
    public string $type; // always "computer_use_preview"

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->display_height = $data['display_height'] ?? 0;
        $instance->display_width = $data['display_width'] ?? 0;
        $instance->environment = $data['environment'] ?? '';
        $instance->type = $data['type'] ?? 'computer_use_preview';
        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}