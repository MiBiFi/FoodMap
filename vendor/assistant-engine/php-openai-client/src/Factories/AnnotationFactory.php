<?php

namespace AssistantEngine\OpenAI\Factories;

use AssistantEngine\OpenAI\Contracts\AnnotationInterface;
use AssistantEngine\OpenAI\Types\Responses\Output\Message\AnnotationFileCitation;
use AssistantEngine\OpenAI\Types\Responses\Output\Message\AnnotationFilePath;
use AssistantEngine\OpenAI\Types\Responses\Output\Message\AnnotationURLCitation;

class AnnotationFactory
{
    /**
     * Factory method to return the appropriate annotation instance based on the type.
     *
     * @param array $data
     * @return AnnotationInterface
     * @throws \Exception if the annotation type is unknown.
     */
    public static function fromArray(array $data): AnnotationInterface {
        $type = $data['type'] ?? '';
        return match ($type) {
            'file_citation' => AnnotationFileCitation::fromArray($data),
            'url_citation' => AnnotationURLCitation::fromArray($data),
            'file_path' => AnnotationFilePath::fromArray($data),
            default => throw new \Exception("Unknown annotation type: " . $type),
        };
    }
}