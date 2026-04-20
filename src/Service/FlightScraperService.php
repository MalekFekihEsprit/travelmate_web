<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class FlightScraperService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    private const BASE_URL = 'https://api.aviationstack.com/v1';

    // Tunis-Carthage IATA code
    private const ORIGIN_IATA = 'TUN';

    // Map city names to IATA airport codes
    private const CITY_TO_IATA = [
        'paris'      => 'CDG',
        'lyon'       => 'LYS',
        'marseille'  => 'MRS',
        'nice'       => 'NCE',
        'toulouse'   => 'TLS',
        'bordeaux'   => 'BOD',
        'nantes'     => 'NTE',
        'strasbourg' => 'SXB',
        'lille'      => 'LIL',
        'montpellier'=> 'MPL',
        'istanbul'   => 'IST',
        'london'     => 'LHR',
        'londres'    => 'LHR',
        'rome'       => 'FCO',
        'milan'      => 'MXP',
        'madrid'     => 'MAD',
        'barcelona'  => 'BCN',
        'barcelone'  => 'BCN',
        'berlin'     => 'BER',
        'amsterdam'  => 'AMS',
        'dubai'      => 'DXB',
        'doha'       => 'DOH',
        'casablanca' => 'CMN',
        'alger'      => 'ALG',
        'tripoli'    => 'TIP',
        'montreal'   => 'YUL',
        'le caire'   => 'CAI',
        'cairo'      => 'CAI',
        'jeddah'     => 'JED',
        'djerba'     => 'DJE',
        'monastir'   => 'MIR',
        'sfax'       => 'SFA',
        'bruxelles'  => 'BRU',
        'brussels'   => 'BRU',
        'geneve'     => 'GVA',
        'zurich'     => 'ZRH',
        'munich'     => 'MUC',
        'francfort'  => 'FRA',
        'frankfurt'  => 'FRA',
        'vienne'     => 'VIE',
        'vienna'     => 'VIE',
        'lisbonne'   => 'LIS',
        'lisbon'     => 'LIS',
        'athens'     => 'ATH',
        'athenes'    => 'ATH',
        'new york'   => 'JFK',
    ];

    public function __construct(
        string $apiKey,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->apiKey = $apiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Resolve a destination query (city name or IATA code) to an IATA code.
     */
    public function resolveIata(string $query): ?string
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        // If it looks like an IATA code already (2-4 uppercase letters)
        $upper = strtoupper($query);
        if (preg_match('/^[A-Z]{2,4}$/', $upper)) {
            return $upper;
        }

        // Look up in our city map
        $key = mb_strtolower($query);
        if (isset(self::CITY_TO_IATA[$key])) {
            return self::CITY_TO_IATA[$key];
        }

        // Try partial match
        foreach (self::CITY_TO_IATA as $city => $iata) {
            if (str_contains($key, $city) || str_contains($city, $key)) {
                return $iata;
            }
        }

        return null;
    }

    /**
     * Search flights from Tunis to destination on a given date.
     */
    public function searchFlights(string $destinationQuery, string $date): array
    {
        $destIata = $this->resolveIata($destinationQuery);

        if (!$destIata) {
            return [
                'status' => 'error',
                'message' => sprintf(
                    'Destination « %s » non reconnue. Essayez avec un nom de ville (ex : Paris, Nice, Istanbul) ou un code IATA (ex : CDG, NCE, IST).',
                    htmlspecialchars($destinationQuery, \ENT_QUOTES)
                ),
                'flights' => [],
            ];
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/flights', [
                'query' => [
                    'access_key' => $this->apiKey,
                    'dep_iata'   => self::ORIGIN_IATA,
                    'arr_iata'   => $destIata,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            // Check for API errors
            if (isset($data['error'])) {
                $errorMsg = $data['error']['message'] ?? 'Erreur API';
                $errorCode = $data['error']['code'] ?? '';
                $this->logger->warning("AviationStack error [$errorCode]: $errorMsg");

                if ($errorCode === 'usage_limit_reached') {
                    return [
                        'status' => 'error',
                        'message' => 'Limite de requêtes API atteinte pour ce mois. Veuillez réessayer plus tard.',
                        'flights' => [],
                    ];
                }

                return [
                    'status' => 'error',
                    'message' => 'Erreur lors de la recherche de vols : ' . $errorMsg,
                    'flights' => [],
                ];
            }

            if ($statusCode !== 200) {
                $this->logger->warning("AviationStack HTTP $statusCode");
                return [
                    'status' => 'error',
                    'message' => 'L\'API de vols est temporairement indisponible. Veuillez réessayer.',
                    'flights' => [],
                ];
            }

            $flightData = $data['data'] ?? [];

            if (empty($flightData)) {
                return [
                    'status' => 'no_results',
                    'message' => 'Aucun vol trouvé de Tunis vers ' . htmlspecialchars($destinationQuery, \ENT_QUOTES) . ' (' . $destIata . ') aujourd\'hui.',
                    'flights' => [],
                    'destIata' => $destIata,
                ];
            }

            $flights = [];
            foreach ($flightData as $flight) {
                $dep = $flight['departure'] ?? [];
                $arr = $flight['arrival'] ?? [];
                $airline = $flight['airline'] ?? [];
                $flightInfo = $flight['flight'] ?? [];

                $depTime = $dep['scheduled'] ?? $dep['estimated'] ?? null;
                $arrTime = $arr['scheduled'] ?? $arr['estimated'] ?? null;

                // Calculate duration
                $durationMinutes = 0;
                $durationFormatted = '';
                if ($depTime && $arrTime) {
                    try {
                        $depDt = new \DateTime($depTime);
                        $arrDt = new \DateTime($arrTime);
                        $diff = $depDt->diff($arrDt);
                        $durationMinutes = ($diff->h * 60) + $diff->i + ($diff->days * 1440);
                        $durationFormatted = $this->formatDuration($durationMinutes);
                    } catch (\Throwable $e) {
                        $durationFormatted = '';
                    }
                }

                $flightNumber = ($airline['iata'] ?? '') . ($flightInfo['number'] ?? '');
                $airlineName = $airline['name'] ?? 'Inconnu';

                $depIata = $dep['iata'] ?? self::ORIGIN_IATA;
                $arrIata = $arr['iata'] ?? $destIata;
                $depAirport = $dep['airport'] ?? 'Tunis-Carthage';
                $arrAirport = $arr['airport'] ?? $destinationQuery;

                $status = $flight['flight_status'] ?? '';

                $flights[] = [
                    'id'               => $flightNumber ?: uniqid(),
                    'flightNumber'     => $flightNumber,
                    'airlines'         => [$airlineName],
                    'airlineLogos'     => [],
                    'departure'        => $depTime,
                    'arrival'          => $arrTime,
                    'duration'         => $durationMinutes,
                    'durationFormatted'=> $durationFormatted,
                    'stops'            => 0,
                    'stopsLabel'       => 'Direct',
                    'originName'       => $depAirport,
                    'originCode'       => $depIata,
                    'destName'         => $arrAirport,
                    'destCode'         => $arrIata,
                    'status'           => $status,
                    'statusLabel'      => $this->translateStatus($status),
                    'depTerminal'      => $dep['terminal'] ?? null,
                    'depGate'          => $dep['gate'] ?? null,
                    'arrTerminal'      => $arr['terminal'] ?? null,
                    'arrBaggage'       => $arr['baggage'] ?? null,
                    'segments'         => [],
                ];
            }

            // Sort by departure time
            usort($flights, function ($a, $b) {
                return ($a['departure'] ?? '') <=> ($b['departure'] ?? '');
            });

            return [
                'status'   => 'ok',
                'flights'  => $flights,
                'count'    => count($flights),
                'destIata' => $destIata,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('AviationStack searchFlights error: ' . $e->getMessage());

            return [
                'status'  => 'error',
                'message' => 'Erreur lors de la recherche de vols. Veuillez réessayer.',
                'flights' => [],
            ];
        }
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'scheduled' => 'Programmé',
            'active'    => 'En vol',
            'landed'    => 'Atterri',
            'cancelled' => 'Annulé',
            'incident'  => 'Incident',
            'diverted'  => 'Dérouté',
            'delayed'   => 'Retardé',
            default     => $status ?: 'N/A',
        };
    }

    private function formatDuration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m . 'min';
        }
        return $h . 'h' . ($m > 0 ? sprintf('%02d', $m) : '');
    }
}
