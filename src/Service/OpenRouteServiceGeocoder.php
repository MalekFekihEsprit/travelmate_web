<?php

namespace App\Service;

use App\Entity\Destination;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenRouteServiceGeocoder
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly string $geocodeUrl,
        private readonly string $cacheDirectory,
        private readonly int $cacheTtl,
    ) {
    }

    public function isConfigured(): bool
    {
        $apiKey = trim($this->apiKey);

        return $apiKey !== '' && !str_contains($apiKey, 'replace_with_your_openrouteservice_api_key');
    }

    public function geocodePlace(string $place, ?Destination $destination = null): ?array
    {
        $place = trim($place);
        if ($place === '' || !$this->isConfigured()) {
            return null;
        }

        $searchText = $this->buildSearchText($place, $destination);
        $cached = $this->loadFromCache($searchText, $destination);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $query = [
                'text' => $searchText,
                'size' => 1,
            ];

            if ($destination && $destination->getLongitude_destination() !== null && $destination->getLatitude_destination() !== null) {
                $query['focus.point.lon'] = $destination->getLongitude_destination();
                $query['focus.point.lat'] = $destination->getLatitude_destination();
            }

            $response = $this->httpClient->request('GET', $this->geocodeUrl, [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'timeout' => 20,
            ]);

            $payload = $response->toArray(false);
            $feature = $payload['features'][0] ?? null;
            $coordinates = $feature['geometry']['coordinates'] ?? null;
            if (!is_array($feature) || !is_array($coordinates) || count($coordinates) < 2) {
                return null;
            }

            $properties = $feature['properties'] ?? [];
            $result = [
                'lat' => (float) $coordinates[1],
                'lng' => (float) $coordinates[0],
                'label' => (string) ($properties['label'] ?? $searchText),
                'locality' => (string) ($properties['locality'] ?? $properties['county'] ?? $destination?->getNom_destination() ?? ''),
                'country' => (string) ($properties['country'] ?? $destination?->getPays_destination() ?? ''),
                'source_place' => $place,
            ];

            $this->storeInCache($searchText, $destination, $result);

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->warning('OpenRouteService geocoding failed.', [
                'place' => $place,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function buildSearchText(string $place, ?Destination $destination = null): string
    {
        $parts = [$place];

        if ($destination) {
            $destinationName = trim((string) $destination->getNom_destination());
            $countryName = trim((string) $destination->getPays_destination());

            if ($destinationName !== '' && stripos($place, $destinationName) === false) {
                $parts[] = $destinationName;
            }

            if ($countryName !== '' && stripos($place, $countryName) === false) {
                $parts[] = $countryName;
            }
        }

        return implode(', ', array_filter($parts));
    }

    private function getCacheFilePath(string $searchText, ?Destination $destination): string
    {
        $fingerprint = [
            'text' => $searchText,
            'destination' => $destination?->getId_destination(),
            'name' => $destination?->getNom_destination(),
            'country' => $destination?->getPays_destination(),
            'url' => $this->geocodeUrl,
        ];

        return rtrim($this->cacheDirectory, '\\/') . DIRECTORY_SEPARATOR . hash('sha256', json_encode($fingerprint)) . '.json';
    }

    private function loadFromCache(string $searchText, ?Destination $destination): ?array
    {
        $filePath = $this->getCacheFilePath($searchText, $destination);
        if (!is_file($filePath) || filemtime($filePath) < (time() - $this->cacheTtl)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function storeInCache(string $searchText, ?Destination $destination, array $payload): void
    {
        if (!is_dir($this->cacheDirectory) && !mkdir($concurrentDirectory = $this->cacheDirectory, 0777, true) && !is_dir($concurrentDirectory)) {
            return;
        }

        file_put_contents($this->getCacheFilePath($searchText, $destination), json_encode($payload, JSON_PRETTY_PRINT));
    }
}