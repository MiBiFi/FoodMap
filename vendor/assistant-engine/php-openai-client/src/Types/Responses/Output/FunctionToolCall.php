<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output;

use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;

class FunctionToolCall implements ResponseOutputItem
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_INCOMPLETE  = 'incomplete';

    public string $id;
    public string $arguments;
    public string $call_id;
    public string $name;
    public string $type; // should be "function_call"
    public ?string $status;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->arguments = $data['arguments'] ?? '';
        $instance->call_id = $data['call_id'] ?? '';
        $instance->name = $data['name'] ?? '';
        $instance->type = $data['type'] ?? 'function_call';
        $instance->status = $data['status'] ?? null;
        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}