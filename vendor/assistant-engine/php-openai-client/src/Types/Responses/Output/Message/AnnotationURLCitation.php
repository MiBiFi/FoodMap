<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Message;

use AssistantEngine\OpenAI\Contracts\AnnotationInterface;

class AnnotationURLCitation  implements AnnotationInterface
{
    public string $type = 'url_citation';
    public int $start_index;
    public int $end_index;
    public string $title;
    public string $url;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->start_index = $data['start_index'] ?? 0;
        $instance->end_index = $data['end_index'] ?? 0;
        $instance->title = $data['title'] ?? '';
        $instance->url = $data['url'] ?? '';
        return $instance;
    }
}