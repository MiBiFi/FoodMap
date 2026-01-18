<?php

namespace AssistantEngine\OpenAI\Types\Responses;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ResponseUsage implements HydratableInterface
{
    public int $input_tokens;
    /** @var array A detailed breakdown of input tokens */
    public array $input_tokens_details;
    public int $output_tokens;
    public int $total_tokens;
    /** @var array A detailed breakdown of output tokens */
    public array $output_tokens_details;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->input_tokens = $data['input_tokens'] ?? 0;
        $instance->input_tokens_details = $data['input_tokens_details'] ?? [];
        $instance->output_tokens = $data['output_tokens'] ?? 0;
        $instance->output_tokens_details = $data['output_tokens_details'] ?? [];
        $instance->total_tokens = $data['total_tokens'] ?? 0;
        return $instance;
    }
}