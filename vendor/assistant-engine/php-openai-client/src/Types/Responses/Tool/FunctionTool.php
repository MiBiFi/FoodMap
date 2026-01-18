<?php

namespace AssistantEngine\OpenAI\Types\Responses\Tool;

use AssistantEngine\OpenAI\Contracts\Tool;

class FunctionTool  implements Tool
{
    public string $name;
    public array $parameters;
    public bool $strict;
    public string $type; // always "function"
    public ?string $description;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->name = $data['name'] ?? '';
        $instance->parameters = $data['parameters'] ?? [];
        $instance->strict = $data['strict'] ?? true;
        $instance->type = $data['type'] ?? 'function';
        $instance->description = $data['description'] ?? null;
        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}