<?php

namespace App\Service;

use App\Entity\Destination;
use App\Entity\Voyage;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqCulturalRulesService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $cacheDirectory,
        private readonly int $cacheTtl,
    ) {
    }

    public function generateForVoyage(Voyage $voyage): array
    {
        $destination = $voyage->getDestination();
        if (!$destination || !$destination->getNom_destination()) {
            return $this->buildUnavailablePayload('La destination du voyage n\'est pas renseignee.');
        }

        if (!$this->isConfigured()) {
            return $this->buildUnavailablePayload('Ajoute votre cle Groq pour activer les conseils culturels IA.', true);
        }

        $cachedPayload = $this->loadFromCache($voyage, $destination);
        if ($cachedPayload !== null) {
            return $cachedPayload;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0.3,
                    'max_completion_tokens' => 800,
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un expert du voyage et du savoir-vivre international. Reponds uniquement en JSON valide. Le ton doit etre clair, concret, rassurant et en francais.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->buildPrompt($voyage, $destination),
                        ],
                    ],
                ],
                'timeout' => 20,
            ]);

            $statusCode = $response->getStatusCode();
            $payload = json_decode($response->getContent(false), true);

            if ($statusCode >= 400 || !is_array($payload)) {
                throw new \RuntimeException('Groq a retourne une reponse invalide.');
            }

            $content = $payload['choices'][0]['message']['content'] ?? null;
            if (!is_string($content) || trim($content) === '') {
                throw new \RuntimeException('Le contenu Groq est vide.');
            }

            $normalized = $this->normalizeGroqContent($content, $destination);
            $this->storeInCache($voyage, $destination, $normalized);

            return $normalized;
        } catch (\Throwable $exception) {
            $this->logger->warning('Impossible de generer les regles culturelles Groq.', [
                'voyage_id' => $voyage->getId_voyage(),
                'error' => $exception->getMessage(),
            ]);

            return $this->buildUnavailablePayload('Les conseils culturels sont temporairement indisponibles. Reessaie plus tard.');
        }
    }

    private function buildPrompt(Voyage $voyage, Destination $destination): string
    {
        $destinationLabel = $this->buildDestinationLabel($destination);
        $dateDebut = $voyage->getDate_debut()?->format('Y-m-d') ?? 'non renseignee';
        $dateFin = $voyage->getDate_fin()?->format('Y-m-d') ?? 'non renseignee';

        return implode("\n", [
            'Genere des regles de comportement culturel pour un voyageur francophone.',
            'Destination: ' . $destinationLabel,
            'Date de debut du voyage: ' . $dateDebut,
            'Date de fin du voyage: ' . $dateFin,
            'Langues: ' . ($destination->getLanguages_destination() ?: 'non renseignees'),
            'Climat: ' . ($destination->getClimat_destination() ?: 'non renseigne'),
            'Saison: ' . ($destination->getSaison_destination() ?: 'non renseignee'),
            'Description destination: ' . ($destination->getDescription_destination() ?: 'non renseignee'),
            'Retourne strictement un JSON avec cette structure:',
            '{',
            '  "summary": "resume court en 2 phrases maximum",',
            '  "rules": [',
            '    {',
            '      "title": "titre court",',
            '      "advice": "conseil concret et actionnable",',
            '      "why": "pourquoi c\'est important localement"',
            '    }',
            '  ]',
            '}',
            'Contraintes:',
            '- entre 4 et 6 regles',
            '- pas de markdown',
            '- pas de texte hors JSON',
            '- evite les generalites vagues',
            '- couvre salutation, tenue, repas, politesse, lieux religieux ou espace public quand pertinent',
        ]);
    }

    private function normalizeGroqContent(string $content, Destination $destination): array
    {
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $content, $matches) !== 1) {
                throw new \RuntimeException('Le JSON Groq ne peut pas etre decode.');
            }

            $decoded = json_decode($matches[0], true);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Le payload Groq n\'est pas exploitable.');
        }

        $summary = trim((string) ($decoded['summary'] ?? ''));
        $rules = [];

        foreach (($decoded['rules'] ?? []) as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $title = trim((string) ($rule['title'] ?? ''));
            $advice = trim((string) ($rule['advice'] ?? ''));
            $why = trim((string) ($rule['why'] ?? ''));

            if ($title === '' || $advice === '') {
                continue;
            }

            $rules[] = [
                'title' => $title,
                'advice' => $advice,
                'why' => $why,
            ];
        }

        if ($rules === []) {
            throw new \RuntimeException('Groq n\'a retourne aucune regle exploitable.');
        }

        return [
            'status' => 'ready',
            'title' => 'Regles culturelles pour ' . $this->buildDestinationLabel($destination),
            'summary' => $summary !== '' ? $summary : 'Quelques reperes utiles pour adopter le bon comportement une fois sur place.',
            'rules' => array_slice($rules, 0, 6),
            'is_configured' => true,
        ];
    }

    private function buildUnavailablePayload(string $message, bool $isConfigMissing = false): array
    {
        return [
            'status' => $isConfigMissing ? 'unconfigured' : 'unavailable',
            'title' => 'Conseils culturels IA',
            'summary' => $message,
            'rules' => [],
            'is_configured' => !$isConfigMissing,
        ];
    }

    private function buildDestinationLabel(Destination $destination): string
    {
        $parts = array_filter([
            $destination->getNom_destination(),
            $destination->getPays_destination(),
        ]);

        return implode(', ', $parts);
    }

    private function isConfigured(): bool
    {
        $apiKey = trim($this->apiKey);

        return $apiKey !== '' && !str_contains($apiKey, 'replace_with_your_groq_api_key');
    }

    private function getCacheFilePath(Voyage $voyage, Destination $destination): string
    {
        $fingerprint = [
            'voyage' => $voyage->getId_voyage(),
            'destination' => $destination->getId_destination(),
            'name' => $destination->getNom_destination(),
            'country' => $destination->getPays_destination(),
            'languages' => $destination->getLanguages_destination(),
            'climate' => $destination->getClimat_destination(),
            'season' => $destination->getSaison_destination(),
            'description' => $destination->getDescription_destination(),
            'model' => $this->model,
        ];

        return rtrim($this->cacheDirectory, '\\/') . DIRECTORY_SEPARATOR . hash('sha256', json_encode($fingerprint)) . '.json';
    }

    private function loadFromCache(Voyage $voyage, Destination $destination): ?array
    {
        $filePath = $this->getCacheFilePath($voyage, $destination);
        if (!is_file($filePath)) {
            return null;
        }

        if (filemtime($filePath) < (time() - $this->cacheTtl)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function storeInCache(Voyage $voyage, Destination $destination, array $payload): void
    {
        if (!is_dir($this->cacheDirectory) && !mkdir($concurrentDirectory = $this->cacheDirectory, 0777, true) && !is_dir($concurrentDirectory)) {
            return;
        }

        file_put_contents($this->getCacheFilePath($voyage, $destination), json_encode($payload, JSON_PRETTY_PRINT));
    }
}