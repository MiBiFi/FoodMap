<?php

namespace AssistantEngine\OpenAI\Types\Responses;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ResponseError implements HydratableInterface
{
    public string $code;
    public string $message;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->code = $data['code'] ?? '';
        $instance->message = $data['message'] ?? '';
        return $instance;
    }
}