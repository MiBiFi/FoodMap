<?php

namespace AssistantEngine\OpenAI\Contracts;

interface HydratableInterface
{
    public static function fromArray(array $data): self;
}