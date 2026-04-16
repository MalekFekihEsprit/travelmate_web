<?php

namespace App\Service;

use App\Entity\Itineraire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StabilityImageService
{
    private const API_BASE = 'https://api.stability.ai/v2beta/stable-image/generate/core';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== ''
            && $this->apiKey !== 'your_stability_api_key_here';
    }

    /**
     * Génère une image descriptive de l'itinéraire jour par jour.
     *
     * @return array{status: string, image_base64?: string, message?: string}
     */
    public function generateItineraryImage(Itineraire $itineraire): array
    {
        if (!$this->isConfigured()) {
            return [
                'status'  => 'unconfigured',
                'message' => 'L\'API Stability AI n\'est pas configurée. Obtenez une clé sur https://platform.stability.ai/account/keys puis renseignez STABILITY_API_KEY dans votre fichier .env',
            ];
        }

        $prompt = $this->buildPrompt($itineraire);

        try {
            $boundary = bin2hex(random_bytes(16));
            $body = $this->buildMultipartBody($boundary, [
                'prompt'        => $prompt,
                'aspect_ratio'  => '16:9',
                'output_format' => 'webp',
                'style_preset'  => 'cinematic',
            ]);

            $response = $this->httpClient->request('POST', self::API_BASE, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 401 || $statusCode === 403) {
                $data = $response->toArray(false);
                return [
                    'status'  => 'error',
                    'message' => 'Clé API Stability AI invalide ou contenu rejeté.',
                ];
            }

            if ($statusCode === 429) {
                return [
                    'status'  => 'error',
                    'message' => 'Limite de requêtes Stability AI atteinte. Réessayez dans quelques instants.',
                ];
            }

            if ($statusCode >= 400) {
                $data = $response->toArray(false);
                $errorDetail = $data['errors'][0] ?? $data['message'] ?? $data['name'] ?? json_encode($data);
                return [
                    'status'  => 'error',
                    'message' => 'Erreur API Stability (HTTP ' . $statusCode . ') : ' . $errorDetail,
                ];
            }

            $data = $response->toArray(false);
            $imageBase64 = $data['image'] ?? null;

            if (!$imageBase64) {
                return [
                    'status'  => 'error',
                    'message' => 'Aucune image retournée par l\'API.',
                ];
            }

            return [
                'status'       => 'ok',
                'image_base64' => $imageBase64,
                'format'       => 'webp',
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => 'error',
                'message' => 'Erreur lors de la génération : ' . $e->getMessage(),
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
            'Cinematic travel photograph of %s%s.',
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

        $parts[] = 'Golden hour lighting, wide-angle lens, dramatic sky, ultra-realistic, 8K quality.';

        $prompt = implode(' ', $parts);
        if (mb_strlen($prompt) > 9990) {
            $prompt = mb_substr($prompt, 0, 9987) . '...';
        }

        return $prompt;
    }

    /**
     * Construit le corps multipart/form-data pour l'API Stability.
     */
    private function buildMultipartBody(string $boundary, array $fields): string
    {
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        return $body;
    }
}
