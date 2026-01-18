<?php

namespace AssistantEngine\OpenAI\Factories;


use AssistantEngine\OpenAI\Contracts\Tool;
use AssistantEngine\OpenAI\Types\Responses\Tool\ComputerTool;
use AssistantEngine\OpenAI\Types\Responses\Tool\FileSearchTool;
use AssistantEngine\OpenAI\Types\Responses\Tool\FunctionTool;
use AssistantEngine\OpenAI\Types\Responses\Tool\WebSearchTool;

class ToolFactory {
    public static function fromArray(array $data): Tool {
        $type = $data['type'] ?? '';

        return match ($type) {
            'file_search' => FileSearchTool::fromArray($data),
            'function' => FunctionTool::fromArray($data),
            'computer_use_preview' => ComputerTool::fromArray($data),
            'web_search_preview', 'web_search_preview_2025_03_11' => WebSearchTool::fromArray($data),
            default => throw new \Exception("Unknown tool type '{$type}'"),
        };
    }
}