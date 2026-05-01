<?php

namespace App\Service;

use App\Entity\Destination;

/**
 * Service métier pour la validation des règles de la Destination.
 */
class DestinationManager
{
    /**
     * Valide les règles métier d'une Destination.
     *
     * Règles :
     *  1. Le nom de la destination est obligatoire (non vide).
     *  2. Le pays de la destination est obligatoire (non vide).
     *  3. Le score doit être compris entre 0 et 10.
     *  4. La latitude, si fournie, doit être comprise entre -90 et 90.
     *  5. La longitude, si fournie, doit être comprise entre -180 et 180.
     *
     * @throws \InvalidArgumentException si une règle est violée
     */
    public function validate(Destination $destination): bool
    {
        // Règle 1 : nom obligatoire
        if (empty(trim((string) $destination->getNomDestination()))) {
            throw new \InvalidArgumentException('Le nom de la destination est obligatoire.');
        }

        // Règle 2 : pays obligatoire
        if (empty(trim((string) $destination->getPaysDestination()))) {
            throw new \InvalidArgumentException('Le pays de la destination est obligatoire.');
        }

        // Règle 3 : score entre 0 et 10
        $score = $destination->getScoreDestination();
        if ($score !== null && ($score < 0 || $score > 10)) {
            throw new \InvalidArgumentException('Le score doit être compris entre 0 et 10.');
        }

        // Règle 4 : latitude valide
        $lat = $destination->getLatitudeDestination();
        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            throw new \InvalidArgumentException('La latitude doit être comprise entre -90 et 90.');
        }

        // Règle 5 : longitude valide
        $lon = $destination->getLongitudeDestination();
        if ($lon !== null && ($lon < -180 || $lon > 180)) {
            throw new \InvalidArgumentException('La longitude doit être comprise entre -180 et 180.');
        }

        return true;
    }

    /**
     * Normalise le nom d'une destination (trim + lowercase) pour les comparaisons.
     */
    public function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Vérifie si deux noms de destinations sont équivalents (insensible à la casse et aux espaces).
     */
    public function areNamesEquivalent(string $nameA, string $nameB): bool
    {
        return $this->normalizeName($nameA) === $this->normalizeName($nameB);
    }
}