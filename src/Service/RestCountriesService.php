<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class RestCountriesService
{
    private const API_BASE_URL = 'https://restcountries.com/v3.1/name/';
    private const API_FIELDS = 'fields=name,currencies,languages,flags';

    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }
    
    /**. 
     * Fetch country data from RESTcountries API
     * 
     * @param string $countryName The name of the country
     * @return array|null Array with 'currency', 'languages', 'flag' keys, or null if not found
     */
    public function getCountryData(string $countryName): ?array
    {
        if (empty($countryName)) {
            return null;
        }

        try {
            $url = self::API_BASE_URL . urlencode($countryName) . '?' . self::API_FIELDS;
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
            ]);
            
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            
            if (empty($data) || !is_array($data)) {
                return null;
            }

            // Get the first country result
            $country = $data[0];

            return $this->parseCountryData($country);
        } catch (ClientExceptionInterface | ServerExceptionInterface $e) {
            // Log the error if needed, return null
            return null;
        } catch (\Exception $e) {
            // Handle any other exceptions
            return null;
        }
    }

    /**
     * Parse country data from API response
     * 
     * @param array $country The country data from API
     * @return array Array with currency, languages, and flag
     */
    private function parseCountryData(array $country): array
    {
        $currency = $this->extractCurrency($country);
        $languages = $this->extractLanguages($country);
        $flag = $this->extractFlag($country);

        return [
            'currency' => $currency,
            'languages' => $languages,
            'flag' => $flag,
        ];
    }

    /**
     * Extract currency code from country data
     * 
     * @param array $country The country data
     * @return string Currency code (e.g., 'EUR', 'USD')
     */
    private function extractCurrency(array $country): string
    {
        if (isset($country['currencies']) && is_array($country['currencies']) && !empty($country['currencies'])) {
            $currencyCodes = array_keys($country['currencies']);
            return $currencyCodes[0] ?? 'N/A';
        }

        return 'N/A';
    }

    /**
     * Extract languages from country data
     * 
     * @param array $country The country data
     * @return string Comma-separated language names
     */
    private function extractLanguages(array $country): string
    {
        if (isset($country['languages']) && is_array($country['languages']) && !empty($country['languages'])) {
            $languageNames = array_values($country['languages']);
            return implode(', ', $languageNames);
        }

        return 'N/A';
    }

    /**
     * Extract flag emoji from country data
     * 
     * @param array $country The country data
     * @return string Flag emoji
     */
    private function extractFlag(array $country): string
    {
        if (isset($country['flags']) && is_array($country['flags'])) {
            // Return the emoji flag if available
            return $country['flags']['alt'] ?? ($country['flags']['png'] ?? '🏳️');
        }

        return '🏳️';
    }
}
