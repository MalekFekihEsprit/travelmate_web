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

        if (empty($hotels)) {
            return $this->buildFallbackData($destination);
        }

        return $hotels;
    }

    private function fetchHotels(string $destination, int $maxResults): array
    {
        try {
            $checkIn = (new \DateTimeImmutable('+7 days'))->format('Y-m-d');
            $checkOut = (new \DateTimeImmutable('+9 days'))->format('Y-m-d');

            $query = [
                // Common RapidAPI hotel provider params
                'query' => $destination,
                'destination' => $destination,
                'location' => $destination,
                'city_name' => $destination,
                'adults_number' => 2,
                'room_number' => 1,
                'checkin_date' => $checkIn,
                'checkout_date' => $checkOut,
                'arrival_date' => $checkIn,
                'departure_date' => $checkOut,
                'currency_code' => $this->rapidApiCurrency,
                'currency' => $this->rapidApiCurrency,
                'locale' => $this->rapidApiLanguage,
                'languagecode' => $this->rapidApiLanguage,
            ];

            $response = $this->httpClient->request('GET', $this->rapidApiUrl, [
                'headers' => [
                    'x-rapidapi-key' => $this->rapidApiKey,
                    'x-rapidapi-host' => $this->rapidApiHost,
                ],
                'query' => $query,
                'timeout' => 25,
            ]);

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