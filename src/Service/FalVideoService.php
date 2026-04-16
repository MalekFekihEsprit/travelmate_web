<?php

namespace App\Service;

use App\Entity\Itineraire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FalVideoService
{
    private const QUEUE_URL = 'https://queue.fal.run/fal-ai/cogvideox-5b';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== ''
            && $this->apiKey !== 'your_fal_key_here';
    }

    /**
     * Soumet une demande de génération vidéo à fal.ai (CogVideoX-5B).
     *
     * @return array{status: string, request_id?: string, status_url?: string, response_url?: string, message?: string}
     */
    public function generateItineraryVideo(Itineraire $itineraire): array
    {
        if (!$this->isConfigured()) {
            return [
                'status'  => 'unconfigured',
                'message' => 'L\'API fal.ai n\'est pas configurée. Obtenez une clé sur https://fal.ai/dashboard/keys puis renseignez FAL_KEY dans votre fichier .env',
            ];
        }

        $prompt = $this->buildPrompt($itineraire);

        try {
            $response = $this->httpClient->request('POST', self::QUEUE_URL, [
                'headers' => [
                    'Authorization' => 'Key ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'prompt'     => $prompt,
                    'video_size' => 'landscape_16_9',
                    'num_inference_steps' => 50,
                    'guidance_scale'      => 7,
                    'use_rife'            => true,
                    'export_fps'          => 16,
                    'negative_prompt'     => 'Distorted, discontinuous, Ugly, blurry, low resolution, motionless, static, disfigured, disconnected limbs, Ugly faces, incomplete arms',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode === 401) {
                return [
                    'status'  => 'error',
                    'message' => 'Clé API fal.ai invalide.',
                ];
            }

            if ($statusCode === 429) {
                return [
                    'status'  => 'error',
                    'message' => 'Limite de requêtes fal.ai atteinte. Réessayez dans quelques instants.',
                ];
            }

            if ($statusCode >= 400) {
                $errorDetail = $data['detail'] ?? $data['message'] ?? json_encode($data);
                return [
                    'status'  => 'error',
                    'message' => 'Erreur API fal.ai (HTTP ' . $statusCode . ') : ' . $errorDetail,
                ];
            }

            return [
                'status'       => 'ok',
                'request_id'   => $data['request_id'] ?? null,
                'status_url'   => $data['status_url'] ?? null,
                'response_url' => $data['response_url'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => 'error',
                'message' => 'Erreur lors de la soumission : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Vérifie le statut d'une génération via l'URL de statut fal.ai.
     *
     * @return array{state: string, video_url?: string, message?: string}
     */
    public function checkStatus(string $requestId): array
    {
        try {
            $statusUrl = self::QUEUE_URL . '/requests/' . $requestId . '/status';

            $response = $this->httpClient->request('GET', $statusUrl, [
                'headers' => [
                    'Authorization' => 'Key ' . $this->apiKey,
                ],
                'query' => ['logs' => 1],
            ]);

            $data = $response->toArray(false);
            $status = $data['status'] ?? 'unknown';

            if ($status === 'COMPLETED') {
                // Récupérer le résultat
                return $this->getResult($requestId);
            }

            if ($status === 'IN_QUEUE') {
                $position = $data['queue_position'] ?? null;
                return [
                    'state'   => 'IN_QUEUE',
                    'message' => $position !== null
                        ? 'En file d\'attente (position ' . $position . ')…'
                        : 'En file d\'attente…',
                ];
            }

            if ($status === 'IN_PROGRESS') {
                return [
                    'state' => 'IN_PROGRESS',
                ];
            }

            // Vérifier si erreur
            if (!empty($data['error'])) {
                return [
                    'state'   => 'FAILED',
                    'message' => $data['error'],
                ];
            }

            return [
                'state' => $status,
            ];
        } catch (\Throwable $e) {
            return [
                'state'   => 'error',
                'message' => 'Erreur lors de la vérification : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Récupère le résultat d'une génération terminée.
     */
    private function getResult(string $requestId): array
    {
        try {
            $responseUrl = self::QUEUE_URL . '/requests/' . $requestId . '/response';

            $response = $this->httpClient->request('GET', $responseUrl, [
                'headers' => [
                    'Authorization' => 'Key ' . $this->apiKey,
                ],
            ]);

            $data = $response->toArray(false);
            $videoUrl = $data['video']['url'] ?? null;

            if ($videoUrl) {
                return [
                    'state'     => 'COMPLETED',
                    'video_url' => $videoUrl,
                ];
            }

            return [
                'state'   => 'FAILED',
                'message' => 'Aucune vidéo retournée par l\'API.',
            ];
        } catch (\Throwable $e) {
            return [
                'state'   => 'error',
                'message' => 'Erreur lors de la récupération du résultat : ' . $e->getMessage(),
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

        $jourMap = [];
        foreach ($etapes as $etape) {
            $jour = $etape->getNumero_jour() ?? 1;
            $jourMap[$jour][] = $etape;
        }
        ksort($jourMap);

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

        $parts[] = 'Golden hour, smooth camera movement, aerial drone, cinematic 4K.';

        $prompt = implode(' ', $parts);
        if (mb_strlen($prompt) > 9990) {
            $prompt = mb_substr($prompt, 0, 9987) . '...';
        }

        return $prompt;
    }
}
