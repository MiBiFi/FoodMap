<?php

namespace AssistantEngine\OpenAI\Types\Responses\Input\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class Output implements HydratableInterface
{
    /**
     * The type of the output object.
     * Always set to `computer_screenshot`.
     *
     * @var string
     */
    public string $type;

    /**
     * The identifier of an uploaded file that contains the screenshot.
     *
     * @var string|null
     */
    public ?string $file_id = null;

    /**
     * The URL of the screenshot image.
     *
     * @var string|null
     */
    public ?string $image_url = null;

    public function __construct()
    {
        $this->type = 'computer_screenshot';
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->type = $data['type'] ?? 'computer_screenshot';
        $instance->file_id = $data['file_id'] ?? null;
        $instance->image_url = $data['image_url'] ?? null;
        return $instance;
    }
}