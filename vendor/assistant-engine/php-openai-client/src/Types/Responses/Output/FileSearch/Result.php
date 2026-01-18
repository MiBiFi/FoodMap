<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\FileSearch;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class Result implements HydratableInterface
{
    public ?array $attributes = null;

    /** @var string|null The unique ID of the file */
    public ?string $file_id = null;

    /** @var string|null The name of the file */
    public ?string $filename = null;

    /** @var float|null The relevance score of the file (a value between 0 and 1) */
    public ?float $score = null;

    /** @var string|null The text that was retrieved from the file */
    public ?string $text = null;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->attributes = $data['attributes'] ?? null;
        $instance->file_id = $data['file_id'] ?? null;
        $instance->filename = $data['filename'] ?? null;
        $instance->score = isset($data['score']) ? (float)$data['score'] : null;
        $instance->text = $data['text'] ?? null;
        return $instance;
    }
}