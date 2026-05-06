<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class HebergementScraperService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $rapidApiKey,
        private readonly string $rapidApiHost,
        private readonly string $rapidApiUrl,
        private readonly string $rapidApiCurrency = 'EUR',
        private readonly string $rapidApiLanguage = 'fr-fr',
    ) {}

    public function scrape(string $destination = 'Paris', int $maxResults = 30, string $cookies = ''): array
    {
        if (trim($destination) === '') {
            return [];
        }

        if (trim($this->rapidApiKey) === '' || trim($this->rapidApiHost) === '' || trim($this->rapidApiUrl) === '') {
            $this->logger->warning('RapidAPI credentials/config are missing.');
            return $this->buildFallbackData($destination);
        }

        $hotels = $this->fetchHotels($destination, $maxResults);

        if ($hotels !== []) {
            return $hotels;
        }

        // RapidAPI frequently fails (403 "not subscribed", etc.). Use Booking.com HTML scraping fallback.
        $fallbackHotels = $this->fetchHotelsFromBookingHtml($destination, $maxResults);
        if ($fallbackHotels !== []) {
            return $fallbackHotels;
        }

        return $this->buildFallbackData($destination);
    }

    private function fetchHotels(string $destination, int $maxResults): array
    {
        try {
            $checkIn = (new \DateTimeImmutable('+7 days'))->format('Y-m-d');
            $checkOut = (new \DateTimeImmutable('+9 days'))->format('Y-m-d');

            // Booking.com RapidAPI expects a destination id + type.
            // If we call a generic/base URL, RapidAPI may route to a different product (cars, etc.).
            $destinationMeta = $this->resolveBookingDestination($destination);
            if ($destinationMeta === null) {
                return [];
            }

            $response = $this->httpClient->request('GET', $this->buildRapidApiUrl('/v1/hotels/search'), [
                'headers' => [
                    'x-rapidapi-key' => $this->rapidApiKey,
                    'x-rapidapi-host' => $this->rapidApiHost,
                ],
                'query' => [
                    'dest_id' => $destinationMeta['dest_id'],
                    'dest_type' => $destinationMeta['dest_type'],
                    'arrival_date' => $checkIn,
                    'departure_date' => $checkOut,
                    'adults_number' => 2,
                    'room_number' => 1,
                    'order_by' => 'popularity',
                    'filter_by_currency' => $this->rapidApiCurrency,
                    'locale' => $this->rapidApiLanguage,
                    'units' => 'metric',
                ],
                'timeout' => 25,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $body = $response->getContent(false);
                $preview = mb_substr(trim((string) $body), 0, 500);
                throw new \RuntimeException(sprintf('RapidAPI hotels/search failed (%d): %s', $statusCode, $preview));
            }

            $payload = $response->toArray(false);
            $rawHotels = $this->extractHotelList($payload);

            if ($rawHotels === []) {
                return [];
            }

            $rawHotels = array_slice($rawHotels, 0, $maxResults);

            $results = [];

            foreach ($rawHotels as $hotel) {
                if (!is_array($hotel)) {
                    continue;
                }

                $name = $this->pickString($hotel, ['name', 'hotel_name', 'title', 'property_name']) ?? 'Hôtel';
                $address = $this->extractAddress($hotel, $destination);
                $rating = $this->extractRating($hotel);
                $price = $this->extractPrice($hotel);
                [$latitude, $longitude] = $this->extractCoordinates($hotel);
                $imageUrl = $this->extractImageUrl($hotel);

                $results[] = [
                    'name' => $name,
                    'type' => $this->guessType($name),
                    'price' => $price,
                    'address' => $address,
                    'rating' => $rating,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'image_url' => $imageUrl,
                ];
            }

            return $results;

        } catch (\Throwable $e) {
            $this->logger->error('RapidAPI hotels error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Best-effort HTML scraping fallback for Booking.com search results.
     *
     * @return array<int, array{name:string,type:string,price:?float,address:string,rating:?float,latitude:?float,longitude:?float,image_url:?string}>
     */
    private function fetchHotelsFromBookingHtml(string $destination, int $maxResults): array
    {
        try {
            $checkIn = (new \DateTimeImmutable('+7 days'))->format('Y-m-d');
            $checkOut = (new \DateTimeImmutable('+9 days'))->format('Y-m-d');

            $url = sprintf(
                'https://www.booking.com/searchresults.html?ss=%s&checkin=%s&checkout=%s&group_adults=2&no_rooms=1&lang=fr',
                urlencode($destination),
                $checkIn,
                $checkOut
            );

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ],
            ]);

            $html = $response->getContent(false);
            if (!is_string($html) || trim($html) === '') {
                return [];
            }

            $lowerHtml = mb_strtolower($html);
            if (str_contains($lowerHtml, 'captcha') || str_contains($lowerHtml, 'robot')) {
                $this->logger->warning('Booking.com HTML scraping blocked by captcha/robot page.');
                return [];
            }

            $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
            $cards = $crawler->filter('[data-testid="property-card"]');
            if ($cards->count() === 0) {
                // Older markup fallback
                $cards = $crawler->filter('.sr_property_block');
            }

            $results = [];
            foreach ($cards as $node) {
                if (count($results) >= $maxResults) {
                    break;
                }

                $card = new \Symfony\Component\DomCrawler\Crawler($node);

                $name = trim((string) ($card->filter('[data-testid="title"]')->first()->text('') ?: $card->filter('h3')->first()->text('')));
                if ($name === '') {
                    $name = 'Hôtel';
                }

                $address = trim((string) $card->filter('[data-testid="address"]')->first()->text(''));
                if ($address === '') {
                    $address = 'Adresse non disponible, ' . $destination;
                }

                // Rating: try review score first.
                $rating = null;
                $scoreText = trim((string) $card->filter('[data-testid="review-score"]')->first()->text(''));
                if ($scoreText !== '') {
                    if (preg_match('/([0-9]+[\\.,]?[0-9]*)/', $scoreText, $m)) {
                        $val = (float) str_replace(',', '.', $m[1]);
                        // Booking is typically /10; convert to /5.
                        $rating = $val > 5 ? round($val / 2, 1) : round($val, 1);
                    }
                }

                // Price: attempt to find a number with currency.
                $price = null;
                $priceText = trim((string) $card->filter('[data-testid="price-and-discounted-price"]')->first()->text(''));
                if ($priceText === '') {
                    $priceText = trim((string) $card->filter('[data-testid="price"]')->first()->text(''));
                }
                if ($priceText !== '') {
                    if (preg_match('/([0-9][0-9\\s]*)(?:[\\.,]([0-9]{1,2}))?/', $priceText, $m)) {
                        $intPart = (float) str_replace(' ', '', $m[1]);
                        $decPart = isset($m[2]) ? (float) ('0.' . $m[2]) : 0.0;
                        $price = $intPart + $decPart;
                    }
                }

                // Image: booking often uses <img src=...> or data-src.
                $imageUrl = null;
                $img = $card->filter('img')->first();
                if ($img->count() > 0) {
                    $src = trim((string) $img->attr('data-src'));
                    if ($src === '') {
                        $src = trim((string) $img->attr('src'));
                    }
                    if ($src !== '') {
                        $imageUrl = $src;
                    }
                }

                $results[] = [
                    'name' => $name,
                    'type' => $this->guessType($name),
                    'price' => $price,
                    'address' => $address,
                    'rating' => $rating,
                    'latitude' => null,
                    'longitude' => null,
                    'image_url' => $imageUrl,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error('Booking.com HTML scrape error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve Booking.com destination to dest_id/dest_type using the locations endpoint.
     *
     * @return array{dest_id: string, dest_type: string}|null
     */
    private function resolveBookingDestination(string $destination): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->buildRapidApiUrl('/v1/hotels/locations'), [
                'headers' => [
                    'x-rapidapi-key' => $this->rapidApiKey,
                    'x-rapidapi-host' => $this->rapidApiHost,
                ],
                'query' => [
                    'name' => $destination,
                    'locale' => $this->rapidApiLanguage,
                ],
                'timeout' => 20,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $body = $response->getContent(false);
                $preview = mb_substr(trim((string) $body), 0, 500);
                $this->logger->error(sprintf('RapidAPI hotels/locations failed (%d): %s', $statusCode, $preview));
                return null;
            }

            $payload = $response->toArray(false);
            if (!is_array($payload) || $payload === []) {
                return null;
            }

            // Usually the payload is a list.
            foreach ($payload as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }

                $destId = $this->pickString($candidate, ['dest_id', 'destId', 'id']);
                $destType = $this->pickString($candidate, ['dest_type', 'destType', 'type']);

                if ($destId !== null && $destType !== null) {
                    return ['dest_id' => $destId, 'dest_type' => $destType];
                }
            }

            // Fallback: try first item if it resembles Booking format.
            $first = $payload[0] ?? null;
            if (is_array($first)) {
                $destId = $this->pickString($first, ['dest_id', 'destId', 'id']);
                $destType = $this->pickString($first, ['dest_type', 'destType', 'type']);
                if ($destId !== null && $destType !== null) {
                    return ['dest_id' => $destId, 'dest_type' => $destType];
                }
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('RapidAPI locations error: ' . $e->getMessage());
            return null;
        }
    }

    private function buildRapidApiUrl(string $path): string
    {
        $base = rtrim(trim($this->rapidApiUrl), '/');
        if ($base === '') {
            return $path;
        }

        // Avoid double slashes.
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * @param array<mixed> $payload
     * @return array<int, mixed>
     */
    private function extractHotelList(array $payload): array
    {
        $candidates = [
            $payload['data']['hotels'] ?? null,
            $payload['data']['results'] ?? null,
            $payload['data']['properties'] ?? null,
            $payload['data'] ?? null,
            $payload['result'] ?? null,
            $payload['results'] ?? null,
            $payload['hotels'] ?? null,
            $payload['properties'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    /**
     * @param array<mixed> $hotel
     */
    private function extractAddress(array $hotel, string $destination): string
    {
        $parts = [];

        $directAddress = $this->pickString($hotel, ['address', 'address_line', 'full_address']);
        if ($directAddress !== null) {
            $parts[] = $directAddress;
        }

        foreach (['location', 'hotel_address', 'address_obj'] as $nestedKey) {
            $nested = $hotel[$nestedKey] ?? null;
            if (!is_array($nested)) {
                continue;
            }

            $nestedAddress = $this->pickString($nested, ['address', 'address1', 'line1', 'street', 'formatted']);
            if ($nestedAddress !== null) {
                $parts[] = $nestedAddress;
            }

            $nestedCity = $this->pickString($nested, ['city', 'city_name', 'locality']);
            if ($nestedCity !== null) {
                $parts[] = $nestedCity;
            }

            $nestedCountry = $this->pickString($nested, ['country', 'country_code']);
            if ($nestedCountry !== null) {
                $parts[] = $nestedCountry;
            }
        }

        if ($parts === []) {
            return 'Adresse non disponible, ' . $destination;
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    /**
     * @param array<mixed> $hotel
     */
    private function extractRating(array $hotel): ?float
    {
        foreach (['rating', 'review_score', 'reviewScore', 'stars'] as $key) {
            if (isset($hotel[$key]) && is_numeric($hotel[$key])) {
                $value = (float) $hotel[$key];
                if ($value > 5.0) {
                    $value = $value / 2;
                }

                return round($value, 1);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $hotel
     */
    private function extractPrice(array $hotel): ?float
    {
        $directPrice = $hotel['price'] ?? $hotel['min_price'] ?? null;
        if (is_numeric($directPrice)) {
            return (float) $directPrice;
        }

        foreach (['price_breakdown', 'priceDetails', 'rate'] as $nestedKey) {
            $nested = $hotel[$nestedKey] ?? null;
            if (!is_array($nested)) {
                continue;
            }

            foreach (['gross_price', 'all_inclusive_price', 'amount', 'value'] as $valueKey) {
                if (isset($nested[$valueKey]) && is_numeric($nested[$valueKey])) {
                    return (float) $nested[$valueKey];
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $hotel
     * @return array{0: ?float, 1: ?float}
     */
    private function extractCoordinates(array $hotel): array
    {
        $lat = null;
        $lng = null;

        foreach (['latitude', 'lat'] as $latKey) {
            if (isset($hotel[$latKey]) && is_numeric($hotel[$latKey])) {
                $lat = (float) $hotel[$latKey];
                break;
            }
        }

        foreach (['longitude', 'lng', 'lon'] as $lngKey) {
            if (isset($hotel[$lngKey]) && is_numeric($hotel[$lngKey])) {
                $lng = (float) $hotel[$lngKey];
                break;
            }
        }

        foreach (['location', 'geoCode', 'coordinates'] as $nestedKey) {
            $nested = $hotel[$nestedKey] ?? null;
            if (!is_array($nested)) {
                continue;
            }

            if ($lat === null) {
                foreach (['latitude', 'lat'] as $latKey) {
                    if (isset($nested[$latKey]) && is_numeric($nested[$latKey])) {
                        $lat = (float) $nested[$latKey];
                        break;
                    }
                }
            }

            if ($lng === null) {
                foreach (['longitude', 'lng', 'lon'] as $lngKey) {
                    if (isset($nested[$lngKey]) && is_numeric($nested[$lngKey])) {
                        $lng = (float) $nested[$lngKey];
                        break;
                    }
                }
            }
        }

        return [$lat, $lng];
    }

    /**
     * @param array<mixed> $hotel
     */
    private function extractImageUrl(array $hotel): ?string
    {
        $direct = $this->pickString($hotel, ['image_url', 'photo_url', 'main_photo_url', 'thumbnail']);
        if ($direct !== null) {
            return $direct;
        }

        foreach (['photos', 'images', 'photoUrls'] as $imagesKey) {
            $images = $hotel[$imagesKey] ?? null;
            if (!is_array($images) || !array_is_list($images) || $images === []) {
                continue;
            }

            $first = $images[0];
            if (is_string($first) && $first !== '') {
                return $first;
            }

            if (is_array($first)) {
                $fromArray = $this->pickString($first, ['url', 'src', 'image_url', 'photo_url']);
                if ($fromArray !== null) {
                    return $fromArray;
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $data
     * @param array<int, string> $keys
     */
    private function pickString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                continue;
            }

            if (is_string($data[$key])) {
                $value = trim($data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function guessType(string $name): string
    {
        $lower = mb_strtolower($name);
        return match(true) {
            str_contains($lower, 'resort')    => 'Resort',
            str_contains($lower, 'villa')     => 'Villa',
            str_contains($lower, 'hostel')    => 'Hostel',
            str_contains($lower, 'auberge')   => 'Auberge',
            str_contains($lower, 'appartement'),
            str_contains($lower, 'apartment') => 'Appartement',
            str_contains($lower, 'guest'),
            str_contains($lower, 'maison')    => "Maison d'hôtes",
            str_contains($lower, 'bungalow')  => 'Bungalow',
            default                           => 'Hotel',
        };
    }

    private function buildFallbackData(string $destination = 'Paris'): array
    {
        return [
            ['name' => 'Grand Hôtel de ' . $destination,        'type' => 'Hotel',      'price' => 129.0, 'address' => '12 Rue Principale, '   . $destination, 'rating' => 4.2, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Villa ' . $destination,                  'type' => 'Villa',      'price' => 245.0, 'address' => '5 Avenue du Soleil, '   . $destination, 'rating' => 4.7, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Auberge de ' . $destination,             'type' => 'Auberge',    'price' => 55.0,  'address' => '3 Impasse des Peintres, '. $destination, 'rating' => 3.9, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Appartement ' . $destination . ' Centre','type' => 'Appartement','price' => 89.0,  'address' => '18 Rue du Centre, '      . $destination, 'rating' => 4.5, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Hostel Le Voyageur - ' . $destination,   'type' => 'Hostel',     'price' => 28.0,  'address' => '7 Rue du Voyageur, '     . $destination, 'rating' => 4.0, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Résidence Panorama ' . $destination,     'type' => 'Appartement','price' => 102.0, 'address' => '42 Rue des Horizons, '   . $destination, 'rating' => 4.3, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Boutique Hôtel Central ' . $destination, 'type' => 'Hotel',      'price' => 138.0, 'address' => '9 Boulevard Central, '  . $destination, 'rating' => 4.4, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Maison d\'hôtes du Parc ' . $destination,'type' => "Maison d'hôtes",'price' => 74.0,'address' => '11 Rue du Parc, '        . $destination, 'rating' => 4.1, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Resort Azure ' . $destination,           'type' => 'Resort',     'price' => 210.0, 'address' => '1 Route Côtière, '      . $destination, 'rating' => 4.8, 'latitude' => null, 'longitude' => null, 'image_url' => null],
            ['name' => 'Suites Urbaines ' . $destination,        'type' => 'Hotel',      'price' => 119.0, 'address' => '27 Avenue Liberté, '    . $destination, 'rating' => 4.2, 'latitude' => null, 'longitude' => null, 'image_url' => null],
        ];
    }
}