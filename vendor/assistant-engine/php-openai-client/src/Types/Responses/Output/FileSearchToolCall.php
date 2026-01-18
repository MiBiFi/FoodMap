<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output;

use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;
use AssistantEngine\OpenAI\Types\Responses\Output\FileSearch\Result;

class FileSearchToolCall implements ResponseOutputItem
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SEARCHING = 'searching';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_FAILED = 'failed';

    public string $id;
    public array $queries;
    public string $status;
    public string $type; // should be "file_search_call"
    public ?array $results;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->queries = $data['queries'] ?? [];
        $instance->status = $data['status'] ?? '';
        $instance->type = $data['type'] ?? 'file_search_call';

        if (isset($data['results']) && is_array($data['results'])) {
            $instance->results = [];
            foreach ($data['results'] as $resultData) {
                $instance->results[] = Result::fromArray($resultData);
            }
        } else {
            $instance->results = null;
        }

        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}