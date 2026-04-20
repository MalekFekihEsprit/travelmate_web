<?php

namespace App\Service;

use App\Entity\Activite;
use App\Entity\Destination;
use App\Entity\Voyage;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CerebrasItinerarySuggestionService
{
    private const API_URL = 'https://api.cerebras.ai/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function generateForVoyage(Voyage $voyage): array
    {
        $destination = $voyage->getDestination();
        if (!$destination || !$destination->getNom_destination()) {
            return $this->buildUnavailablePayload('La destination du voyage est obligatoire pour generer une proposition IA.');
        }

        if (!$this->isConfigured()) {
            return $this->buildUnavailablePayload('Ajoute votre cle Cerebras dans .env pour activer la generation automatique d\'itineraire.', true);
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0.6,
                    'max_completion_tokens' => 2500,
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un planificateur de voyage expert. Reponds uniquement en JSON valide sans markdown ni texte autour. La reponse doit etre en francais simple et naturelle.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->buildPrompt($voyage, $destination),
                        ],
                    ],
                ],
                'timeout' => 25,
            ]);

            $statusCode = $response->getStatusCode();
            $payload = json_decode($response->getContent(false), true);

            if ($statusCode >= 400 || !is_array($payload)) {
                throw new \RuntimeException($this->extractApiErrorMessage($statusCode, $payload));
            }

            $content = $payload['choices'][0]['message']['content'] ?? null;
            if (!is_string($content) || trim($content) === '') {
                throw new \RuntimeException('Le contenu Cerebras est vide.');
            }

            return $this->normalizeContent($content, $voyage, $destination);
        } catch (\Throwable $exception) {
            $this->logger->warning('Impossible de generer une proposition d\'itineraire Cerebras.', [
                'voyage_id' => $voyage->getIdVoyage(),
                'error' => $exception->getMessage(),
            ]);

            return $this->buildUnavailablePayload($this->buildUserFacingErrorMessage($exception));
        }
    }

    private function buildPrompt(Voyage $voyage, Destination $destination): string
    {
        $dateDebut = $voyage->getDate_debut()?->format('Y-m-d') ?? 'non renseignee';
        $dateFin = $voyage->getDate_fin()?->format('Y-m-d') ?? 'non renseignee';
        $nbJours = $this->computeTripLength($voyage);
        $activites = $this->formatActivities($voyage);

        return implode("\n", [
            'Genere une seule proposition d\'itineraire detaille jour par jour pour un voyage.',
            'Titre du voyage: ' . ($voyage->getTitre_voyage() ?: 'non renseigne'),
            'Destination: ' . $this->buildDestinationLabel($destination),
            'Description destination: ' . ($destination->getDescription_destination() ?: 'non renseignee'),
            'Climat: ' . ($destination->getClimat_destination() ?: 'non renseigne'),
            'Saison: ' . ($destination->getSaison_destination() ?: 'non renseignee'),
            'Date debut: ' . $dateDebut,
            'Date fin: ' . $dateFin,
            'Duree en jours: ' . ($nbJours ?? 'non calculee'),
            'Statut du voyage: ' . ($voyage->getStatut() ?: 'non renseigne'),
            'Activites liees au voyage:',
            $activites !== [] ? implode("\n", $activites) : '- aucune activite selectionnee',
            'Retourne strictement un JSON avec cette structure:',
            '{',
            '  "nom_itineraire": "nom court et vendeur",',
            '  "summary": "resume en 1 ou 2 phrases",',
            '  "description_itineraire": "resume general du programme",',
            '  "highlights": ["point fort 1", "point fort 2", "point fort 3"],',
            '  "etapes": [',
            '    {"numero_jour": 1, "heure": "09:00", "description_etape": "Description de cette etape"},',
            '    {"numero_jour": 1, "heure": "12:00", "description_etape": "Description du dejeuner ou activite"},',
            '    {"numero_jour": 1, "heure": "15:00", "description_etape": "Activite apres-midi"},',
            '    {"numero_jour": 2, "heure": "09:00", "description_etape": "Debut du jour 2"}',
            '  ]',
            '}',
            'Contraintes:',
            '- nom_itineraire entre 5 et 80 caracteres',
            '- summary max 220 caracteres',
            '- description_itineraire: resume global du programme',
            '- highlights contient entre 3 et 5 elements',
            '- etapes: genere 2 a 4 etapes par jour, chaque jour de 1 a ' . ($nbJours ?? 3),
            '- heure au format HH:MM (24h)',
            '- description_etape: phrase descriptive de l\'activite ou moment',
            '- adapte le rythme a la duree du voyage',
            '- si peu d\'informations sont disponibles, reste concret et prudent',
        ]);
    }

    private function normalizeContent(string $content, Voyage $voyage, Destination $destination): array
    {
        $normalizedContent = trim($content);
        $normalizedContent = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $normalizedContent) ?? $normalizedContent;

        $decoded = json_decode($normalizedContent, true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $normalizedContent, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Le JSON Cerebras ne peut pas etre decode.');
        }

        $nomItineraire = trim((string) ($decoded['nom_itineraire'] ?? ''));
        $summary = trim((string) ($decoded['summary'] ?? ''));
        $description = trim((string) ($decoded['description_itineraire'] ?? ''));
        $highlights = [];

        foreach (($decoded['highlights'] ?? []) as $highlight) {
            $label = trim((string) $highlight);
            if ($label !== '') {
                $highlights[] = $label;
            }
        }

        $etapes = [];
        foreach (($decoded['etapes'] ?? []) as $rawEtape) {
            if (!is_array($rawEtape)) {
                continue;
            }

            $numeroJour = (int) ($rawEtape['numero_jour'] ?? 0);
            $heure = trim((string) ($rawEtape['heure'] ?? '09:00'));
            $descriptionEtape = trim((string) ($rawEtape['description_etape'] ?? ''));

            if ($numeroJour < 1 || $descriptionEtape === '') {
                continue;
            }

            if (!preg_match('/^\d{1,2}:\d{2}$/', $heure)) {
                $heure = '09:00';
            }

            $etapes[] = [
                'numero_jour' => $numeroJour,
                'heure' => $heure,
                'description_etape' => $descriptionEtape,
            ];
        }

        if ($nomItineraire === '') {
            $nomItineraire = sprintf('Itineraire IA - %s', $voyage->getTitre_voyage() ?: $this->buildDestinationLabel($destination));
        }

        if ($summary === '') {
            $summary = 'Une proposition generee automatiquement a partir des informations du voyage.';
        }

        if ($description === '') {
            $description = 'Programme genere par IA.';
        }

        if ($highlights === []) {
            $highlights = [
                'Rythme adapte a votre voyage',
                'Activites coherentes avec la destination',
                'Base editable apres validation',
            ];
        }

        return [
            'status' => 'ready',
            'title' => 'Proposition IA pour ' . $this->buildDestinationLabel($destination),
            'summary' => mb_substr($summary, 0, 220),
            'nom_itineraire' => mb_substr($nomItineraire, 0, 120),
            'description_itineraire' => $description,
            'highlights' => array_slice($highlights, 0, 5),
            'etapes' => $etapes,
            'is_configured' => true,
        ];
    }

    private function buildUnavailablePayload(string $message, bool $isConfigMissing = false): array
    {
        return [
            'status' => $isConfigMissing ? 'unconfigured' : 'unavailable',
            'title' => 'Proposition d\'itineraire IA',
            'summary' => $message,
            'nom_itineraire' => '',
            'description_itineraire' => '',
            'highlights' => [],
            'etapes' => [],
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

    private function computeTripLength(Voyage $voyage): ?int
    {
        if (!$voyage->getDate_debut() || !$voyage->getDate_fin()) {
            return null;
        }

        return $voyage->getDate_fin()->diff($voyage->getDate_debut())->days + 1;
    }

    /**
     * @return list<string>
     */
    private function formatActivities(Voyage $voyage): array
    {
        $lines = [];

        foreach ($voyage->getActivites()->toArray() as $activite) {
            if (!$activite instanceof Activite) {
                continue;
            }

            $parts = array_filter([
                $activite->getNom(),
                $activite->getLieu() ? 'lieu: ' . $activite->getLieu() : null,
                $activite->getDuree() ? 'duree: ' . $activite->getDuree() . ' h' : null,
                $activite->getCategorie()?->getNom() ? 'categorie: ' . $activite->getCategorie()->getNom() : null,
            ]);

            if ($parts === []) {
                continue;
            }

            $lines[] = '- ' . implode(' | ', $parts);
        }

        return array_slice($lines, 0, 10);
    }

    private function isConfigured(): bool
    {
        $apiKey = trim($this->apiKey);

        return $apiKey !== '' && !str_contains($apiKey, 'replace_with_your_cerebras_api_key');
    }

    private function extractApiErrorMessage(int $statusCode, mixed $payload): string
    {
        if (is_array($payload)) {
            $message = trim((string) ($payload['error']['message'] ?? ''));
            $code = trim((string) ($payload['error']['code'] ?? ''));

            if ($message !== '') {
                return sprintf('Cerebras API error %d%s: %s', $statusCode, $code !== '' ? ' ' . $code : '', $message);
            }
        }

        return sprintf('Cerebras API error %d.', $statusCode);
    }

    private function buildUserFacingErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $normalized = mb_strtolower($message);

        if (str_contains($normalized, 'rate limit') || str_contains($normalized, 'quota') || str_contains($normalized, 'error 429')) {
            return 'La cle Cerebras est bien detectee, mais votre quota ou votre limite de debit est atteint. Reessayez dans quelques instants ou verifiez votre plan Cerebras.';
        }

        if (str_contains($normalized, 'unauthorized') || str_contains($normalized, 'invalid api key') || str_contains($normalized, 'error 401') || str_contains($normalized, 'error 403')) {
            return 'La cle Cerebras est refusee. Verifiez qu\'elle est valide et active pour l\'API Inference Cerebras.';
        }

        if (str_contains($normalized, 'error 404') || str_contains($normalized, 'model') && str_contains($normalized, 'not found')) {
            return 'Le modele Cerebras configure n\'est pas disponible pour votre compte. Essayez un autre modele compatible.';
        }

        return 'La proposition d\'itineraire IA est indisponible pour le moment. Verifiez votre cle Cerebras puis reessayez.';
    }
}