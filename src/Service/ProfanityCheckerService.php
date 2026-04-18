<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Vérifie si un texte contient des gros mots via l'API PurgoMalum.
 *
 * API gratuite, sans clé, supporte le français et l'anglais.
 * Doc : https://www.purgomalum.com
 */
class ProfanityCheckerService
{
    // ── Mots supplémentaires en arabe & français tunisien ──────────────────
    // Ces mots sont transmis à PurgoMalum en plus de sa liste interne.
    // Ajoutez / retirez selon vos besoins.
    private const CUSTOM_WORDS = [
        // Français vulgaire courant
        'merde', 'putain', 'connard', 'connasse', 'salope', 'enculé',
        'enculer', 'baise', 'baiser', 'couille', 'couilles', 'bite',
        'chier', 'chieur', 'fumier', 'ordure', 'pute', 'putes',
        'nique', 'niquer', 'va te faire foutre', 'fils de pute',
        'fils de putain', 'ta gueule', 'ferme ta gueule',
        // Tunisien / arabe translittéré
        'zobbi', 'nik', 'kahba', 'kalb', 'hmar', 'sharmouta',
        'wled el kahba', 'ibn el sharmouta',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $logger,
    ) {}

    /**
     * Retourne true si le texte contient des gros mots.
     * En cas d'erreur réseau, retourne false (on ne bloque pas par défaut).
     */
    public function containsProfanity(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        // ── 1. Vérification locale rapide (mots custom) ────────────────────
        $lower = mb_strtolower($text);
        foreach (self::CUSTOM_WORDS as $word) {
            if (str_contains($lower, mb_strtolower($word))) {
                $this->logger->info('[ProfanityChecker] Mot interdit détecté (local) : ' . $word);
                return true;
            }
        }

        // ── 2. Vérification via l'API PurgoMalum ──────────────────────────
        try {
            $customList = implode(',', array_map('urlencode', self::CUSTOM_WORDS));

            $response = $this->httpClient->request('GET', 'https://www.purgomalum.com/service/containsprofanity', [
                'query' => [
                    'text'        => $text,
                    'add'         => $customList,  // ajoute nos mots à la liste interne
                ],
                'timeout' => 5,
            ]);

            $body = trim($response->getContent(false));

            //// L'API retourne "true" ou "false" en texte brut
            return $body === 'true';

        } catch (\Throwable $e) {
            $this->logger->warning('[ProfanityChecker] API inaccessible : ' . $e->getMessage());
            // Fallback : on laisse passer (ne pas bloquer les utilisateurs si l'API est down)
            return false;
        }
    }
}