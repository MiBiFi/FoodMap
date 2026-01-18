<?php

namespace AssistantEngine\OpenAI;

use AssistantEngine\OpenAI\Resources\Responses;
use AssistantEngine\OpenAI\Types\Response;
use GuzzleHttp\Client as GuzzleClient;

class Client
{
    protected string $apiKey;
    protected GuzzleClient $client;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new GuzzleClient([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers'  => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ]
        ]);
    }

    /**
     * @return Responses
     */
    public function responses(): Responses
    {
        return new Responses($this->client);
    }

    public static function make(string $apiKey): Client
    {
        return new Client($apiKey);
    }
}