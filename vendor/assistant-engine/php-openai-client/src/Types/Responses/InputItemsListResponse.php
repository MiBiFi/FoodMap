<?php

namespace AssistantEngine\OpenAI\Types\Responses;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;
use AssistantEngine\OpenAI\Factories\InputItemListFactory;

class InputItemsListResponse implements HydratableInterface
{
    /** The type of object (should be "list") */
    public string $object;

    /** A plain array of the returned data */
    public array $data;

    /** The first message ID */
    public string $first_id;

    /** The last message ID */
    public string $last_id;

    /** Whether there are more items */
    public bool $has_more;

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->object = $data['object'] ?? '';
        $instance->first_id = $data['first_id'] ?? '';
        $instance->last_id = $data['last_id'] ?? '';
        $instance->has_more = $data['has_more'] ?? false;

        foreach ($data['data'] as $item) {
            $instance->data[] = InputItemListFactory::fromArray($item);
        }

        return $instance;
    }
}