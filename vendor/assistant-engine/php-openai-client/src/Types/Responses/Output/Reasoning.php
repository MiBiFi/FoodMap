<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output;

use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;
use AssistantEngine\OpenAI\Types\Responses\Output\Reasoning\Summary;

class Reasoning implements ResponseOutputItem
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_INCOMPLETE = 'incomplete';

    /** @var string The unique identifier of the reasoning content. */
    public string $id;

    /** @var Summary[] An array of summary items. */
    public array $summary;

    /** @var string The type of the object. Always "reasoning". */
    public string $type = 'reasoning';

    /**
     * @var string|null The status of the item.
     * One of "in_progress", "completed", or "incomplete".
     */
    public ?string $status;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->summary = [];
        if (isset($data['summary']) && is_array($data['summary'])) {
            foreach ($data['summary'] as $summaryData) {
                $instance->summary[] = Summary::fromArray($summaryData);
            }
        }
        $instance->status = $data['status'] ?? null;
        return $instance;
    }

    public function getType(): string
    {
        return $this->type;
    }
}