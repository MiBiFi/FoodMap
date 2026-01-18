<?php

namespace AssistantEngine\OpenAI\Types\Responses\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class SafetyCheck implements HydratableInterface
{
    public string $id;
    public string $code;
    public string $message;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->code = $data['code'] ?? '';
        $instance->message = $data['message'] ?? '';
        return $instance;
    }
}