<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TicketmasterService
{
    private const BASE_URL = 'https://app.ticketmaster.com/discovery/v2/events.json';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string              $apiKey
    ) {}

    /**
     * Retourne une liste d'événements à venir pour une destination donnée.
     *
     * @param  string $destination  Ville ou pays (ex: "Paris", "Tunis", "London")
     * @param  int    $size         Nombre max de résultats (1–200)
     * @return array<int, array{
     *   titre: string,
     *   date: string,
     *   heure: string,
     *   lieu: string,
     *   ville: string,
     *   image: string|null,
     *   url: string,
     *   categorie: string,
     * }>
     */
    public function fetchEventsByDestination(string $destination, int $size = 12): array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL, [
                'query' => [
                    'apikey'  => $this->apiKey,
                    'keyword' => $destination,        // recherche par mot-clé destination
                    'city'    => $destination,        // filtre ville
                    'size'    => $size,
                    'sort'    => 'date,asc',          // les plus proches en premier
                    'startDateTime' => (new \DateTime())->format('Y-m-d') . 'T00:00:00Z',
                    'classificationName' => 'music,arts,sports,family,festival', // catégories pertinentes
                ],
                'timeout' => 8,
            ]);

            $data = $response->toArray(false);

            if (empty($data['_embedded']['events'])) {
                // Si pas de résultats par ville, retry avec keyword uniquement
                $response2 = $this->httpClient->request('GET', self::BASE_URL, [
                    'query' => [
                        'apikey'  => $this->apiKey,
                        'keyword' => $destination,
                        'size'    => $size,
                        'sort'    => 'date,asc',
                        'startDateTime' => (new \DateTime())->format('Y-m-d') . 'T00:00:00Z',
                    ],
                    'timeout' => 8,
                ]);
                $data = $response2->toArray(false);
            }

            if (empty($data['_embedded']['events'])) {
                return [];
            }

            return array_map([$this, 'normalizeEvent'], $data['_embedded']['events']);

        } catch (\Throwable $e) {
            // En cas d'erreur réseau, on retourne un tableau vide silencieusement
            return [];
        }
    }

    private function normalizeEvent(array $raw): array
    {
        // Date & heure
        $dateStr  = $raw['dates']['start']['localDate']  ?? null;
        $heureStr = $raw['dates']['start']['localTime']  ?? '00:00:00';

        // Lieu
        $venue    = $raw['_embedded']['venues'][0]  ?? [];
        $lieu     = $venue['name']                  ?? 'Lieu inconnu';
        $ville    = $venue['city']['name']          ?? '';

        // Image (on prend la plus large disponible)
        $images   = $raw['images'] ?? [];
        usort($images, fn($a, $b) => ($b['width'] ?? 0) <=> ($a['width'] ?? 0));
        $image    = !empty($images) ? $images[0]['url'] : null;

        // Catégorie
        $categorie = $raw['classifications'][0]['segment']['name'] ?? 'Événement';

        return [
            'titre'     => $raw['name'],
            'date'      => $dateStr,
            'heure'     => substr($heureStr, 0, 5),   // "HH:MM"
            'lieu'      => $lieu,
            'ville'     => $ville,
            'image'     => $image,
            'url'       => $raw['url']  ?? '#',
            'categorie' => $categorie,
        ];
    }
}