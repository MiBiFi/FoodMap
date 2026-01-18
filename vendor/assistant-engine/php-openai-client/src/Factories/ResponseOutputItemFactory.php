<?php

namespace AssistantEngine\OpenAI\Factories;

use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;
use AssistantEngine\OpenAI\Types\Responses\Output\ComputerToolCall;
use AssistantEngine\OpenAI\Types\Responses\Output\FileSearchToolCall;
use AssistantEngine\OpenAI\Types\Responses\Output\FunctionToolCall;
use AssistantEngine\OpenAI\Types\Responses\Output\MessageOutput;
use AssistantEngine\OpenAI\Types\Responses\Output\WebSearchToolCall;
use AssistantEngine\OpenAI\Types\Responses\Output\Reasoning;

class ResponseOutputItemFactory
{
    public static function fromArray(array $data): ResponseOutputItem {
        $type = $data['type'] ?? '';
        return match ($type) {
            'message' => MessageOutput::fromArray($data),
            'file_search_call' => FileSearchToolCall::fromArray($data),
            'function_call' => FunctionToolCall::fromArray($data),
            'web_search_call' => WebSearchToolCall::fromArray($data),
            'computer_call' => ComputerToolCall::fromArray($data),
            'reasoning' => Reasoning::fromArray($data),
            default => throw new \Exception('Response output item type not found: ' . $type),
        };
    }
}