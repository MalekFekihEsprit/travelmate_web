<?php
// src/Service/WikimediaImageService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WikimediaImageService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $pexelsApiKey = '',
    ) {}

    public function findPhotoUrl(string $hebergementName, string $destinationName = ''): ?string
    {
        // ── 1. Wikipedia : page exacte de l'hôtel ──────────────────────────
        $url = $this->searchWikipediaImage($hebergementName);
        if ($url) return $url;

        if ($destinationName) {
            $url = $this->searchWikipediaImage($hebergementName . ' ' . $destinationName);
            if ($url) return $url;
        }

        // ── 2. Wikimedia Commons ────────────────────────────────────────────
        $url = $this->searchCommonsImage($hebergementName);
        if ($url) return $url;

        // ── 3. Pexels : photo du type d'hébergement + destination ──────────
        if ($this->pexelsApiKey !== '') {
            $url = $this->searchPexelsImage($hebergementName, $destinationName);
            if ($url) return $url;
        }

        return null;
    }

    // ── Wikipedia ────────────────────────────────────────────────────────────

    private function searchWikipediaImage(string $query): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://en.wikipedia.org/w/api.php', [
                'query' => [
                    'action'      => 'query',
                    'titles'      => $query,
                    'prop'        => 'pageimages',
                    'pithumbsize' => 800,
                    'format'      => 'json',
                    'origin'      => '*',
                ],
                'timeout' => 5,
            ]);

            $data  = $response->toArray(false);
            $pages = $data['query']['pages'] ?? [];

            foreach ($pages as $page) {
                if (!empty($page['thumbnail']['source'])) {
                    return $page['thumbnail']['source'];
                }
            }
        } catch (\Throwable) {}

        return null;
    }

    // ── Wikimedia Commons ────────────────────────────────────────────────────

    private function searchCommonsImage(string $query): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://commons.wikimedia.org/w/api.php', [
                'query' => [
                    'action'       => 'query',
                    'generator'    => 'search',
                    'gsrsearch'    => 'File:' . $query . ' hotel',
                    'gsrnamespace' => 6,
                    'prop'         => 'imageinfo',
                    'iiprop'       => 'url',
                    'iiurlwidth'   => 800,
                    'gsrlimit'     => 1,
                    'format'       => 'json',
                    'origin'       => '*',
                ],
                'timeout' => 5,
            ]);

            $data  = $response->toArray(false);
            $pages = $data['query']['pages'] ?? [];

            foreach ($pages as $page) {
                $thumbUrl = $page['imageinfo'][0]['thumburl'] ?? null;
                if ($thumbUrl) return $thumbUrl;
            }
        } catch (\Throwable) {}

        return null;
    }

    // ── Pexels fallback ──────────────────────────────────────────────────────

    private function searchPexelsImage(string $hebergementName, string $destinationName): ?string
    {
        $query = $this->buildPexelsQuery($hebergementName, $destinationName);

        try {
            $response = $this->httpClient->request('GET', 'https://api.pexels.com/v1/search', [
                'headers' => [
                    'Authorization' => $this->pexelsApiKey,
                ],
                'query' => [
                    'query'       => $query,
                    'per_page'    => 1,
                    'orientation' => 'landscape',
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray(false);

            if (!empty($data['photos'][0]['src']['large'])) {
                return $data['photos'][0]['src']['large'];
            }
        } catch (\Throwable) {}

        return null;
    }

    private function buildPexelsQuery(string $name, string $destination): string
    {
        $nameLower = mb_strtolower($name);

        $typeKeywords = [
            'villa'    => 'villa',
            'auberge'  => 'hostel',
            'hostel'   => 'hostel',
            'appart'   => 'apartment',
            'apart'    => 'apartment',
            'resort'   => 'resort',
            'chateau'  => 'chateau hotel',
            'château'  => 'chateau hotel',
            'camping'  => 'camping',
            'riad'     => 'riad',
            'gîte'     => 'countryside house',
            'spa'      => 'spa hotel',
        ];

        $type = 'hotel';
        foreach ($typeKeywords as $keyword => $pexelsType) {
            if (str_contains($nameLower, $keyword)) {
                $type = $pexelsType;
                break;
            }
        }

        return trim($type . ($destination ? ' ' . $destination : ''));
    }
}