<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class WeatherService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    private const WEATHER_EMOJIS = [
        // Thunderstorm
        200 => '⛈️', 201 => '⛈️', 202 => '⛈️', 210 => '🌩️', 211 => '🌩️',
        212 => '🌩️', 221 => '🌩️', 230 => '⛈️', 231 => '⛈️', 232 => '⛈️',
        // Drizzle
        300 => '🌦️', 301 => '🌦️', 302 => '🌦️', 310 => '🌦️', 311 => '🌦️',
        312 => '🌦️', 313 => '🌦️', 314 => '🌦️', 321 => '🌦️',
        // Rain
        500 => '🌧️', 501 => '🌧️', 502 => '🌧️', 503 => '🌧️', 504 => '🌧️',
        511 => '❄️', 520 => '🌧️', 521 => '🌧️', 522 => '🌧️', 531 => '🌧️',
        // Snow
        600 => '❄️', 601 => '❄️', 602 => '❄️', 611 => '🌨️', 612 => '🌨️',
        613 => '🌨️', 615 => '🌨️', 616 => '🌨️', 620 => '🌨️', 621 => '🌨️', 622 => '🌨️',
        // Atmosphere
        701 => '🌫️', 711 => '🌫️', 721 => '🌫️', 731 => '🌫️', 741 => '🌫️',
        751 => '🌫️', 761 => '🌫️', 762 => '🌫️', 771 => '💨', 781 => '🌪️',
        // Clear
        800 => '☀️',
        // Clouds
        801 => '🌤️', 802 => '⛅', 803 => '🌥️', 804 => '☁️',
    ];

    public function __construct(
        string $openWeatherMapApiKey,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->apiKey = $openWeatherMapApiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Get weather forecast emojis for multiple dates at given coordinates.
     * Returns array keyed by 'Y-m-d' => emoji string.
     */
    public function getWeatherEmojisForDates(float $lat, float $lon, array $dates): array
    {
        $result = [];

        // OpenWeatherMap free tier: 5-day/3-hour forecast
        // For dates beyond 5 days, we can't get forecast
        try {
            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/forecast', [
                'query' => [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                ],
            ]);

            $data = $response->toArray();

            // Group forecast by date, pick the midday (12:00) entry or closest
            $forecastByDate = [];
            foreach ($data['list'] ?? [] as $entry) {
                $dt = new \DateTimeImmutable('@' . $entry['dt']);
                $dateKey = $dt->format('Y-m-d');
                $hour = (int) $dt->format('H');

                if (!isset($forecastByDate[$dateKey]) || abs($hour - 12) < abs($forecastByDate[$dateKey]['hour'] - 12)) {
                    $forecastByDate[$dateKey] = [
                        'hour' => $hour,
                        'weather_id' => $entry['weather'][0]['id'] ?? 800,
                    ];
                }
            }

            // Also get current weather for today
            $todayResponse = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                'query' => [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                ],
            ]);
            $todayData = $todayResponse->toArray();
            $todayKey = (new \DateTimeImmutable())->format('Y-m-d');
            if (!isset($forecastByDate[$todayKey]) && isset($todayData['weather'][0]['id'])) {
                $forecastByDate[$todayKey] = [
                    'hour' => 12,
                    'weather_id' => $todayData['weather'][0]['id'],
                ];
            }

            foreach ($dates as $date) {
                $dateKey = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
                if (isset($forecastByDate[$dateKey])) {
                    $weatherId = $forecastByDate[$dateKey]['weather_id'];
                    $result[$dateKey] = self::WEATHER_EMOJIS[$weatherId] ?? '🌡️';
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('OpenWeatherMap API error: ' . $e->getMessage());
        }

        return $result;
    }
}
