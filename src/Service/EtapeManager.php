<?php

namespace App\Service;

use App\Entity\Etape;
use App\Entity\Itineraire;
use App\Repository\EtapeRepository;
use Doctrine\ORM\EntityManagerInterface;

class EtapeManager
{
    private EntityManagerInterface $entityManager;
    private EtapeRepository $etapeRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        EtapeRepository $etapeRepository
    ) {
        $this->entityManager = $entityManager;
        $this->etapeRepository = $etapeRepository;
    }

    /**
     * Valide les règles métier d'une étape
     * Règle 1: La description est obligatoire et doit contenir au moins 10 caractères
     * Règle 2: L'heure est obligatoire
     * Règle 3: Le numéro de jour est obligatoire et doit être valide (>= 1)
     * Règle 4: L'itinéraire est obligatoire
     *
     * @throws \InvalidArgumentException
     */
    public function validate(Etape $etape): bool
    {
        // Règle 1: Description obligatoire et minimum 10 caractères
        $description = $etape->getDescription_etape();
        if (empty($description)) {
            throw new \InvalidArgumentException('La description de l\'étape est obligatoire.');
        }
        if (strlen($description) < 10) {
            throw new \InvalidArgumentException('La description doit contenir au minimum 10 caractères.');
        }

        // Règle 2: Heure obligatoire
        if ($etape->getHeure() === null) {
            throw new \InvalidArgumentException('L\'heure de l\'étape est obligatoire.');
        }

        // Règle 3: Numéro de jour valide
        $numeroJour = $etape->getNumero_jour();
        if ($numeroJour === null || $numeroJour < 1) {
            throw new \InvalidArgumentException('Le numéro de jour est obligatoire et doit être supérieur à 0.');
        }

        // Règle 4: Itinéraire obligatoire
        if ($etape->getItineraire() === null) {
            throw new \InvalidArgumentException('L\'étape doit être liée à un itinéraire.');
        }

        // Vérification supplémentaire: Le numéro de jour ne doit pas dépasser la durée du voyage
        $itineraire = $etape->getItineraire();
        $voyage = $itineraire->getVoyage();
        if ($voyage && $voyage->getDate_debut() && $voyage->getDate_fin()) {
            $totalDays = $voyage->getDate_fin()->diff($voyage->getDate_debut())->days + 1;
            if ($numeroJour > $totalDays) {
                throw new \InvalidArgumentException(sprintf(
                    'Le numéro de jour (%d) dépasse la durée du voyage (%d jours).',
                    $numeroJour,
                    $totalDays
                ));
            }
        }

        return true;
    }

    /**
     * Vérifie que l'heure est unique pour un itinéraire et un jour donnés
     *
     * @throws \InvalidArgumentException
     */
    public function validateUniqueHour(Etape $etape, ?int $excludeId = null): bool
    {
        $itineraire = $etape->getItineraire();
        $numeroJour = $etape->getNumero_jour();
        $heure = $etape->getHeure();

        if (!$itineraire || !$numeroJour || !$heure) {
            return true; // Les autres validations traiteront ces cas
        }

        $existingEtapes = $this->etapeRepository->findBy([
            'itineraire' => $itineraire,
            'numero_jour' => $numeroJour
        ]);

        $heureFormatted = $heure->format('H:i');

        foreach ($existingEtapes as $existing) {
            if ($excludeId !== null && $existing->getId_etape() === $excludeId) {
                continue;
            }
            if ($existing->getHeure() && $existing->getHeure()->format('H:i') === $heureFormatted) {
                throw new \InvalidArgumentException(sprintf(
                    'Une autre étape existe déjà à %s pour le jour %d.',
                    $heureFormatted,
                    $numeroJour
                ));
            }
        }

        return true;
    }

    /**
     * Crée une nouvelle étape
     */
    public function create(Etape $etape): Etape
    {
        $this->validate($etape);
        $this->validateUniqueHour($etape);

        $this->entityManager->persist($etape);
        $this->entityManager->flush();

        return $etape;
    }

    /**
     * Met à jour une étape existante
     */
    public function update(Etape $etape): Etape
    {
        $this->validate($etape);
        $this->validateUniqueHour($etape, $etape->getId_etape());

        $this->entityManager->flush();

        return $etape;
    }

    /**
     * Supprime une étape
     */
    public function delete(Etape $etape): void
    {
        $this->entityManager->remove($etape);
        $this->entityManager->flush();
    }

    /**
     * Vérifie si le nombre d'étapes par jour dépasse une limite (optionnel)
     * Règle métier supplémentaire: Maximum 10 étapes par jour
     *
     * @throws \InvalidArgumentException
     */
    public function validateMaxStepsPerDay(Etape $etape, int $maxSteps = 10): bool
    {
        $itineraire = $etape->getItineraire();
        $numeroJour = $etape->getNumero_jour();

        if (!$itineraire || !$numeroJour) {
            return true;
        }

        // Compter les étapes existantes pour ce jour (excluant l'étape actuelle si mise à jour)
        $existingSteps = $this->etapeRepository->count([
            'itineraire' => $itineraire,
            'numero_jour' => $numeroJour
        ]);

        // Si c'est une création, ajouter 1 pour l'étape à créer
        $totalSteps = ($etape->getId_etape() === null) ? $existingSteps + 1 : $existingSteps;

        if ($totalSteps > $maxSteps) {
            throw new \InvalidArgumentException(sprintf(
                'Le nombre maximum d\'étapes par jour est de %d. Vous avez déjà %d étapes pour le jour %d.',
                $maxSteps,
                $existingSteps,
                $numeroJour
            ));
        }

        return true;
    }
}