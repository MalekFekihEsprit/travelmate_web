<?php
namespace App\Service;

class EstimationService
{
    public function __construct(
        private OpenRouterClient $client,
        private string $apiKey,
        private string $model
    ) {}

    public function estimateCost(string $destination, int $nbJours, int $nbPersonnes, string $devise): string
    {
        return $this->client->chat($this->apiKey, $this->model, [
            ['role' => 'system', 'content' => 'Tu es un expert en planification de voyages. Réponds en français avec une estimation structurée par catégorie (hébergement, transport, restauration, loisirs).'],
            ['role' => 'user',   'content' => "Estime le coût total d'un voyage à $destination pour $nbPersonnes personne(s) pendant $nbJours jours en $devise. Détaille par catégorie."],
        ]);
    }
}