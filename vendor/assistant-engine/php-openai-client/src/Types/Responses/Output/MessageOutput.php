<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output;

use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;
use AssistantEngine\OpenAI\Types\Responses\Output\Message\ResponseOutputRefusal;
use AssistantEngine\OpenAI\Types\Responses\Output\Message\ResponseOutputText;


class MessageOutput implements ResponseOutputItem
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_INCOMPLETE = 'incomplete';

    public string $id;
    public array $content;
    public string $role;
    public string $status;
    public string $type; // should always be "message"

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->role = $data['role'] ?? 'assistant';
        $instance->status = $data['status'] ?? '';
        $instance->type = $data['type'] ?? 'message';

        $instance->content = [];

        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $contentItem) {
                if ($contentItem['type'] === 'output_text') {
                    $instance->content[] = ResponseOutputText::fromArray($contentItem);
                } else if ($contentItem['type'] === 'refusal') {
                    $instance->content[] = ResponseOutputRefusal::fromArray($contentItem);
                } else {
                    throw new \Exception('unknown content type: ' . $contentItem['type']);
                }
            }
        }

        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}