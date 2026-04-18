<?php
// src/Service/AiRecommendationService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiRecommendationService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $aiServiceUrl
    ) {}

    public function getRecommendations(string $userProfile, array $activities): array
    {
        $response = $this->client->request('POST', $this->aiServiceUrl . '/recommend', [
            'json' => [
                'user_profile' => $userProfile,
                'activities'   => $activities,
            ]
        ]);

        return $response->toArray()['recommendations'];
    }
}