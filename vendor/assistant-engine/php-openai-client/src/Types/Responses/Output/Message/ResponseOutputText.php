<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Message;

use AssistantEngine\OpenAI\Contracts\AnnotationInterface;
use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;
use AssistantEngine\OpenAI\Factories\AnnotationFactory;

class ResponseOutputText implements ResponseOutputItem
{
    public string $type = 'output_text';
    public string $text;
    /** @var AnnotationInterface[] */
    public array $annotations;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->text = $data['text'] ?? '';
        $instance->annotations = [];
        if (isset($data['annotations']) && is_array($data['annotations'])) {
            foreach ($data['annotations'] as $annotationData) {
                $instance->annotations[] = AnnotationFactory::fromArray($annotationData);
            }
        }
        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}