<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class UnsplashImageService
{
    private const API_URL = 'https://api.unsplash.com/search/photos';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $accessKey,
    ) {}

    /**
     * Search Unsplash for a landscape photo matching the query.
     * Returns the "regular" sized image URL or null on failure.
     */
    public function findPhotoUrl(string $query): ?string
    {
        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Client-ID ' . $this->accessKey,
                ],
                'query' => [
                    'query'          => $query,
                    'per_page'       => 1,
                    'orientation'    => 'landscape',
                    'content_filter' => 'high',
                ],
            ]);

            $data = $response->toArray();

            $url = $data['results'][0]['urls']['regular'] ?? null;

            if ($url === null) {
                $this->logger->warning('Unsplash: no results for query: ' . $query);
            }

            return $url;

        } catch (\Throwable $e) {
            $this->logger->error('Unsplash API error: ' . $e->getMessage());
            return null;
        }
    }
}
