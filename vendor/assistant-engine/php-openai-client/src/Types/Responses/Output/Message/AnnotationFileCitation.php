<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Message;

use AssistantEngine\OpenAI\Contracts\AnnotationInterface;

class AnnotationFileCitation implements AnnotationInterface
{
    public string $type = 'file_citation';
    public string $file_id;
    public int $index;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->file_id = $data['file_id'] ?? '';
        $instance->index = $data['index'] ?? 0;
        return $instance;
    }
}
