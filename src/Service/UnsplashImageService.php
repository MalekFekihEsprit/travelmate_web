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
        private readonly string $accessKeys,
    ) {}

    /**
     * Search Unsplash for a landscape photo matching the query.
     * Returns the "regular" sized image URL or null on failure.
     */
    public function findPhotoUrl(string $query): ?string
    {
        $keys = array_values(array_filter(array_map('trim', preg_split('/[;,\n]+/', $this->accessKeys) ?: [])));
        if ($keys === []) {
            $this->logger->warning('Unsplash: no access keys configured.');
            return null;
        }

        try {
            foreach ($keys as $accessKey) {
                try {
                    $response = $this->httpClient->request('GET', self::API_URL, [
                        'headers' => [
                            'Authorization' => 'Client-ID ' . $accessKey,
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

                    if ($url !== null) {
                        return $url;
                    }

                    $this->logger->warning('Unsplash: no results for query: ' . $query);
                } catch (\Throwable $keyException) {
                    $this->logger->warning('Unsplash key failed, trying next key: ' . $keyException->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $this->logger->error('Unsplash API error: ' . $e->getMessage());
            return null;
        }

        return null;
    }
}
