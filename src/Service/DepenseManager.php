<?php

namespace App\Service;

use App\Entity\Depense;

/**
 * Service métier pour la validation des dépenses.
 * Utilisé par les tests unitaires (DepenseManagerTest).
 */
class DepenseManager
{
    // Valid categories list
    private const VALID_CATEGORIES = ['Hébergement', 'Transport', 'Restauration', 'Loisirs', 'Achats', 'Santé', 'Autre'];
    
    // Valid payment types list
    private const VALID_PAYMENT_TYPES = ['Espèces', 'Carte bancaire', 'Virement', 'Mobile Pay', 'Autre'];
    
    // Valid currencies list
    private const VALID_CURRENCIES = ['TND', 'EUR', 'USD', 'GBP', 'CAD', 'JPY', 'CHF'];

    // -------------------------------------------------------
    // Validation du libellé
    // -------------------------------------------------------

    public function validateLibelle(?string $libelle): bool
    {
        if ($libelle === null || trim($libelle) === '') {
            throw new \InvalidArgumentException('Le libellé de la dépense est obligatoire.');
        }

        if (mb_strlen(trim($libelle)) < 3) {
            throw new \InvalidArgumentException('Le libellé doit contenir au moins 3 caractères.');
        }

        if (mb_strlen(trim($libelle)) > 60) {
            throw new \InvalidArgumentException('Le libellé ne doit pas dépasser 60 caractères.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation du montant
    // -------------------------------------------------------

    public function validateMontant(?float $montant): bool
    {
        if ($montant === null) {
            throw new \InvalidArgumentException('Le montant de la dépense est obligatoire.');
        }

        if (!is_numeric($montant) || $montant <= 0) {
            throw new \InvalidArgumentException('Le montant doit être un nombre strictement positif.');
        }

        if ($montant > 9999999.99) {
            throw new \InvalidArgumentException('Le montant ne doit pas dépasser 9 999 999.99.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation de la catégorie
    // -------------------------------------------------------

    public function validateCategorie(?string $categorie): bool
    {
        if ($categorie === null || trim($categorie) === '') {
            throw new \InvalidArgumentException('La catégorie de la dépense est obligatoire.');
        }

        if (!in_array($categorie, self::VALID_CATEGORIES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Catégorie invalide. Valeurs autorisées : %s.', implode(', ', self::VALID_CATEGORIES))
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation de la description
    // -------------------------------------------------------

    public function validateDescription(?string $description): bool
    {
        if ($description === null || trim($description) === '') {
            throw new \InvalidArgumentException('La description de la dépense est obligatoire.');
        }

        if (mb_strlen(trim($description)) > 255) {
            throw new \InvalidArgumentException('La description ne doit pas dépasser 255 caractères.');
        }

        if (mb_strlen(trim($description)) < 5) {
            throw new \InvalidArgumentException('La description doit contenir au moins 5 caractères.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation de la devise (optionnelle)
    // -------------------------------------------------------

    public function validateDevise(?string $devise): bool
    {
        // Devise is optional (nullable in entity)
        if ($devise === null || trim($devise) === '') {
            return true;
        }

        $devise = strtoupper(trim($devise));
        
        if (!in_array($devise, self::VALID_CURRENCIES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Devise invalide. Valeurs autorisées : %s.', implode(', ', self::VALID_CURRENCIES))
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation du type de paiement
    // -------------------------------------------------------

    public function validateTypePaiement(?string $typePaiement): bool
    {
        if ($typePaiement === null || trim($typePaiement) === '') {
            throw new \InvalidArgumentException('Le type de paiement est obligatoire.');
        }

        if (!in_array($typePaiement, self::VALID_PAYMENT_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Type de paiement invalide. Valeurs autorisées : %s.', implode(', ', self::VALID_PAYMENT_TYPES))
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation de la date
    // -------------------------------------------------------

    public function validateDate(?string $date, bool $allowFuture = true): bool
    {
        if ($date === null || trim($date) === '') {
            throw new \InvalidArgumentException('La date de la dépense est obligatoire.');
        }

        $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Format de date invalide. Format attendu : YYYY-MM-DD.');
        }

        if (!$allowFuture && $dateTime > new \DateTime()) {
            throw new \InvalidArgumentException('La date ne peut pas être dans le futur.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation globale d'une Depense
    // -------------------------------------------------------

    public function validate(Depense $depense): bool
    {
        $this->validateLibelle($depense->getLibelleDepense());
        $this->validateMontant($depense->getMontantDepense());
        $this->validateCategorie($depense->getCategorieDepense());
        $this->validateDescription($depense->getDescriptionDepense());
        $this->validateDevise($depense->getDeviseDepense());
        $this->validateTypePaiement($depense->getTypePaiement());
        
        $date = $depense->getDateCreation();
        if (!$date instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('La date de création est obligatoire.');
        }
        
        // La date ne peut pas être future (optionnel, à adapter selon besoin)
        if ($date > new \DateTime()) {
            throw new \InvalidArgumentException('La date de la dépense ne peut pas être dans le futur.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Getters for valid values (for tests)
    // -------------------------------------------------------

    public static function getValidCategories(): array
    {
        return self::VALID_CATEGORIES;
    }

    public static function getValidPaymentTypes(): array
    {
        return self::VALID_PAYMENT_TYPES;
    }

    public static function getValidCurrencies(): array
    {
        return self::VALID_CURRENCIES;
    }
}