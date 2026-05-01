<?php

namespace App\Service;

use App\Entity\Voyage;

/**
 * Service métier pour la validation des voyages.
 * Utilisé par les tests unitaires (VoyageManagerTest).
 */
class VoyageManager
{
    // -------------------------------------------------------
    // Validation du titre
    // -------------------------------------------------------

    public function validateTitre(?string $titre): bool
    {
        if ($titre === null || trim($titre) === '') {
            throw new \InvalidArgumentException('Le titre du voyage est obligatoire.');
        }

        if (mb_strlen(trim($titre)) < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caracteres.');
        }

        if (mb_strlen(trim($titre)) > 120) {
            throw new \InvalidArgumentException('Le titre ne doit pas depasser 120 caracteres.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation du statut
    // -------------------------------------------------------

    public function validateStatut(?string $statut): bool
    {
        if ($statut === null || trim($statut) === '') {
            throw new \InvalidArgumentException('Le statut est obligatoire.');
        }

        if (!in_array($statut, Voyage::STATUTS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Statut invalide. Valeurs autorisees : %s.', implode(', ', Voyage::STATUTS))
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation des dates
    // -------------------------------------------------------

    public function validateDates(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): bool
    {
        if ($dateFin < $dateDebut) {
            throw new \InvalidArgumentException(
                'La date de fin doit etre posterieure ou egale a la date de debut.'
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation globale d'un Voyage
    // -------------------------------------------------------

    public function validate(Voyage $voyage): bool
    {
        $this->validateTitre($voyage->getTitreVoyage());
        $this->validateStatut($voyage->getStatut());

        $debut = $voyage->getDateDebut();
        $fin   = $voyage->getDateFin();

        if (!$debut instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('La date de debut est obligatoire.');
        }

        if (!$fin instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('La date de fin est obligatoire.');
        }

        $this->validateDates($debut, $fin);

        if ($voyage->getDestination() === null) {
            throw new \InvalidArgumentException('La destination est obligatoire.');
        }

        return true;
    }
}