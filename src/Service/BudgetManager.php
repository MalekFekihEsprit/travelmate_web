<?php

namespace App\Service;

use App\Entity\Budget;

/**
 * Service métier pour la validation des budgets.
 * Utilisé par les tests unitaires (BudgetManagerTest).
 */
class BudgetManager
{
    // Valid currencies list (to be adjusted according to your needs)
    private const VALID_CURRENCIES = ['TND', 'EUR', 'USD', 'GBP', 'CAD', 'JPY', 'CHF'];
    
    // Valid statuses list
    private const VALID_STATUSES = ['actif', 'inactif', 'clôturé', 'en cours'];

    // -------------------------------------------------------
    // Validation du libellé
    // -------------------------------------------------------

    public function validateLibelle(?string $libelle): bool
    {
        if ($libelle === null || trim($libelle) === '') {
            throw new \InvalidArgumentException('Le libellé du budget est obligatoire.');
        }

        if (mb_strlen(trim($libelle)) < 3) {
            throw new \InvalidArgumentException('Le libellé doit contenir au moins 3 caractères.');
        }

        if (mb_strlen(trim($libelle)) > 100) {
            throw new \InvalidArgumentException('Le libellé ne doit pas dépasser 100 caractères.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation du montant total
    // -------------------------------------------------------

    public function validateMontantTotal(?float $montant): bool
    {
        if ($montant === null) {
            throw new \InvalidArgumentException('Le montant total est obligatoire.');
        }

        if (!is_numeric($montant) || $montant <= 0) {
            throw new \InvalidArgumentException('Le montant total doit être un nombre strictement positif.');
        }

        if ($montant > 9999999.99) {
            throw new \InvalidArgumentException('Le montant total ne doit pas dépasser 9 999 999.99.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation de la devise
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
    // Validation du statut
    // -------------------------------------------------------

    public function validateStatut(?string $statut): bool
    {
        // Statut is optional (nullable in entity)
        if ($statut === null || trim($statut) === '') {
            return true;
        }

        if (!in_array($statut, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Statut invalide. Valeurs autorisées : %s.', implode(', ', self::VALID_STATUSES))
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation de la description
    // -------------------------------------------------------

    public function validateDescription(?string $description): bool
    {
        // Description is optional
        if ($description === null || trim($description) === '') {
            return true;
        }

        if (mb_strlen(trim($description)) > 500) {
            throw new \InvalidArgumentException('La description ne doit pas dépasser 500 caractères.');
        }

        return true;
    }

    // -------------------------------------------------------
    // Validation globale d'un Budget
    // -------------------------------------------------------

    public function validate(Budget $budget): bool
    {
        $this->validateLibelle($budget->getLibelleBudget());
        $this->validateMontantTotal($budget->getMontantTotal());
        $this->validateDevise($budget->getDeviseBudget());
        $this->validateStatut($budget->getStatutBudget());
        $this->validateDescription($budget->getDescriptionBudget());

        return true;
    }

    // -------------------------------------------------------
    // Getters for valid values (for tests)
    // -------------------------------------------------------

    public static function getValidCurrencies(): array
    {
        return self::VALID_CURRENCIES;
    }

    public static function getValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }
}