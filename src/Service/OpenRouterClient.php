<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenRouterClient
{
    public function __construct(private HttpClientInterface $client) {}

    public function chat(string $apiKey, string $model, array $messages): string
    {
        $response = $this->client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => 'https://travelmate.local',
                'X-Title'       => 'TravelMate',
            ],
            'json' => [
                'model'    => $model,
                'messages' => $messages,
            ],
        ]);

        $data = $response->toArray();
        return $data['choices'][0]['message']['content'] ?? '';
    }
}