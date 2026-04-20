<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class RestCountriesService
{
    private const API_BASE_URL = 'https://restcountries.com/v3.1/name/';
    private const API_FIELDS = 'fields=name,currencies,languages,flags,region,subregion';
    private const API_CCA2_FIELDS = 'fields=cca2,name';

    private HttpClientInterface $httpClient;
    
    /** @var array<string, string|null> Cache for country name to CCA2 lookups */
    private array $cca2Cache = [];

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }
    
    /**. 
     * Fetch country data from RESTcountries API
     * 
     * @param string $countryName The name of the country
        * @return array|null Array with 'currency', 'languages', 'flag', 'region' keys, or null if not found
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
     * Get CCA2 code (e.g., "TN" for Tunisia) from country name
     * Uses caching to avoid repeated API calls
     * 
     * @param string $countryName The name of the country (e.g., "Tunisia")
     * @return string|null The CCA2 code (e.g., "TN") or null if not found
     */
    public function getCCA2Code(string $countryName): ?string
    {
        if (empty($countryName)) {
            return null;
        }

        $normalizedName = strtolower(trim($countryName));
        
        // Check cache first
        if (isset($this->cca2Cache[$normalizedName])) {
            return $this->cca2Cache[$normalizedName];
        }

        try {
            $url = self::API_BASE_URL . urlencode($countryName) . '?' . self::API_CCA2_FIELDS;
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
            ]);
            
            if ($response->getStatusCode() !== 200) {
                $this->cca2Cache[$normalizedName] = null;
                return null;
            }

            $data = $response->toArray();
            
            if (empty($data) || !is_array($data)) {
                $this->cca2Cache[$normalizedName] = null;
                return null;
            }

            // Get the first country result
            $country = $data[0];
            $cca2 = isset($country['cca2']) && is_string($country['cca2']) 
                ? strtoupper(trim($country['cca2']))
                : null;

            // Cache the result (store with lowercase key for case-insensitive lookup)
            $this->cca2Cache[$normalizedName] = $cca2;
            
            return $cca2;
        } catch (ClientExceptionInterface | ServerExceptionInterface $e) {
            $this->cca2Cache[$normalizedName] = null;
            return null;
        } catch (\Exception $e) {
            $this->cca2Cache[$normalizedName] = null;
            return null;
        }
    }

    /**
     * Parse country data from API response
     * 
     * @param array $country The country data from API
     * @return array Array with currency, languages, flag, and region
     */
    private function parseCountryData(array $country): array
    {
        $currency = $this->extractCurrency($country);
        $languages = $this->extractLanguages($country);
        $flag = $this->extractFlag($country);
        $region = $this->extractRegion($country);

        return [
            'currency' => $currency,
            'languages' => $languages,
            'flag' => $flag,
            'region' => $region,
        ];
    }

    /**
     * Extract region from country data
     *
     * @param array $country The country data
     * @return string Region name
     */
    private function extractRegion(array $country): string
    {
        if (isset($country['region']) && is_string($country['region']) && trim($country['region']) !== '') {
            return trim($country['region']);
        }

        return '';
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
     * Extract flag image URL from country data
     * 
     * @param array $country The country data
     * @return string Flag image URL, with alt text as fallback
     */
    private function extractFlag(array $country): string
    {
        if (isset($country['flags']) && is_array($country['flags'])) {
            return $country['flags']['png']
                ?? ($country['flags']['svg']
                    ?? ($country['flags']['alt'] ?? ''));
        }

        return '';
    }
}
