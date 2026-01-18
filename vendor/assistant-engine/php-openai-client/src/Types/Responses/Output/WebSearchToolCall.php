<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output;

use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;

class WebSearchToolCall implements ResponseOutputItem
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SEARCHING = 'searching';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /** @var string The unique ID of the web search tool call */
    public string $id;

    /** @var string The status of the web search tool call */
    public string $status;

    /** @var string The type of the tool call output. Always "web_search_call" */
    public string $type;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->status = $data['status'] ?? '';
        $instance->type = $data['type'] ?? 'web_search_call';
        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}