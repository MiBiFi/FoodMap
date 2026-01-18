<?php

namespace AssistantEngine\OpenAI\Resources;

use AssistantEngine\OpenAI\Resources\Responses\InputItems;
use AssistantEngine\OpenAI\Types\Responses\Response;
use GuzzleHttp\Client;

class Responses
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Send a create request to the OpenAI responses API.
     *
     * @param array $payload An associative array with keys like "model" and "input"
     * @return Response The decoded JSON response from the API.
     */
    public function create(array $payload): Response
    {
        $response = $this->client->post('responses', [
            'json' => $payload
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return Response::fromArray($data);
    }

    /**
     * Retrieve a response by its unique ID.
     *
     * @param string $id The unique identifier of the response.
     * @return Response The decoded JSON response from the API.
     */
    public function find(string $id): Response
    {
        $response = $this->client->get("responses/{$id}");
        $data = json_decode($response->getBody()->getContents(), true);

        return Response::fromArray($data);
    }

    /**
     * Delete a response by its unique ID.
     *
     * @param string $id The unique identifier of the response.
     * @return array a response message
     */
    public function delete(string $id): array
    {
        $response = $this->client->delete("responses/{$id}");

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get the InputItems sub-resource.
     *
     * @return InputItems
     */
    public function inputItems(): InputItems
    {
        return new InputItems($this->client);
    }
}