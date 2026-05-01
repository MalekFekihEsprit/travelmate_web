<?php

namespace App\Service;

use App\Entity\Itineraire;
use App\Entity\Voyage;
use App\Repository\ItineraireRepository;
use Doctrine\ORM\EntityManagerInterface;

class ItineraireManager
{
    private EntityManagerInterface $entityManager;
    private ItineraireRepository $itineraireRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ItineraireRepository $itineraireRepository
    ) {
        $this->entityManager = $entityManager;
        $this->itineraireRepository = $itineraireRepository;
    }

    /**
     * Valide les règles métier d'un itinéraire
     * Règle 1: Le nom est obligatoire et doit contenir au moins 3 caractères
     * Règle 2: La description est obligatoire et doit contenir au moins 10 caractères
     * Règle 3: L'itinéraire doit être lié à un voyage
     *
     * @throws \InvalidArgumentException
     */
    public function validate(Itineraire $itineraire): bool
    {
        // Règle 1: Nom obligatoire et minimum 3 caractères
        $nom = $itineraire->getNom_itineraire();
        if (empty($nom)) {
            throw new \InvalidArgumentException('Le nom de l\'itinéraire est obligatoire.');
        }
        if (strlen($nom) < 3) {
            throw new \InvalidArgumentException('Le nom de l\'itinéraire doit contenir au minimum 3 caractères.');
        }

        // Règle 2: Description obligatoire et minimum 10 caractères
        $description = $itineraire->getDescription_itineraire();
        if (empty($description)) {
            throw new \InvalidArgumentException('La description est obligatoire.');
        }
        if (strlen($description) < 10) {
            throw new \InvalidArgumentException('La description doit contenir au minimum 10 caractères.');
        }

        // Règle 3: L'itinéraire doit être lié à un voyage
        if ($itineraire->getVoyage() === null) {
            throw new \InvalidArgumentException('L\'itinéraire doit être lié à un voyage.');
        }

        return true;
    }

    /**
     * Vérifie l'unicité du nom pour un voyage donné
     *
     * @throws \InvalidArgumentException
     */
    public function validateUniqueName(Itineraire $itineraire, ?int $excludeId = null): bool
    {
        $voyage = $itineraire->getVoyage();
        if (!$voyage) {
            return true; // La validation du voyage sera faite par validate()
        }

        $existing = $this->itineraireRepository->findOneBy([
            'voyage' => $voyage,
            'nom_itineraire' => $itineraire->getNom_itineraire()
        ]);

        if ($existing && ($excludeId === null || $existing->getId_itineraire() !== $excludeId)) {
            throw new \InvalidArgumentException('Un itinéraire avec ce nom existe déjà pour ce voyage.');
        }

        return true;
    }

    /**
     * Crée un nouvel itinéraire
     */
    public function create(Itineraire $itineraire): Itineraire
    {
        $this->validate($itineraire);
        $this->validateUniqueName($itineraire);

        $this->entityManager->persist($itineraire);
        $this->entityManager->flush();

        return $itineraire;
    }

    /**
     * Met à jour un itinéraire existant
     */
    public function update(Itineraire $itineraire): Itineraire
    {
        $this->validate($itineraire);
        $this->validateUniqueName($itineraire, $itineraire->getId_itineraire());

        $this->entityManager->flush();

        return $itineraire;
    }

    /**
     * Supprime un itinéraire
     */
    public function delete(Itineraire $itineraire): void
    {
        $this->entityManager->remove($itineraire);
        $this->entityManager->flush();
    }

    /**
     * Ajoute un "like" à l'itinéraire
     * Règle métier: Un like ne peut pas être négatif
     */
    public function addLike(Itineraire $itineraire): Itineraire
    {
        $itineraire->incrementJaime();
        $this->entityManager->flush();

        return $itineraire;
    }

    /**
     * Retire un "like" à l'itinéraire
     * Règle métier: Le nombre de likes ne peut pas être négatif
     */
    public function removeLike(Itineraire $itineraire): Itineraire
    {
        if ($itineraire->getJaime() > 0) {
            $itineraire->decrementJaime();
            $this->entityManager->flush();
        }

        return $itineraire;
    }
}