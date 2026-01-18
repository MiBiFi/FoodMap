<?php

namespace AssistantEngine\OpenAI\Contracts;

interface Tool extends HydratableInterface
{
    public function getType(): string;
}