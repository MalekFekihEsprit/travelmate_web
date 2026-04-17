<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class YouTubeVideoService
{
    private const YOUTUBE_SEARCH_URL = 'https://www.googleapis.com/youtube/v3/search';
    private const YOUTUBE_VIDEO_URL = 'https://www.youtube.com/watch?v=';

    /** @var array<string, string|null> */
    private array $cache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly int $minDurationSeconds = 30,
        private readonly int $maxDurationSeconds = 240,
    ) {}

    public function fetchVideoUrl(string $city, string $country): ?string
    {
        $city = trim($city);
        $country = trim($country);

        if ($city === '' || $country === '') {
            return null;
        }

        if (trim($this->apiKey) === '') {
            $this->logger->warning('YouTube API key is missing.');
            return null;
        }

        $cacheKey = mb_strtolower($city . '|' . $country);
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        try {
            $destination = sprintf('%s, %s', $city, $country);
            $this->logger->info(sprintf('Searching YouTube video for: %s', $destination));

            $videoId = $this->searchSingleBestVideo($city, $country);
            if ($videoId !== null) {
                $url = self::YOUTUBE_VIDEO_URL . $videoId;
                $this->cache[$cacheKey] = $url;
                return $url;
            }

            $this->cache[$cacheKey] = null;
            return null;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Error while fetching YouTube video for %s, %s: %s', $city, $country, $e->getMessage()));
            $this->cache[$cacheKey] = null;
            return null;
        }
    }

    public function fetchVideoUrlFromDestination(string $destination): ?string
    {
        $parts = array_map('trim', explode(',', $destination));
        if (count($parts) !== 2) {
            return null;
        }

        return $this->fetchVideoUrl($parts[0], $parts[1]);
    }

    private function searchSingleBestVideo(string $city, string $country): ?string
    {
        $query = sprintf('%s %s travel guide', $city, $country);

        $payload = $this->makeApiCall(self::YOUTUBE_SEARCH_URL, [
            'part' => 'snippet',
            'q' => $query,
            'type' => 'video',
            'maxResults' => 5,
            'videoEmbeddable' => 'true',
            'videoSyndicated' => 'true',
            'safeSearch' => 'moderate',
            'key' => $this->apiKey,
        ]);

        $items = $payload['items'] ?? null;
        if (!is_array($items)) {
            return null;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? null;
            if (!is_array($id)) {
                continue;
            }

            $videoId = $id['videoId'] ?? null;
            if (is_string($videoId) && $videoId !== '') {
                return $videoId;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function makeApiCall(string $url, array $query): array
    {
        $response = $this->httpClient->request('GET', $url, [
            'query' => $query,
            'timeout' => 4,
            'max_duration' => 4,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $error = $response->getContent(false);
            throw new \RuntimeException(sprintf('YouTube API error %d: %s', $statusCode, $error));
        }

        $content = $response->getContent(false);
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}