<?php

namespace App\Service;

use App\Entity\Itineraire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RunwayVideoService
{
    private const API_BASE = 'https://api.dev.runwayml.com/v1';
    private const API_VERSION = '2024-11-06';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== ''
            && $this->apiKey !== 'your_runway_api_key_here';
    }

    /**
     * Génère une vidéo descriptive de l'itinéraire jour par jour.
     *
     * @return array{status: string, generation_id?: string, message?: string}
     */
    public function generateItineraryVideo(Itineraire $itineraire): array
    {
        if (!$this->isConfigured()) {
            return [
                'status'  => 'unconfigured',
                'message' => 'L\'API Runway ML n\'est pas configurée. Obtenez une clé sur https://dev.runwayml.com puis renseignez RUNWAY_API_SECRET dans votre fichier .env',
            ];
        }

        $prompt = $this->buildPrompt($itineraire);

        try {
            $response = $this->httpClient->request('POST', self::API_BASE . '/text_to_video', [
                'headers' => [
                    'Authorization'   => 'Bearer ' . $this->apiKey,
                    'Content-Type'    => 'application/json',
                    'Accept'          => 'application/json',
                    'X-Runway-Version' => self::API_VERSION,
                ],
                'json' => [
                    'model'      => 'gen4.5',
                    'promptText' => $prompt,
                    'ratio'      => '1280:720',
                    'duration'   => 5,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'status'  => 'error',
                    'message' => 'Clé API Runway ML invalide ou quota épuisé.',
                ];
            }

            if ($statusCode === 429) {
                return [
                    'status'  => 'error',
                    'message' => 'Limite de requêtes Runway ML atteinte. Réessayez dans quelques instants.',
                ];
            }

            if ($statusCode >= 400) {
                $errorDetail = $data['detail'] ?? $data['message'] ?? $data['error'] ?? json_encode($data);
                return [
                    'status'  => 'error',
                    'message' => 'Erreur API Runway (HTTP ' . $statusCode . ') : ' . $errorDetail,
                ];
            }

            return [
                'status'        => 'ok',
                'generation_id' => $data['id'] ?? null,
                'state'         => 'PENDING',
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => 'error',
                'message' => 'Erreur lors de la génération : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Vérifie le statut d'une tâche de génération vidéo.
     *
     * @return array{state: string, video_url?: string, message?: string}
     */
    public function checkStatus(string $taskId): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE . '/tasks/' . $taskId, [
                'headers' => [
                    'Authorization'   => 'Bearer ' . $this->apiKey,
                    'Accept'          => 'application/json',
                    'X-Runway-Version' => self::API_VERSION,
                ],
            ]);

            $data = $response->toArray(false);
            $status = $data['status'] ?? 'unknown';

            $result = [
                'state' => $status,
            ];

            if ($status === 'SUCCEEDED' && !empty($data['output'])) {
                $result['video_url'] = $data['output'][0];
            }

            if ($status === 'FAILED') {
                $result['message'] = $data['failure'] ?? $data['failureReason'] ?? 'La génération a échoué.';
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'state'   => 'error',
                'message' => 'Erreur lors de la vérification : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Construit un prompt cinématique à partir de l'itinéraire et ses étapes.
     */
    private function buildPrompt(Itineraire $itineraire): string
    {
        $voyage = $itineraire->getVoyage();
        $destination = $voyage?->getDestination();
        $destinationName = $destination?->getNom_destination() ?? 'une destination';
        $pays = $destination?->getPays_destination() ?? '';
        $climat = $destination?->getClimat_destination() ?? '';

        $etapes = $itineraire->getEtapes()->toArray();

        // Grouper les étapes par jour
        $jourMap = [];
        foreach ($etapes as $etape) {
            $jour = $etape->getNumero_jour() ?? 1;
            $jourMap[$jour][] = $etape;
        }
        ksort($jourMap);

        // Construire la description jour par jour avec activités
        $parts = [];
        $parts[] = sprintf(
            'Cinematic travel video of %s%s.',
            $destinationName,
            $pays ? ', ' . $pays : ''
        );

        if ($climat) {
            $parts[] = sprintf('Weather: %s.', $climat);
        }

        if (!empty($jourMap)) {
            foreach ($jourMap as $jour => $etapesDuJour) {
                $dayParts = [];
                foreach ($etapesDuJour as $etape) {
                    $segment = '';
                    $activite = $etape->getActivite();
                    if ($activite) {
                        $segment .= $activite->getNom();
                        $lieu = $activite->getLieu();
                        if ($lieu) {
                            $segment .= ' at ' . $lieu;
                        }
                        $actDesc = $activite->getDescription();
                        if ($actDesc) {
                            $segment .= ' (' . mb_substr($actDesc, 0, 60) . ')';
                        }
                    }
                    $etapeDesc = $etape->getDescription_etape();
                    if ($etapeDesc) {
                        $segment .= ($segment ? ': ' : '') . mb_substr($etapeDesc, 0, 80);
                    }
                    if ($segment) {
                        $dayParts[] = trim($segment);
                    }
                }
                if (!empty($dayParts)) {
                    $parts[] = sprintf('Day %d: %s.', $jour, implode(', then ', $dayParts));
                }
            }
        } else {
            $desc = $itineraire->getDescription_itineraire();
            if ($desc) {
                $parts[] = $desc;
            }
        }

        $parts[] = 'Golden hour, smooth camera, aerial drone, cinematic 4K.';

        // Runway text_to_video max 1000 chars
        $prompt = implode(' ', $parts);
        if (mb_strlen($prompt) > 990) {
            $prompt = mb_substr($prompt, 0, 987) . '...';
        }

        return $prompt;
    }
}
