<?php

namespace App\Service;

class FlightScraperService
{
    /**
     * Mapping ville → code IATA aéroport principal.
     */
    private const IATA_CODES = [
        // Tunisie
        'tunis'         => 'TUN',
        'monastir'      => 'MIR',
        'enfidha'       => 'NBE',
        'djerba'        => 'DJE',
        'tozeur'        => 'TOE',
        'sfax'          => 'SFA',
        'tabarka'       => 'TBJ',
        'gafsa'         => 'GAF',
        // France
        'paris'         => 'CDG',
        'lyon'          => 'LYS',
        'marseille'     => 'MRS',
        'nice'          => 'NCE',
        'toulouse'      => 'TLS',
        'bordeaux'      => 'BOD',
        'nantes'        => 'NTE',
        'strasbourg'    => 'SXB',
        'lille'         => 'LIL',
        'montpellier'   => 'MPL',
        // Europe
        'london'        => 'LHR',
        'londres'       => 'LHR',
        'rome'          => 'FCO',
        'milan'         => 'MXP',
        'madrid'        => 'MAD',
        'barcelone'     => 'BCN',
        'barcelona'     => 'BCN',
        'amsterdam'     => 'AMS',
        'bruxelles'     => 'BRU',
        'brussels'      => 'BRU',
        'berlin'        => 'BER',
        'francfort'     => 'FRA',
        'frankfurt'     => 'FRA',
        'munich'        => 'MUC',
        'vienne'        => 'VIE',
        'vienna'        => 'VIE',
        'zurich'        => 'ZRH',
        'geneve'        => 'GVA',
        'geneva'        => 'GVA',
        'lisbonne'      => 'LIS',
        'lisbon'        => 'LIS',
        'porto'         => 'OPO',
        'athenes'       => 'ATH',
        'athens'        => 'ATH',
        'prague'        => 'PRG',
        'varsovie'      => 'WAW',
        'warsaw'        => 'WAW',
        'budapest'      => 'BUD',
        'dublin'        => 'DUB',
        'copenhague'    => 'CPH',
        'copenhagen'    => 'CPH',
        'stockholm'     => 'ARN',
        'oslo'          => 'OSL',
        'helsinki'       => 'HEL',
        // Moyen-Orient / Afrique
        'istanbul'      => 'IST',
        'dubai'         => 'DXB',
        'doha'          => 'DOH',
        'le caire'      => 'CAI',
        'cairo'         => 'CAI',
        'casablanca'    => 'CMN',
        'marrakech'     => 'RAK',
        'alger'         => 'ALG',
        'algiers'       => 'ALG',
        'tripoli'       => 'TIP',
        'dakar'         => 'DSS',
        'abidjan'       => 'ABJ',
        'nairobi'       => 'NBO',
        'johannesburg'  => 'JNB',
        'le cap'        => 'CPT',
        'cape town'     => 'CPT',
        // Asie & Amériques
        'new york'      => 'JFK',
        'los angeles'   => 'LAX',
        'montreal'      => 'YUL',
        'toronto'       => 'YYZ',
        'tokyo'         => 'NRT',
        'bangkok'       => 'BKK',
        'singapour'     => 'SIN',
        'singapore'     => 'SIN',
        'kuala lumpur'  => 'KUL',
        'pekin'         => 'PEK',
        'beijing'       => 'PEK',
        'shanghai'      => 'PVG',
        'hong kong'     => 'HKG',
        'mumbai'        => 'BOM',
        'delhi'         => 'DEL',
        'bali'          => 'DPS',
        'sydney'        => 'SYD',
        'sao paulo'     => 'GRU',
        'mexico'        => 'MEX',
        'buenos aires'  => 'EZE',
    ];

    /**
     * Noms des aéroports pour l'affichage.
     */
    private const AIRPORT_NAMES = [
        'TUN' => 'Tunis-Carthage',
        'MIR' => 'Monastir Habib Bourguiba',
        'NBE' => 'Enfidha-Hammamet',
        'DJE' => 'Djerba-Zarzis',
        'CDG' => 'Paris Charles de Gaulle',
        'LYS' => 'Lyon Saint-Exupéry',
        'MRS' => 'Marseille Provence',
        'NCE' => 'Nice Côte d\'Azur',
        'LHR' => 'London Heathrow',
        'FCO' => 'Roma Fiumicino',
        'MXP' => 'Milano Malpensa',
        'MAD' => 'Madrid Barajas',
        'BCN' => 'Barcelona El Prat',
        'AMS' => 'Amsterdam Schiphol',
        'BRU' => 'Bruxelles-National',
        'BER' => 'Berlin Brandenburg',
        'FRA' => 'Frankfurt am Main',
        'MUC' => 'München Franz Josef Strauss',
        'IST' => 'Istanbul Airport',
        'DXB' => 'Dubai International',
        'DOH' => 'Doha Hamad',
        'CAI' => 'Le Caire',
        'CMN' => 'Casablanca Mohammed V',
        'RAK' => 'Marrakech Ménara',
        'ALG' => 'Alger Houari Boumediene',
        'JFK' => 'New York JFK',
        'NRT' => 'Tokyo Narita',
        'BKK' => 'Bangkok Suvarnabhumi',
    ];

    /**
     * Compagnies aériennes principales par route depuis Tunis.
     */
    private const AIRLINES_BY_ROUTE = [
        'CDG' => ['Tunisair', 'Transavia', 'Nouvelair'],
        'LYS' => ['Tunisair', 'Transavia'],
        'MRS' => ['Tunisair', 'Transavia', 'Nouvelair'],
        'NCE' => ['Tunisair', 'Transavia'],
        'TLS' => ['Tunisair'],
        'BOD' => ['Tunisair'],
        'NTE' => ['Transavia'],
        'LIL' => ['Tunisair'],
        'LHR' => ['Tunisair', 'British Airways'],
        'FCO' => ['Tunisair', 'Nouvelair'],
        'MXP' => ['Tunisair', 'Nouvelair'],
        'MAD' => ['Tunisair'],
        'BCN' => ['Tunisair', 'Vueling'],
        'AMS' => ['Tunisair', 'Transavia'],
        'BRU' => ['Tunisair', 'TUI fly'],
        'BER' => ['Tunisair'],
        'FRA' => ['Tunisair', 'Lufthansa'],
        'MUC' => ['Tunisair', 'Lufthansa'],
        'VIE' => ['Tunisair', 'Austrian'],
        'ZRH' => ['Tunisair', 'Swiss'],
        'GVA' => ['Tunisair'],
        'IST' => ['Tunisair', 'Turkish Airlines'],
        'DXB' => ['Tunisair', 'Emirates', 'flydubai'],
        'DOH' => ['Tunisair', 'Qatar Airways'],
        'CAI' => ['Tunisair', 'EgyptAir'],
        'CMN' => ['Tunisair', 'Royal Air Maroc'],
        'RAK' => ['Tunisair', 'Royal Air Maroc'],
        'ALG' => ['Tunisair', 'Air Algérie'],
        'DSS' => ['Tunisair'],
        'JFK' => ['Tunisair', 'Turkish Airlines', 'Air France'],
        'NRT' => ['Turkish Airlines', 'Emirates'],
        'BKK' => ['Turkish Airlines', 'Emirates', 'Qatar Airways'],
        'MIR' => ['Tunisair', 'Nouvelair'],
        'DJE' => ['Tunisair', 'Nouvelair'],
    ];

    public function isConfigured(): bool
    {
        return true; // Pas d'API key nécessaire
    }

    /**
     * Génère les liens de recherche de vols sur Kayak, Google Flights, etc.
     *
     * @return array{status: string, message?: string, flights: list<array>, count?: int, links?: array}
     */
    public function searchFlights(string $origin, string $destination, string $date): array
    {
        // Générer les URLs de recherche
        $kayakUrl = sprintf(
            'https://www.kayak.fr/flights/%s-%s/%s?sort=bestflight_a&fs=stops=0',
            urlencode($origin),
            urlencode($destination),
            urlencode($date)
        );

        $googleFlightsUrl = sprintf(
            'https://www.google.com/travel/flights?q=Flights+from+%s+to+%s+on+%s&curr=EUR&hl=fr',
            urlencode($origin),
            urlencode($destination),
            urlencode($date)
        );

        $skyscannerUrl = sprintf(
            'https://www.skyscanner.fr/transport/vols/%s/%s/%s/?adultes=1&cabinclass=economy',
            strtolower($origin),
            strtolower($destination),
            str_replace('-', '', substr($date, 2)) // yymmdd
        );

        $originName = self::AIRPORT_NAMES[$origin] ?? $origin;
        $destName = self::AIRPORT_NAMES[$destination] ?? $destination;
        $airlines = self::AIRLINES_BY_ROUTE[$destination] ?? ['Tunisair'];

        // Construire les "vols" comme plateformes de recherche
        $flights = [
            [
                'platform'    => 'Kayak',
                'icon'        => '🔍',
                'url'         => $kayakUrl,
                'description' => 'Comparateur de vols avec les meilleurs prix. Filtres avancés, alertes de prix.',
                'color'       => '#ff6900',
                'airlines'    => $airlines,
            ],
            [
                'platform'    => 'Google Flights',
                'icon'        => '✈️',
                'url'         => $googleFlightsUrl,
                'description' => 'Recherche Google avec calendrier de prix et graphiques de tendances.',
                'color'       => '#4285f4',
                'airlines'    => $airlines,
            ],
            [
                'platform'    => 'Skyscanner',
                'icon'        => '🌐',
                'url'         => $skyscannerUrl,
                'description' => 'Compare des centaines de compagnies et d\'agences de voyage.',
                'color'       => '#0770e3',
                'airlines'    => $airlines,
            ],
        ];

        return [
            'status'      => 'ok',
            'flights'     => $flights,
            'count'       => count($flights),
            'origin'      => $origin,
            'destination' => $destination,
            'origin_name' => $originName,
            'dest_name'   => $destName,
            'date'        => $date,
            'airlines'    => $airlines,
        ];
    }

    /**
     * Résoudre un nom de ville en code IATA.
     */
    public function resolveIataCode(string $cityName): ?string
    {
        $normalized = mb_strtolower(trim($cityName));

        if (isset(self::IATA_CODES[$normalized])) {
            return self::IATA_CODES[$normalized];
        }

        foreach (self::IATA_CODES as $city => $code) {
            if (str_contains($normalized, $city) || str_contains($city, $normalized)) {
                return $code;
            }
        }

        if (preg_match('/^[A-Z]{3}$/', trim($cityName))) {
            return trim($cityName);
        }

        return null;
    }

    public function getIataCodes(): array
    {
        return self::IATA_CODES;
    }
}
