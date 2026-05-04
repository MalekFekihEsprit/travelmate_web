<?php

namespace App\Service;

use App\Entity\Hebergement;

/**
 * Service métier pour la validation des règles de l'entité Hebergement.
 */
class HebergementManager
{
    /**
     * Valide les règles métier d'un Hebergement.
     *
     * Règles :
     *  1. Le nom de l'hébergement est obligatoire (non vide).
     *  2. Le prix par nuit, s'il est fourni, doit être positif ou nul.
     *  3. La note, si elle est fournie, doit être comprise entre 0 et 5.
     *  4. La latitude, si fournie, doit être comprise entre -90 et 90.
     *  5. La longitude, si fournie, doit être comprise entre -180 et 180.
     *
     * @throws \InvalidArgumentException si une règle est violée
     */
    public function validate(Hebergement $hebergement): bool
    {
        // Règle 1 : nom obligatoire
        if (empty(trim((string) $hebergement->getNomHebergement()))) {
            throw new \InvalidArgumentException('Le nom de l\'hébergement est obligatoire.');
        }

        // Règle 2 : prix par nuit >= 0
        $prix = $hebergement->getPrixNuitHebergement();
        if ($prix !== null && $prix < 0) {
            throw new \InvalidArgumentException('Le prix par nuit doit être un nombre positif ou nul.');
        }

        // Règle 3 : note entre 0 et 5
        $note = $hebergement->getNoteHebergement();
        if ($note !== null && ($note < 0 || $note > 5)) {
            throw new \InvalidArgumentException('La note doit être comprise entre 0 et 5.');
        }

        // Règle 4 : latitude valide
        $lat = $hebergement->getLatitudeHebergement();
        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            throw new \InvalidArgumentException('La latitude doit être comprise entre -90 et 90.');
        }

        // Règle 5 : longitude valide
        $lon = $hebergement->getLongitudeHebergement();
        if ($lon !== null && ($lon < -180 || $lon > 180)) {
            throw new \InvalidArgumentException('La longitude doit être comprise entre -180 et 180.');
        }

        return true;
    }

    /**
     * Normalise le nom d'un hébergement (trim + minuscules) pour les comparaisons.
     */
    public function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Vérifie si deux noms d'hébergement sont équivalents (insensible à la casse et aux espaces).
     */
    public function areNamesEquivalent(string $nameA, string $nameB): bool
    {
        return $this->normalizeName($nameA) === $this->normalizeName($nameB);
    }
}