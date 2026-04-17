<?php

namespace App\Service;

use App\Entity\Itineraire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReplicateVideoService
{
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private StabilityImageService $stabilityImageService,
        private string $projectDir,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== 'your_groq_key_here';
    }

    public function generateItineraryVideo(Itineraire $itineraire): array
    {
        if (!$this->isConfigured()) {
            return [
                'status'  => 'unconfigured',
                'message' => 'Clé API Groq manquante.',
            ];
        }

        // Check if itinerary has étapes
        if ($itineraire->getEtapes()->isEmpty()) {
            return [
                'status'  => 'error',
                'message' => 'Cet itinéraire ne contient aucune étape. Ajoutez des étapes pour générer une vidéo.',
            ];
        }

        $prompt = $this->buildPrompt($itineraire);

        try {
            $response = $this->httpClient->request('POST', self::GROQ_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'           => 'llama-3.3-70b-versatile',
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        [
                            'role'    => 'system',
                            'content' => 'Tu es un expert en voyages. Réponds UNIQUEMENT en JSON valide. Le JSON doit être un objet avec une clé "slides" contenant un tableau.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                ],
                'timeout' => 60,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                // Try to get error details from Groq
                $body = $response->getContent(false);
                $errorData = json_decode($body, true);
                $detail = $errorData['error']['message'] ?? $body;
                return ['status' => 'error', 'message' => 'Erreur API Groq (HTTP ' . $statusCode . '): ' . mb_substr($detail, 0, 300)];
            }

            $data    = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? '';

            if ($content === '') {
                return ['status' => 'error', 'message' => 'Groq a renvoyé une réponse vide.'];
            }

            // Clean markdown fences if any
            $content = preg_replace('/```(?:json)?\s*|```/', '', $content);
            $content = trim($content);

            $decoded = json_decode($content, true);

            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                return ['status' => 'error', 'message' => 'JSON invalide: ' . json_last_error_msg() . ' — Contenu: ' . mb_substr($content, 0, 200)];
            }

            // Accept {"slides": [...]} or a raw array [...]
            if (is_array($decoded) && isset($decoded['slides']) && is_array($decoded['slides'])) {
                $slides = $decoded['slides'];
            } elseif (is_array($decoded) && array_is_list($decoded)) {
                $slides = $decoded;
            } else {
                return ['status' => 'error', 'message' => 'Structure JSON inattendue: ' . mb_substr($content, 0, 200)];
            }

            if (empty($slides)) {
                return ['status' => 'error', 'message' => 'Aucune slide générée.'];
            }

            // Generate AI images for each slide via Stability AI
            foreach ($slides as &$slide) {
                $imagePrompt = $slide['image_prompt'] ?? $slide['keyword'] ?? $slide['titre'] ?? '';
                $slide['image_url'] = $this->generateAndSaveImage(
                    $imagePrompt,
                    $itineraire->getIdItineraire(),
                    $slide['jour'] ?? 0
                );
            }
            unset($slide);

            return [
                'status' => 'ok',
                'slides' => $slides,
            ];

        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Erreur : ' . $e->getMessage()];
        }
    }

    private function buildPrompt(Itineraire $itineraire): string
    {
        $voyage          = $itineraire->getVoyage();
        $destination     = $voyage?->getDestination();
        $destinationName = $destination?->getNom_destination() ?? 'destination';
        $pays            = $destination?->getPays_destination() ?? '';

        $etapes  = $itineraire->getEtapes()->toArray();
        $jourMap = [];

        foreach ($etapes as $etape) {
            $jour = $etape->getNumero_jour() ?? 1;
            $jourMap[$jour][] = $etape;
        }
        ksort($jourMap);

        $joursText = '';
        foreach ($jourMap as $jour => $etapesDuJour) {
            $joursText .= "Jour $jour : ";
            foreach ($etapesDuJour as $etape) {
                $activite = $etape->getActivite();
                if ($activite) {
                    // Use activity details: name, lieu, description
                    $actInfo = $activite->getNom();
                    $lieu = $activite->getLieu();
                    if ($lieu) {
                        $actInfo .= ' à ' . $lieu;
                    }
                    $actDesc = $activite->getDescription();
                    if ($actDesc) {
                        $actInfo .= ' (' . mb_substr($actDesc, 0, 100) . ')';
                    }
                    $joursText .= '[Activité: ' . $actInfo . '] ';
                } else {
                    // No activity — use étape description
                    $joursText .= $etape->getDescription_etape() . ' ';
                }
            }
        }

        return "Génère un diaporama de voyage pour {$destinationName}" . ($pays ? ", {$pays}" : '') . ".
Itinéraire : {$joursText}

Retourne UNIQUEMENT un objet JSON avec une clé \"slides\" contenant un tableau :
{
  \"slides\": [
    {
      \"jour\": 1,
      \"titre\": \"Titre court du jour\",
      \"description\": \"Description cinématique en 2 phrases\",
      \"image_prompt\": \"Detailed cinematic travel photograph description in English for AI image generation based on the activities/descriptions above, e.g. 'Golden hour view of the Eiffel Tower from Trocadero gardens, warm light, dramatic clouds, ultra realistic 8K'\",
      \"keyword\": \"mot clé en anglais pour chercher une image\",
      \"emoji\": \"🗼\"
    }
  ]
}
Génère un objet par jour de l'itinéraire. Le champ image_prompt doit être une description très détaillée en anglais d'une photo de voyage cinématique BASÉE DIRECTEMENT sur les activités ou descriptions de chaque étape. Si l'étape mentionne une activité spécifique (randonnée, plongée, visite de monument, etc.), le image_prompt doit représenter cette activité dans le lieu mentionné. Mentionne le lieu spécifique, l'éclairage doré, l'ambiance et le style photographique cinématique.";
    }

    private function generateAndSaveImage(string $prompt, ?int $itineraireId, int $jour): string
    {
        $fallback = 'https://placehold.co/1280x720/2c1e14/f5f0eb?text=' . urlencode(mb_substr($prompt, 0, 40));

        if ($prompt === '') {
            return $fallback;
        }

        $dir = $this->projectDir . '/public/slides';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf('slide_%d_%d_%s.webp', $itineraireId ?? 0, $jour, substr(md5($prompt), 0, 8));
        $filepath = $dir . '/' . $filename;

        // Return cached image if it exists
        if (file_exists($filepath)) {
            return '/slides/' . $filename;
        }

        try {
            $result = $this->stabilityImageService->generateFromPrompt($prompt);

            if ($result['status'] === 'ok' && isset($result['image_base64'])) {
                file_put_contents($filepath, base64_decode($result['image_base64']));
                return '/slides/' . $filename;
            }
        } catch (\Throwable) {
            // Fall through to fallback
        }

        return $fallback;
    }
}