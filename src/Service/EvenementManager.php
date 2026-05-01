<?php

namespace App\Service;

use App\Entity\Evenement;

class EvenementManager
{
    /**
     * Valide les règles métier d'un Evenement.
     */
    public function validate(Evenement $evenement): bool
    {
        if (empty(trim((string) $evenement->getTitre()))) {
            throw new \InvalidArgumentException('Le titre de l\'événement est obligatoire.');
        }

        if (mb_strlen(trim((string) $evenement->getTitre())) < 5) {
            throw new \InvalidArgumentException('Le titre doit comporter au moins 5 caractères.');
        }

        if (mb_strlen(trim((string) $evenement->getTitre())) > 255) {
            throw new \InvalidArgumentException('Le titre ne peut pas dépasser 255 caractères.');
        }

        if ($evenement->getDate() === null) {
            throw new \InvalidArgumentException('La date est obligatoire.');
        }

        $today = new \DateTime('today');
        if ($evenement->getDate() < $today) {
            throw new \InvalidArgumentException('La date de l\'événement ne peut pas être dans le passé.');
        }

        if ($evenement->getHeure() === null) {
            throw new \InvalidArgumentException('L\'heure est obligatoire.');
        }

        if (empty(trim((string) $evenement->getLieu()))) {
            throw new \InvalidArgumentException('Le lieu est obligatoire.');
        }

        if (mb_strlen(trim((string) $evenement->getLieu())) < 3) {
            throw new \InvalidArgumentException('Le lieu doit comporter au moins 3 caractères.');
        }

        if ($evenement->getNbPlaces() === null || $evenement->getNbPlaces() <= 0) {
            throw new \InvalidArgumentException('Le nombre de places doit être supérieur à 0.');
        }

        if ($evenement->getNbPlaces() > 10000) {
            throw new \InvalidArgumentException('Le nombre de places ne peut pas dépasser 10 000.');
        }

        return true;
    }

    /**
     * Crée un Evenement.
     */
    public function create(array $data): Evenement
    {
        $evenement = new Evenement();
        $evenement->setTitre($data['titre']);
        $evenement->setDate($data['date']);
        $evenement->setHeure($data['heure']);
        $evenement->setLieu($data['lieu']);
        $evenement->setNbPlaces($data['nbPlaces']);

        if (isset($data['description'])) {
            $evenement->setDescription($data['description']);
        }

        $this->validate($evenement);

        return $evenement;
    }

    /**
     * Met à jour un Evenement.
     */
    public function update(Evenement $evenement, array $data): Evenement
    {
        if (isset($data['titre']))    $evenement->setTitre($data['titre']);
        if (isset($data['date']))     $evenement->setDate($data['date']);
        if (isset($data['heure']))    $evenement->setHeure($data['heure']);
        if (isset($data['lieu']))     $evenement->setLieu($data['lieu']);
        if (isset($data['nbPlaces'])) $evenement->setNbPlaces($data['nbPlaces']);

        $this->validate($evenement);

        return $evenement;
    }

    /**
     * Calcule les places restantes.
     */
    public function getPlacesRestantes(Evenement $evenement): int
    {
        return $evenement->getPlacesRestantes();
    }
}