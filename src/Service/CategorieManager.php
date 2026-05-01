<?php

namespace App\Service;

use App\Entity\Categorie;

class CategorieManager
{
    /**
     * Valide les règles métier d'une Categorie.
     */
    public function validate(Categorie $categorie): bool
    {
        if (empty(trim((string) $categorie->getNom()))) {
            throw new \InvalidArgumentException('Le nom de la catégorie est obligatoire.');
        }

        if (mb_strlen(trim((string) $categorie->getNom())) < 3) {
            throw new \InvalidArgumentException('Le nom doit comporter au moins 3 caractères.');
        }

        if (mb_strlen(trim((string) $categorie->getNom())) > 100) {
            throw new \InvalidArgumentException('Le nom ne peut pas dépasser 100 caractères.');
        }

        if (empty(trim((string) $categorie->getDescription()))) {
            throw new \InvalidArgumentException('La description est obligatoire.');
        }

        if (mb_strlen(trim((string) $categorie->getDescription())) < 15) {
            throw new \InvalidArgumentException('La description doit comporter au moins 15 caractères.');
        }

        if (empty(trim((string) $categorie->getType()))) {
            throw new \InvalidArgumentException('Le type est obligatoire.');
        }

        if (mb_strlen(trim((string) $categorie->getType())) < 3) {
            throw new \InvalidArgumentException('Le type doit comporter au moins 3 caractères.');
        }

        $saisonsValides = ['printemps', 'été', 'automne', 'hiver', 'Toutes saisons', 'Printemps', 'Été', 'Automne', 'Hiver'];
        if (!in_array($categorie->getSaison(), $saisonsValides, true)) {
            throw new \InvalidArgumentException('Veuillez choisir une saison valide.');
        }

        $niveaux = ['Faible', 'faible', 'Modéré', 'modéré', 'Élevé', 'élevé', 'Extrême', 'extrême', 'Moyen', 'moyen'];
        if (!in_array($categorie->getNiveauintensite(), $niveaux, true)) {
            throw new \InvalidArgumentException('Veuillez choisir un niveau d\'intensité valide.');
        }

        if (empty(trim((string) $categorie->getPubliccible()))) {
            throw new \InvalidArgumentException('Le public cible est obligatoire.');
        }

        if (mb_strlen(trim((string) $categorie->getPubliccible())) < 3) {
            throw new \InvalidArgumentException('Le public cible doit comporter au moins 3 caractères.');
        }

        return true;
    }

    /**
     * Crée une Categorie.
     */
    public function create(array $data): Categorie
    {
        $categorie = new Categorie();
        $categorie->setNom($data['nom']);
        $categorie->setDescription($data['description']);
        $categorie->setType($data['type']);
        $categorie->setSaison($data['saison']);
        $categorie->setNiveauintensite($data['niveauintensite']);
        $categorie->setPubliccible($data['publiccible']);

        $this->validate($categorie);

        return $categorie;
    }

    /**
     * Met à jour une Categorie.
     */
    public function update(Categorie $categorie, array $data): Categorie
    {
        if (isset($data['nom']))            $categorie->setNom($data['nom']);
        if (isset($data['description']))    $categorie->setDescription($data['description']);
        if (isset($data['type']))           $categorie->setType($data['type']);
        if (isset($data['saison']))         $categorie->setSaison($data['saison']);
        if (isset($data['niveauintensite'])) $categorie->setNiveauintensite($data['niveauintensite']);
        if (isset($data['publiccible']))    $categorie->setPubliccible($data['publiccible']);

        $this->validate($categorie);

        return $categorie;
    }
}