<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DestinationSeasonService
{
    private const OPEN_METEO_URL = 'https://archive-api.open-meteo.com/v1/archive';
    private const IDEAL_TEMP_MIN = 18.0;
    private const IDEAL_TEMP_MAX = 30.0;
    private const MAX_PRECIPITATION = 100.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getBestSeason(float $latitude, float $longitude): string
    {
        try {
            $jsonResponse = $this->fetchDailyClimateData($latitude, $longitude);
            $monthlyData = $this->aggregateDailyToMonthly($jsonResponse);

            if ($monthlyData === []) {
                $this->logger->warning('No climate data available, using hemisphere fallback.', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

                return $this->getHemisphereSeason($latitude);
            }

            return $this->calculateBestSeason($monthlyData, $latitude);
        } catch (TransportExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Failed to determine best season, using hemisphere fallback.', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $exception->getMessage(),
            ]);

            return $this->getHemisphereSeason($latitude);
        }
    }

    private function fetchDailyClimateData(float $latitude, float $longitude): string
    {
        $year = (int) (new \DateTimeImmutable('now'))->format('Y') - 1;

        $response = $this->httpClient->request('GET', self::OPEN_METEO_URL, [
            'query' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'start_date' => sprintf('%d-01-01', $year),
                'end_date' => sprintf('%d-12-31', $year),
                'daily' => 'temperature_2m_mean,precipitation_sum',
                'timezone' => 'auto',
            ],
            'timeout' => 20,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Open-Meteo returned status ' . $response->getStatusCode());
        }

        return $response->getContent();
    }

    /**
     * @return array<int, array{monthNumber:int, temperature:float, precipitation:float}>
     */
    private function aggregateDailyToMonthly(string $jsonResponse): array
    {
        $decoded = json_decode($jsonResponse, true);

        if (!is_array($decoded) || !isset($decoded['daily']) || !is_array($decoded['daily'])) {
            return [];
        }

        $daily = $decoded['daily'];
        if (!isset($daily['time'], $daily['temperature_2m_mean'], $daily['precipitation_sum'])) {
            return [];
        }

        $times = $daily['time'];
        $temperatures = $daily['temperature_2m_mean'];
        $precipitations = $daily['precipitation_sum'];

        if (!is_array($times) || !is_array($temperatures) || !is_array($precipitations)) {
            return [];
        }

        $monthMap = [];

        foreach ($times as $index => $dateStr) {
            if (!is_string($dateStr) || !isset($temperatures[$index], $precipitations[$index])) {
                continue;
            }

            $dateParts = explode('-', $dateStr);
            if (count($dateParts) < 2) {
                continue;
            }

            $month = (int) $dateParts[1];
            $temp = (float) $temperatures[$index];
            $precip = (float) $precipitations[$index];

            if (!isset($monthMap[$month])) {
                $monthMap[$month] = ['sumTemp' => 0.0, 'sumPrecip' => 0.0, 'count' => 0];
            }

            $monthMap[$month]['sumTemp'] += $temp;
            $monthMap[$month]['sumPrecip'] += $precip;
            $monthMap[$month]['count']++;
        }

        $monthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            if (isset($monthMap[$month]) && $monthMap[$month]['count'] > 0) {
                $monthlyData[] = [
                    'monthNumber' => $month,
                    'temperature' => $monthMap[$month]['sumTemp'] / $monthMap[$month]['count'],
                    'precipitation' => $monthMap[$month]['sumPrecip'],
                ];
            }
        }

        return $monthlyData;
    }

    /**
     * @param array<int, array{monthNumber:int, temperature:float, precipitation:float}> $monthlyData
     */
    private function calculateBestSeason(array $monthlyData, float $latitude): string
    {
        $northernSeasons = [
            [3, 4, 5],
            [6, 7, 8],
            [9, 10, 11],
            [12, 1, 2],
        ];

        $southernSeasons = [
            [9, 10, 11],
            [12, 1, 2],
            [3, 4, 5],
            [6, 7, 8],
        ];

        $seasonNames = ['Printemps', 'Été', 'Automne', 'Hiver'];
        $seasons = $latitude >= 0 ? $northernSeasons : $southernSeasons;

        $seasonScores = [0.0, 0.0, 0.0, 0.0];
        $goodMonthsCount = [0, 0, 0, 0];
        $totalMonthsCount = [0, 0, 0, 0];

        foreach ($seasons as $index => $seasonMonths) {
            foreach ($seasonMonths as $monthNumber) {
                foreach ($monthlyData as $monthClimate) {
                    if ($monthClimate['monthNumber'] === $monthNumber) {
                        $totalMonthsCount[$index]++;

                        if ($monthClimate['temperature'] >= self::IDEAL_TEMP_MIN
                            && $monthClimate['temperature'] <= self::IDEAL_TEMP_MAX
                            && $monthClimate['precipitation'] <= self::MAX_PRECIPITATION) {
                            $goodMonthsCount[$index]++;
                        }

                        break;
                    }
                }
            }

            if ($totalMonthsCount[$index] > 0) {
                $seasonScores[$index] = $goodMonthsCount[$index] / $totalMonthsCount[$index];
            }
        }

        $bestSeasonIndex = 0;
        $bestScore = $seasonScores[0];

        for ($i = 1; $i < count($seasonScores); $i++) {
            if ($seasonScores[$i] > $bestScore) {
                $bestScore = $seasonScores[$i];
                $bestSeasonIndex = $i;
            }
        }

        if ($bestScore === 0.0) {
            return $this->getTemperatureBasedSeason($monthlyData, $latitude);
        }

        return $seasonNames[$bestSeasonIndex];
    }

    /**
     * @param array<int, array{monthNumber:int, temperature:float, precipitation:float}> $monthlyData
     */
    private function getTemperatureBasedSeason(array $monthlyData, float $latitude): string
    {
        $northernSeasons = [
            [3, 4, 5],
            [6, 7, 8],
            [9, 10, 11],
            [12, 1, 2],
        ];

        $southernSeasons = [
            [9, 10, 11],
            [12, 1, 2],
            [3, 4, 5],
            [6, 7, 8],
        ];

        $seasonNames = ['Printemps', 'Été', 'Automne', 'Hiver'];
        $seasons = $latitude >= 0 ? $northernSeasons : $southernSeasons;

        $seasonAvgs = [0.0, 0.0, 0.0, 0.0];
        $seasonCounts = [0, 0, 0, 0];

        foreach ($seasons as $index => $seasonMonths) {
            foreach ($seasonMonths as $monthNumber) {
                foreach ($monthlyData as $monthClimate) {
                    if ($monthClimate['monthNumber'] === $monthNumber) {
                        $seasonAvgs[$index] += $monthClimate['temperature'];
                        $seasonCounts[$index]++;
                        break;
                    }
                }
            }

            if ($seasonCounts[$index] > 0) {
                $seasonAvgs[$index] /= $seasonCounts[$index];
            }
        }

        $targetTemp = 24.0;
        $bestSeason = 0;
        $smallestDiff = abs($seasonAvgs[0] - $targetTemp);

        for ($i = 1; $i < count($seasonAvgs); $i++) {
            $diff = abs($seasonAvgs[$i] - $targetTemp);
            if ($diff < $smallestDiff) {
                $smallestDiff = $diff;
                $bestSeason = $i;
            }
        }

        return $seasonNames[$bestSeason];
    }

    private function getHemisphereSeason(float $latitude): string
    {
        return $latitude >= 0 ? 'Été' : 'Hiver';
    }
}