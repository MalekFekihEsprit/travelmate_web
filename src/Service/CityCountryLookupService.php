<?php

namespace App\Service;

class CityCountryLookupService
{
    private bool $loaded = false;

    /** @var array<string, string> */
    private array $countries = [];

    /** @var array<string, array<string, string>> */
    private array $countryToCities = [];

    /** @var array<string, array<string, array{latitude?: float, longitude?: float}>> */
    private array $cityCoordinates = [];

    public function __construct(
        private readonly string $citiesJsonPath,
        private readonly RestCountriesService $restCountriesService
    )
    {
    }

    public function isValidCountry(string $country): bool
    {
        $this->load();

        if ($country === '') {
            return false;
        }

        // Convert country name to CCA2 code via REST Countries API
        $cca2Code = $this->restCountriesService->getCCA2Code($country);
        if ($cca2Code === null) {
            return false;
        }

        $countryKey = $this->normalizeCountryKey($cca2Code);

        return isset($this->countries[$countryKey]);
    }

    public function isValidCityForCountry(string $city, string $country): bool
    {
        $this->load();

        if ($city === '' || $country === '') {
            return false;
        }

        // Convert country name to CCA2 code via REST Countries API
        $cca2Code = $this->restCountriesService->getCCA2Code($country);
        if ($cca2Code === null) {
            return false;
        }

        $countryKey = $this->normalizeCountryKey($cca2Code);

        $normalizedCity = $this->normalize($city);
        if ($normalizedCity === '') {
            return false;
        }

        if (!isset($this->countryToCities[$countryKey])) {
            return false;
        }

        return isset($this->countryToCities[$countryKey][$normalizedCity]);
    }

    /**
     * @return array<int, string>
     */
    public function suggestCountries(string $query, int $limit = 8): array
    {
        $this->load();

        $normalizedQuery = $this->normalize($query);
        $results = [];

        foreach ($this->countries as $normalizedCountry => $country) {
            if ($normalizedQuery === '' || str_contains($normalizedCountry, $normalizedQuery)) {
                $results[] = $country;
            }
        }

        sort($results);

        return array_slice($results, 0, $limit);
    }

    /**
     * @return array<int, string>
     */
    public function suggestCities(string $query, ?string $country = null, int $limit = 8): array
    {
        $this->load();

        $normalizedQuery = $this->normalize($query);
        $results = [];

        if ($country !== null && $country !== '') {
            // Convert country name to CCA2 code via REST Countries API
            $cca2Code = $this->restCountriesService->getCCA2Code($country);
            if ($cca2Code === null) {
                return [];
            }

            $countryKey = $this->normalizeCountryKey($cca2Code);

            foreach ($this->countryToCities[$countryKey] ?? [] as $normalizedCity => $city) {
                if ($normalizedQuery === '' || str_contains($normalizedCity, $normalizedQuery)) {
                    $results[] = $city;
                }
            }

            sort($results);

            return array_slice($results, 0, $limit);
        }

        foreach ($this->countryToCities as $cities) {
            foreach ($cities as $normalizedCity => $city) {
                if ($normalizedQuery === '' || str_contains($normalizedCity, $normalizedQuery)) {
                    $results[$normalizedCity] = $city;
                }
            }
        }

        $values = array_values($results);
        sort($values);

        return array_slice($values, 0, $limit);
    }

    /**
     * @return array{is_valid_country: bool, is_valid_city: bool, is_valid_pair: bool}
     */
    public function validate(string $city, string $country): array
    {
        $isValidCountry = $this->isValidCountry($country);
        $isValidCity = $this->normalize($city) !== '';
        $isValidPair = false;

        if ($isValidCountry && $isValidCity) {
            $isValidPair = $this->isValidCityForCountry($city, $country);
            $isValidCity = $isValidPair;
        }

        return [
            'is_valid_country' => $isValidCountry,
            'is_valid_city' => $isValidCity,
            'is_valid_pair' => $isValidPair,
        ];
    }

    /**
     * Get coordinates (latitude, longitude) for a city in a country
     * 
     * @return array{latitude?: float, longitude?: float}|null Array with latitude/longitude or null if not found
     */
    public function getCoordinates(string $city, string $country): ?array
    {
        $this->load();

        if ($city === '' || $country === '') {
            return null;
        }

        // Convert country name to CCA2 code via REST Countries API
        $cca2Code = $this->restCountriesService->getCCA2Code($country);
        if ($cca2Code === null) {
            return null;
        }

        $countryKey = $this->normalizeCountryKey($cca2Code);

        $normalizedCity = $this->normalize($city);
        if ($normalizedCity === '') {
            return null;
        }

        return $this->cityCoordinates[$countryKey][$normalizedCity] ?? null;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!is_file($this->citiesJsonPath)) {
            return;
        }

        $rawJson = file_get_contents($this->citiesJsonPath);
        if (!is_string($rawJson) || $rawJson === '') {
            return;
        }

        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded)) {
            return;
        }

        $this->ingest($decoded);
    }

    /**
     * @param mixed $node
     */
    private function ingest(mixed $node): void
    {
        if (!is_array($node)) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $item) {
                $this->ingest($item);
            }

            return;
        }

        if (isset($node['country']) && is_string($node['country'])) {
            $country = $node['country'];
            $this->addCountry($country);

            $latitude = isset($node['latitude']) && is_numeric($node['latitude']) ? (float)$node['latitude'] : null;
            $longitude = isset($node['longitude']) && is_numeric($node['longitude']) ? (float)$node['longitude'] : null;

            if (isset($node['city']) && is_string($node['city'])) {
                $this->addCityWithCoordinates($country, $node['city'], $latitude, $longitude);
            }

            if (isset($node['name']) && is_string($node['name']) && !isset($node['city'])) {
                $this->addCityWithCoordinates($country, $node['name'], $latitude, $longitude);
            }

            if (isset($node['cities']) && is_array($node['cities'])) {
                foreach ($node['cities'] as $city) {
                    if (is_string($city)) {
                        $this->addCity($country, $city);
                    } elseif (is_array($city)) {
                        $cityName = $city['city'] ?? $city['name'] ?? null;
                        if ($cityName !== null && is_string($cityName)) {
                            $cityLat = isset($city['latitude']) && is_numeric($city['latitude']) ? (float)$city['latitude'] : null;
                            $cityLng = isset($city['longitude']) && is_numeric($city['longitude']) ? (float)$city['longitude'] : null;
                            $this->addCityWithCoordinates($country, $cityName, $cityLat, $cityLng);
                        }
                    }
                }
            }
        }

        foreach ($node as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if (is_string($key) && array_is_list($value)) {
                $onlyStrings = true;
                foreach ($value as $v) {
                    if (!is_string($v)) {
                        $onlyStrings = false;
                        break;
                    }
                }

                if ($onlyStrings) {
                    $this->addCountry($key);
                    foreach ($value as $city) {
                        $this->addCity($key, $city);
                    }

                    continue;
                }

                $onlyObjects = true;
                foreach ($value as $v) {
                    if (!is_array($v)) {
                        $onlyObjects = false;
                        break;
                    }
                }

                if ($onlyObjects) {
                    $this->addCountry($key);
                    foreach ($value as $cityData) {
                        $cityName = $cityData['city'] ?? $cityData['name'] ?? null;
                        if (!is_string($cityName)) {
                            continue;
                        }

                        $latitude = isset($cityData['latitude']) && is_numeric($cityData['latitude']) ? (float) $cityData['latitude'] : null;
                        $longitude = isset($cityData['longitude']) && is_numeric($cityData['longitude']) ? (float) $cityData['longitude'] : null;
                        $this->addCityWithCoordinates($key, $cityName, $latitude, $longitude);
                    }

                    continue;
                }
            }

            $this->ingest($value);
        }
    }

    private function addCountry(string $country): void
    {
        $countryKey = $this->normalizeCountryKey($country);
        if ($countryKey === '') {
            return;
        }

        $this->countries[$countryKey] = trim($country);
        $this->countryToCities[$countryKey] ??= [];
    }

    private function addCity(string $country, string $city): void
    {
        $this->addCityWithCoordinates($country, $city, null, null);
    }

    private function addCityWithCoordinates(string $country, string $city, ?float $latitude = null, ?float $longitude = null): void
    {
        $countryKey = $this->normalizeCountryKey($country);
        $normalizedCity = $this->normalize($city);

        if ($countryKey === '' || $normalizedCity === '') {
            return;
        }

        $this->countries[$countryKey] = trim($country);
        $this->countryToCities[$countryKey] ??= [];
        $this->countryToCities[$countryKey][$normalizedCity] = trim($city);

        // Store coordinates if provided
        if ($latitude !== null || $longitude !== null) {
            $this->cityCoordinates[$countryKey] ??= [];
            $this->cityCoordinates[$countryKey][$normalizedCity] = [];
            if ($latitude !== null) {
                $this->cityCoordinates[$countryKey][$normalizedCity]['latitude'] = $latitude;
            }
            if ($longitude !== null) {
                $this->cityCoordinates[$countryKey][$normalizedCity]['longitude'] = $longitude;
            }
        }
    }

    private function normalizeCountryKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
            return strtoupper($value);
        }

        return $this->normalize($value);
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
