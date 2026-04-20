<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Inflationservice
{
    private const BASE_URL  = 'https://api.worldbank.org/v2/country';
    private const INDICATOR = 'FP.CPI.TOTL.ZG';
    
    // Mapping devise -> pays
    private const CURRENCY_TO_COUNTRY = [
        'TND' => ['code' => 'TN', 'name' => 'Tunisie', 'currency_name' => 'Dinar Tunisien'],
        'EUR' => ['code' => 'EU', 'name' => 'Zone Euro', 'currency_name' => 'Euro'],
        'USD' => ['code' => 'US', 'name' => 'États-Unis', 'currency_name' => 'Dollar US'],
        'GBP' => ['code' => 'GB', 'name' => 'Royaume-Uni', 'currency_name' => 'Livre Sterling'],
        'JPY' => ['code' => 'JP', 'name' => 'Japon', 'currency_name' => 'Yen Japonais'],
        'CAD' => ['code' => 'CA', 'name' => 'Canada', 'currency_name' => 'Dollar Canadien'],
        'CHF' => ['code' => 'CH', 'name' => 'Suisse', 'currency_name' => 'Franc Suisse'],
        'MAD' => ['code' => 'MA', 'name' => 'Maroc', 'currency_name' => 'Dirham Marocain'],
        'TRY' => ['code' => 'TR', 'name' => 'Turquie', 'currency_name' => 'Lire Turque'],
        'CNY' => ['code' => 'CN', 'name' => 'Chine', 'currency_name' => 'Yuan Chinois'],
        'RUB' => ['code' => 'RU', 'name' => 'Russie', 'currency_name' => 'Rouble Russe'],
        'INR' => ['code' => 'IN', 'name' => 'Inde', 'currency_name' => 'Roupie Indienne'],
        'BRL' => ['code' => 'BR', 'name' => 'Brésil', 'currency_name' => 'Real Brésilien'],
        'AUD' => ['code' => 'AU', 'name' => 'Australie', 'currency_name' => 'Dollar Australien'],
        'ZAR' => ['code' => 'ZA', 'name' => 'Afrique du Sud', 'currency_name' => 'Rand Sud-Africain'],
        'DZD' => ['code' => 'DZ', 'name' => 'Algérie', 'currency_name' => 'Dinar Algérien'],
        'EGP' => ['code' => 'EG', 'name' => 'Égypte', 'currency_name' => 'Livre Égyptienne'],
        'SAR' => ['code' => 'SA', 'name' => 'Arabie Saoudite', 'currency_name' => 'Riyal Saoudien'],
        'AED' => ['code' => 'AE', 'name' => 'Émirats Arabes Unis', 'currency_name' => 'Dirham Émirati'],
        'SEK' => ['code' => 'SE', 'name' => 'Suède', 'currency_name' => 'Couronne Suédoise'],
        'NOK' => ['code' => 'NO', 'name' => 'Norvège', 'currency_name' => 'Couronne Norvégienne'],
        'DKK' => ['code' => 'DK', 'name' => 'Danemark', 'currency_name' => 'Couronne Danoise'],
        'PLN' => ['code' => 'PL', 'name' => 'Pologne', 'currency_name' => 'Zloty Polonais'],
        'CZK' => ['code' => 'CZ', 'name' => 'République Tchèque', 'currency_name' => 'Couronne Tchèque'],
        'HUF' => ['code' => 'HU', 'name' => 'Hongrie', 'currency_name' => 'Forint Hongrois'],
    ];
    
    // Données d'inflation statiques pour fallback
    private const STATIC_INFLATION = [
        'TN' => ['rate' => 8.2, 'trend' => 'up', 'year' => 2024],
        'EU' => ['rate' => 4.5, 'trend' => 'stable', 'year' => 2024],
        'US' => ['rate' => 3.5, 'trend' => 'down', 'year' => 2024],
        'GB' => ['rate' => 4.8, 'trend' => 'up', 'year' => 2024],
        'JP' => ['rate' => 2.8, 'trend' => 'up', 'year' => 2024],
        'CA' => ['rate' => 3.2, 'trend' => 'stable', 'year' => 2024],
        'CH' => ['rate' => 2.1, 'trend' => 'down', 'year' => 2024],
        'MA' => ['rate' => 6.5, 'trend' => 'up', 'year' => 2024],
        'TR' => ['rate' => 45.0, 'trend' => 'up', 'year' => 2024],
        'CN' => ['rate' => 2.5, 'trend' => 'down', 'year' => 2024],
        'RU' => ['rate' => 7.5, 'trend' => 'up', 'year' => 2024],
        'IN' => ['rate' => 5.5, 'trend' => 'stable', 'year' => 2024],
        'BR' => ['rate' => 4.2, 'trend' => 'down', 'year' => 2024],
        'AU' => ['rate' => 3.8, 'trend' => 'stable', 'year' => 2024],
        'ZA' => ['rate' => 5.9, 'trend' => 'up', 'year' => 2024],
        'DZ' => ['rate' => 7.2, 'trend' => 'up', 'year' => 2024],
        'EG' => ['rate' => 24.5, 'trend' => 'up', 'year' => 2024],
        'SA' => ['rate' => 2.8, 'trend' => 'stable', 'year' => 2024],
        'AE' => ['rate' => 3.1, 'trend' => 'stable', 'year' => 2024],
        'SE' => ['rate' => 3.9, 'trend' => 'down', 'year' => 2024],
        'NO' => ['rate' => 3.5, 'trend' => 'stable', 'year' => 2024],
        'DK' => ['rate' => 3.2, 'trend' => 'down', 'year' => 2024],
        'PL' => ['rate' => 5.8, 'trend' => 'down', 'year' => 2024],
        'CZ' => ['rate' => 5.2, 'trend' => 'down', 'year' => 2024],
        'HU' => ['rate' => 6.1, 'trend' => 'down', 'year' => 2024],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache
    ) {}

    /**
     * Obtient l'inflation basée sur la devise
     */
    public function getInflationByCurrency(string $currency): array
    {
        $currency = strtoupper($currency);
        
        if (!isset(self::CURRENCY_TO_COUNTRY[$currency])) {
            return [
                'success' => false,
                'error' => "Devise {$currency} non supportée pour l'inflation",
                'fallback_rate' => 5.0
            ];
        }
        
        $countryInfo = self::CURRENCY_TO_COUNTRY[$currency];
        $countryCode = $countryInfo['code'];
        
        // Essayer l'API World Bank
        try {
            $apiData = $this->getLatestInflation($countryCode);
            if ($apiData['rate'] !== null) {
                return [
                    'success' => true,
                    'currency' => $currency,
                    'currency_name' => $countryInfo['currency_name'],
                    'country_code' => $countryCode,
                    'country_name' => $countryInfo['name'],
                    'rate' => $apiData['rate'],
                    'year' => $apiData['year'],
                    'source' => 'World Bank API',
                    'is_estimated' => false,
                    'advice' => $this->getInflationAdvice($apiData['rate'], $countryInfo['name'])
                ];
            }
        } catch (\Exception $e) {
            // Fallback
        }
        
        // Fallback sur données statiques
        $staticData = self::STATIC_INFLATION[$countryCode] ?? ['rate' => 5.0, 'year' => date('Y'), 'trend' => 'stable'];
        
        return [
            'success' => true,
            'currency' => $currency,
            'currency_name' => $countryInfo['currency_name'],
            'country_code' => $countryCode,
            'country_name' => $countryInfo['name'],
            'rate' => $staticData['rate'],
            'year' => $staticData['year'],
            'trend' => $staticData['trend'],
            'source' => 'Base de données statique',
            'is_estimated' => true,
            'advice' => $this->getInflationAdvice($staticData['rate'], $countryInfo['name'])
        ];
    }

    public function getLatestInflation(string $countryCode): array
    {
        $code     = strtoupper($countryCode);
        $cacheKey = 'inflation_' . $code;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter(86400);

            $url      = self::BASE_URL . '/' . $code . '/indicator/' . self::INDICATOR;
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'format'   => 'json',
                    'per_page' => 5,
                    'mrv'      => 1,
                ]
            ]);

            $data    = $response->toArray();
            $entries = $data[1] ?? [];
            $entries = array_values(array_filter($entries, fn($e) => $e['value'] !== null));
            $latest  = $entries[0] ?? null;

            return [
                'country'   => $latest['country']['value'] ?? $code,
                'year'      => $latest['date'] ?? null,
                'rate'      => $latest !== null ? round($latest['value'], 2) : null,
                'indicator' => 'CPI (annual %)',
            ];
        });
    }

    public function getHistoricalInflation(string $countryCode, int $years = 10): array
    {
        $code = strtoupper($countryCode);

        return $this->cache->get('inflation_history_' . $code . '_' . $years, function (ItemInterface $item) use ($code, $years) {
            $item->expiresAfter(86400);

            $url      = self::BASE_URL . '/' . $code . '/indicator/' . self::INDICATOR;
            $response = $this->httpClient->request('GET', $url, [
                'query' => ['format' => 'json', 'per_page' => $years]
            ]);

            $data    = $response->toArray();
            $entries = $data[1] ?? [];

            return array_values(array_filter(
                array_map(fn($e) => [
                    'year'  => $e['date'],
                    'value' => $e['value'] !== null ? round($e['value'], 2) : null,
                ], $entries),
                fn($e) => $e['value'] !== null
            ));
        });
    }
    
    public function adjustForInflation(float $montant, string $fromYear, string $toYear, string $devise = 'TND'): string
    {
        $inflationData = $this->getInflationByCurrency($devise);
        $inflationRate = $inflationData['rate'] ?? 5.0;
        
        $years = (int) $toYear - (int) $fromYear;
        if ($years <= 0) {
            return number_format($montant, 2, ',', ' ') . ' ' . $devise . ' (aucun ajustement)';
        }
        
        $adjusted = $montant * pow(1 + ($inflationRate / 100), $years);
        $difference = $adjusted - $montant;
        
        return sprintf(
            '%s %s en %s → %s %s en %s (inflation: +%s%%, +%s %s)',
            number_format($montant, 2, ',', ' '),
            $devise,
            $fromYear,
            number_format($adjusted, 2, ',', ' '),
            $devise,
            $toYear,
            number_format($inflationRate * $years, 1),
            number_format($difference, 2, ',', ' '),
            $devise
        );
    }
    
    private function getInflationAdvice(?float $rate, string $country): string
    {
        if ($rate === null) {
            return "Données d'inflation non disponibles pour {$country}.";
        }
        
        if ($rate > 10) {
            return "⚠️ Inflation très élevée ({$rate}%) à {$country}. Prévoyez un budget majoré de 30% et privilégiez les paiements en avance.";
        } elseif ($rate > 5) {
            return "📈 Inflation élevée ({$rate}%) à {$country}. Augmentez votre budget quotidien de 15-20%.";
        } elseif ($rate > 3) {
            return "📊 Inflation modérée ({$rate}%) à {$country}. Prévoyez une marge de 10% sur votre budget.";
        } elseif ($rate > 0) {
            return "✅ Inflation maîtrisée ({$rate}%) à {$country}. Budget standard recommandé.";
        } else {
            return "📉 Déflation ({$rate}%) à {$country}. Bonne nouvelle pour votre portefeuille !";
        }
    }
}