<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ActionDrag implements HydratableInterface
{
    public string $type = 'drag';
    /** @var ActionDragPath[] */
    public array $path = [];

    public static function fromArray(array $data): self {
        $instance = new self();
        if (isset($data['path']) && is_array($data['path'])) {
            foreach ($data['path'] as $pathItem) {
                $instance->path[] = ActionDragPath::fromArray($pathItem);
            }
        }
        return $instance;
    }
}