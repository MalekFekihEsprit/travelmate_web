<?php
// src/Service/AiRecommendationService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AiRecommendationService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $aiServiceUrl
    ) {}

    public function getRecommendations(string $userProfile, array $activities): array
    {
        // If no AI service URL, return fallback immediately
        if (empty($this->aiServiceUrl)) {
            return [
                'success' => false,
                'message' => 'AI service is not configured on this environment.',
                'recommendations' => []
            ];
        }

        try {
            $response = $this->client->request('POST', $this->aiServiceUrl . '/recommend', [
                'json' => [
                    'user_profile' => $userProfile,
                    'activities'   => $activities,
                ],
                'timeout' => 5, // prevent long hangs
            ]);

            $data = $response->toArray(false);
            
            // Ensure the expected key exists
            return [
                'success' => true,
                'recommendations' => $data['recommendations'] ?? [],
            ];
        } catch (TransportExceptionInterface $e) {
            // Network error (connection refused, timeout, DNS, etc.)
            return [
                'success' => false,
                'message' => 'AI service temporarily unreachable.',
                'recommendations' => []
            ];
        } catch (\Throwable $e) {
            // Any other error (bad response, JSON parse, etc.)
            return [
                'success' => false,
                'message' => 'AI service error: ' . $e->getMessage(),
                'recommendations' => []
            ];
        }
    }
}