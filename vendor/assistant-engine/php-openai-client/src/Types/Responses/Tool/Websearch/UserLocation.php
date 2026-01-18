<?php

namespace AssistantEngine\OpenAI\Types\Responses\Tool\WebSearch;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class UserLocation implements HydratableInterface
{
    /**
     * The type of location approximation. Always "approximate".
     */
    public string $type = 'approximate';

    /**
     * Free text input for the city of the user, e.g. "San Francisco".
     */
    public ?string $city = null;

    /**
     * The two-letter ISO country code of the user, e.g. "US".
     */
    public ?string $country = null;

    /**
     * Free text input for the region of the user, e.g. "California".
     */
    public ?string $region = null;

    /**
     * The IANA timezone of the user, e.g. "America/Los_Angeles".
     */
    public ?string $timezone = null;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->type = $data['type'] ?? 'approximate';
        $instance->city = $data['city'] ?? null;
        $instance->country = $data['country'] ?? null;
        $instance->region = $data['region'] ?? null;
        $instance->timezone = $data['timezone'] ?? null;
        return $instance;
    }
}