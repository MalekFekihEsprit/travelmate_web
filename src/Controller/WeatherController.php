<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/weather')]
class WeatherController extends AbstractController
{
    // ✅ CORRECTION 1 : Injection de la clé API via le constructeur (bonne pratique Symfony)
    public function __construct(private string $openWeatherApiKey) {}

    #[Route('/forecast', name: 'app_weather_forecast', methods: ['GET'])]
    public function forecast(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        $location = $request->query->get('location', 'Tunis');
        
        if (!$date) {
            return new JsonResponse(['error' => 'Date parameter is required'], 400);
        }
        
        try {
            $weatherData = $this->getWeatherForecast($date, $location);
            return new JsonResponse($weatherData);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    private function getWeatherForecast(string $date, string $location): array
    {
        // ✅ CORRECTION 2 : Utiliser $this->openWeatherApiKey au lieu de $_ENV
        $apiKey = $this->openWeatherApiKey;
        
        if (empty($apiKey)) {
            // Mode développement : retourner des données factices
            return $this->getMockWeatherData($date, $location);
        }

        // ✅ CORRECTION : Vérifier si la date est dans la plage des 5 prochains jours
        // OpenWeatherMap Free ne couvre que J à J+5
        $selectedDate = new \DateTime($date);
        $selectedDate->setTime(0, 0, 0);
        $today = new \DateTime('today');
        $maxDate = new \DateTime('+5 days');
        $maxDate->setTime(23, 59, 59);

        if ($selectedDate < $today || $selectedDate > $maxDate) {
            // Date hors plage → retourner données mock avec flag explicite
            $mock = $this->getMockWeatherData($date, $location);
            $mock['out_of_range'] = true;
            return $mock;
        }
        $url = "https://api.openweathermap.org/data/2.5/forecast";
        $params = [
            'q' => $location,
            'appid' => $apiKey,
            'units' => 'metric',
            'lang' => 'fr',
            'cnt' => 40 // 40 créneaux = 5 jours complets (toutes les 3h)
        ];
        
        $query = http_build_query($params);
        $fullUrl = $url . '?' . $query;
        
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            // ✅ Fallback mock si l'API échoue, sans planter
            return $this->getMockWeatherData($date, $location);
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['list'])) {
            return $this->getMockWeatherData($date, $location);
        }
        
        $result = $this->formatWeatherData($data, $date);

        // ✅ CORRECTION CRITIQUE : si l'API ne retourne aucun créneau pour ce jour,
        // fallback sur les données mock (ex: timezone décalée, date limite J+5)
        if (empty($result['forecasts'])) {
            $mock = $this->getMockWeatherData($date, $location);
            $mock['mock'] = true;
            return $mock;
        }

        return $result;
    }
    
    private function formatWeatherData(array $data, string $selectedDate): array
    {
        $forecasts = [];
        $selectedDateObj = new \DateTime($selectedDate);
        
        foreach ($data['list'] as $forecast) {
            // ✅ CORRECTION 4 : Forcer le fuseau horaire UTC pour éviter les décalages de date
            $forecastDate = new \DateTime('@' . $forecast['dt']);
            $forecastDate->setTimezone(new \DateTimeZone('Africa/Tunis'));
            
            // Ne garder que les prévisions pour le jour sélectionné
            if ($forecastDate->format('Y-m-d') === $selectedDateObj->format('Y-m-d')) {
                $forecasts[] = [
                    'time' => $forecastDate->format('H:i'),
                    'temperature' => round($forecast['main']['temp']),
                    'feels_like' => round($forecast['main']['feels_like']),
                    'description' => $forecast['weather'][0]['description'],
                    'icon' => $forecast['weather'][0]['icon'],
                    'humidity' => $forecast['main']['humidity'],
                    'wind_speed' => $forecast['wind']['speed'],
                    'wind_direction' => $forecast['wind']['deg'] ?? 0,
                    'pressure' => $forecast['main']['pressure'],
                    'visibility' => $forecast['visibility'] ?? 0,
                    'rain_probability' => ($forecast['pop'] ?? 0) * 100
                ];
            }
        }
        
        return [
            'location' => $data['city']['name'] ?? 'Inconnue',
            'country' => $data['city']['country'] ?? '',
            'selected_date' => $selectedDate,
            'forecasts' => $forecasts,
            'current_weather' => $data['list'][0] ?? null
        ];
    }
    
    private function getMockWeatherData(string $date, string $location): array
    {
        $dateObj = new \DateTime($date);
        $dayOfWeek = $dateObj->format('N');
        
        $conditions = [
            1 => ['desc' => 'ciel dégagé', 'icon' => '01d', 'temp' => 22, 'rain' => 0],
            2 => ['desc' => 'partiellement nuageux', 'icon' => '02d', 'temp' => 20, 'rain' => 10],
            3 => ['desc' => 'nuageux', 'icon' => '03d', 'temp' => 18, 'rain' => 20],
            4 => ['desc' => 'légères pluies', 'icon' => '10d', 'temp' => 16, 'rain' => 60],
            5 => ['desc' => 'pluvieux', 'icon' => '09d', 'temp' => 15, 'rain' => 80],
            6 => ['desc' => 'orages', 'icon' => '11d', 'temp' => 17, 'rain' => 90],
            7 => ['desc' => 'ensoleillé', 'icon' => '01d', 'temp' => 24, 'rain' => 5]
        ];
        
        $condition = $conditions[$dayOfWeek] ?? $conditions[1];
        
        $forecasts = [];
        for ($hour = 8; $hour <= 18; $hour += 2) {
            $tempVariation = rand(-2, 2);
            $forecasts[] = [
                'time' => sprintf('%02d:00', $hour),
                'temperature' => $condition['temp'] + $tempVariation,
                'feels_like' => $condition['temp'] + $tempVariation - 1,
                'description' => $condition['desc'],
                'icon' => $condition['icon'],
                'humidity' => rand(40, 80),
                'wind_speed' => rand(5, 20),
                'wind_direction' => rand(0, 360),
                'pressure' => rand(1010, 1025),
                'visibility' => rand(5000, 10000),
                'rain_probability' => $condition['rain']
            ];
        }
        
        return [
            'location' => $location,
            'country' => 'TN',
            'selected_date' => $date,
            'forecasts' => $forecasts,
            'current_weather' => [
                'main' => ['temp' => $condition['temp']],
                'weather' => [0 => ['description' => $condition['desc'], 'icon' => $condition['icon']]],
                'pop' => $condition['rain'] / 100
            ],
            'mock' => true
        ];
    }
}