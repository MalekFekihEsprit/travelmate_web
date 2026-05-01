<?php

namespace App\Service;

use App\Entity\Activite;
use App\Entity\Categorie;

class ActiviteManager
{
    /**
     * Valide les règles métier d'une Activite.
     * Lève \InvalidArgumentException si une règle est violée.
     */
    public function validate(Activite $activite): bool
    {
        if (empty(trim((string) $activite->getNom()))) {
            throw new \InvalidArgumentException('Le nom de l\'activité est obligatoire.');
        }

        if (mb_strlen(trim((string) $activite->getNom())) < 3) {
            throw new \InvalidArgumentException('Le nom doit comporter au moins 3 caractères.');
        }

        if (mb_strlen(trim((string) $activite->getNom())) > 100) {
            throw new \InvalidArgumentException('Le nom ne peut pas dépasser 100 caractères.');
        }

        if (empty(trim((string) $activite->getDescription()))) {
            throw new \InvalidArgumentException('La description est obligatoire.');
        }

        if (mb_strlen(trim((string) $activite->getDescription())) < 15) {
            throw new \InvalidArgumentException('La description doit comporter au moins 15 caractères.');
        }

        if ($activite->getBudget() === null) {
            throw new \InvalidArgumentException('Le budget est obligatoire.');
        }

        if ($activite->getBudget() <= 0) {
            throw new \InvalidArgumentException('Le budget doit être un nombre positif.');
        }

        if ($activite->getBudget() > 100000) {
            throw new \InvalidArgumentException('Le budget ne peut pas dépasser 100 000 DT.');
        }

        $niveaux = ['facile', 'intermediaire', 'difficile', 'expert'];
        if (!in_array($activite->getNiveaudifficulte(), $niveaux, true)) {
            throw new \InvalidArgumentException('Veuillez choisir un niveau de difficulté valide.');
        }

        if ($activite->getAgemin() === null) {
            throw new \InvalidArgumentException('L\'âge minimum est obligatoire.');
        }

        if ($activite->getAgemin() < 0) {
            throw new \InvalidArgumentException('L\'âge minimum ne peut pas être négatif.');
        }

        if ($activite->getAgemin() > 120) {
            throw new \InvalidArgumentException('L\'âge minimum ne peut pas dépasser 120 ans.');
        }

        $statuts = ['active', 'inactive', 'archivee'];
        if (!in_array($activite->getStatut(), $statuts, true)) {
            throw new \InvalidArgumentException('Veuillez choisir un statut valide.');
        }

        if ($activite->getDuree() === null || $activite->getDuree() <= 0) {
            throw new \InvalidArgumentException('La durée doit être supérieure à 0.');
        }

        if ($activite->getDuree() > 720) {
            throw new \InvalidArgumentException('La durée ne peut pas dépasser 720 heures.');
        }

        if ($activite->getCategorie() === null) {
            throw new \InvalidArgumentException('Veuillez choisir une catégorie.');
        }

        return true;
    }

    /**
     * Crée une Activite avec les données fournies.
     */
    public function create(array $data): Activite
    {
        $activite = new Activite();
        $activite->setNom($data['nom']);
        $activite->setDescription($data['description']);
        $activite->setBudget($data['budget']);
        $activite->setNiveaudifficulte($data['niveaudifficulte']);
        $activite->setAgemin($data['agemin']);
        $activite->setStatut($data['statut']);
        $activite->setDuree($data['duree']);

        if (isset($data['categorie'])) {
            $activite->setCategorie($data['categorie']);
        }

        $this->validate($activite);

        return $activite;
    }

    /**
     * Met à jour une Activite existante.
     */
    public function update(Activite $activite, array $data): Activite
    {
        if (isset($data['nom']))              $activite->setNom($data['nom']);
        if (isset($data['description']))      $activite->setDescription($data['description']);
        if (isset($data['budget']))           $activite->setBudget($data['budget']);
        if (isset($data['niveaudifficulte'])) $activite->setNiveaudifficulte($data['niveaudifficulte']);
        if (isset($data['agemin']))           $activite->setAgemin($data['agemin']);
        if (isset($data['statut']))           $activite->setStatut($data['statut']);
        if (isset($data['duree']))            $activite->setDuree($data['duree']);

        $this->validate($activite);

        return $activite;
    }
}