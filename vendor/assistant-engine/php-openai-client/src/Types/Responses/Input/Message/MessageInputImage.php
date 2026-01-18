<?php

namespace AssistantEngine\OpenAI\Types\Responses\Input\Message;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class MessageInputImage  implements HydratableInterface
{
    /**
     * The detail level of the image to be sent to the model.
     * One of `high`, `low`, or `auto`.
     *
     * @var string
     */
    public string $detail;

    /**
     * The type of the input item. Always `input_image`.
     *
     * @var string
     */
    public string $type;

    /**
     * The ID of the file to be sent to the model.
     *
     * @var string|null
     */
    public ?string $file_id = null;

    /**
     * The URL of the image to be sent to the model.
     *
     * @var string|null
     */
    public ?string $image_url = null;

    public function __construct()
    {
        $this->type = 'input_image';
        $this->detail = 'auto'; // default to auto if not provided
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->detail = $data['detail'] ?? 'auto';
        $instance->type = $data['type'] ?? 'input_image';
        $instance->file_id = $data['file_id'] ?? null;
        $instance->image_url = $data['image_url'] ?? null;
        return $instance;
    }
}