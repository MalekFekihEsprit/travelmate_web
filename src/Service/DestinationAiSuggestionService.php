<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DestinationAiSuggestionService
{
    private const OPENROUTER_API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * @var list<string>
     */
    private array $modelsToTry = [
        'google/gemma-3-4b-it:free',
        'nex-agi/deepseek-v3.1-nex-n1:free',
        'meta-llama/llama-3.3-70b-instruct:free',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
    ) {
    }

    public function generateDescriptionAndSuggestions(string $city, string $country): string
    {
        $city = trim($city);
        $country = trim($country);

        if ($city === '' || $country === '') {
            throw new DestinationAiSuggestionException('City and country cannot be empty.');
        }

        if ($this->apiKey === '') {
            throw new DestinationAiSuggestionException('OPENROUTER_API_KEY is not configured.');
        }

        $errors = [];

        foreach ($this->modelsToTry as $model) {
            try {
                $payload = $this->buildPayload($city, $country, $model);
                $response = $this->httpClient->request('POST', self::OPENROUTER_API_URL, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                        'HTTP-Referer' => 'http://localhost',
                        'X-Title' => 'TravelMate Symfony App',
                    ],
                    'json' => $payload,
                    'timeout' => 60,
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->getContent(false);

                if ($statusCode !== 200) {
                    throw new DestinationAiSuggestionException(sprintf('Model %s returned status %d: %s', $model, $statusCode, $body));
                }

                $content = $this->parseContent($body);
                if ($content !== '') {
                    $this->logger->info('AI suggestions generated successfully.', ['model' => $model, 'city' => $city, 'country' => $country]);
                    return $content;
                }

                throw new DestinationAiSuggestionException(sprintf('Model %s returned an empty response.', $model));
            } catch (TransportExceptionInterface | DestinationAiSuggestionException $exception) {
                $error = sprintf('Model %s failed: %s', $model, $exception->getMessage());
                $errors[] = $error;
                $this->logger->warning('AI model failed.', [
                    'model' => $model,
                    'city' => $city,
                    'country' => $country,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw new DestinationAiSuggestionException(
            'All AI models failed. Last error: ' . ($errors !== [] ? end($errors) : 'Unknown error.')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $city, string $country, string $model): array
    {
        $prompt = sprintf(
            "You are a travel expert. Write content for %s, %s using this exact structure:\n\n" .
            "1) First, write a short and professional tourism description in French (maximum 60 words). Do not add a heading for this paragraph.\n\n" .
            "2) Then add this heading exactly: Monuments a visiter\n" .
            "Provide 5 bullet points. Each bullet must be one concise line with monument name and why it is worth visiting.\n\n" .
            "3) Then add this heading exactly: Activites signatures\n" .
            "Provide 5 bullet points. Each bullet must be one concise line with a signature activity and why to try it.\n\n" .
            "Rules: no emojis, no markdown code blocks, no itinerary/day-by-day plan.",
            $city,
            $country
        );

        return [
            'model' => $model,
            'max_tokens' => 900,
            'temperature' => 0.7,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];
    }

    private function parseContent(string $jsonBody): string
    {
        $decoded = json_decode($jsonBody, true);

        if (!is_array($decoded)) {
            throw new DestinationAiSuggestionException('Failed to decode AI response.');
        }

        if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            throw new DestinationAiSuggestionException('API error: ' . $decoded['error']['message']);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;

        if (!is_string($content)) {
            throw new DestinationAiSuggestionException('Unexpected AI response format.');
        }

        return trim($content);
    }
}

