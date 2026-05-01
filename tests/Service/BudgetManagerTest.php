<?php

namespace App\Tests\Service;

use App\Entity\Budget;
use App\Entity\User;
use App\Entity\Voyage;
use App\Service\BudgetManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour BudgetManager (CRUD de l'entité Budget)
 *
 * Règles métier testées :
 *  1. Le libellé est obligatoire (min 3, max 100 caractères)
 *  2. Le montant total est obligatoire et doit être > 0
 *  3. La devise est optionnelle mais doit être parmi les valeurs autorisées
 *  4. Le statut est optionnel mais doit être parmi les valeurs autorisées
 *  5. La description est optionnelle (max 500 caractères)
 */
class BudgetManagerTest extends TestCase
{
    private BudgetManager $manager;

    protected function setUp(): void
    {
        $this->manager = new BudgetManager();
    }

    // =========================================================
    //  Helper : crée un Budget valide par défaut
    // =========================================================

    private function makeValidBudget(): Budget
    {
        $budget = new Budget();
        $budget->setLibelleBudget('Voyage à Paris');
        // Note: setMontantTotal attend un string dans l'entité !
        $budget->setMontantTotal('2500.00');
        $budget->setDeviseBudget('EUR');
        $budget->setStatutBudget('actif');
        $budget->setDescriptionBudget('Budget pour le séjour de 5 jours');

        // Optional: add relationships if needed for validation
        $userMock = $this->createMock(User::class);
        $voyageMock = $this->createMock(Voyage::class);
        $budget->setUser($userMock);
        $budget->setVoyage($voyageMock);

        return $budget;
    }

    // =========================================================
    //  1. LIBELLÉ
    // =========================================================

    public function testLibelleValide(): void
    {
        $budget = $this->makeValidBudget();
        $this->assertTrue($this->manager->validateLibelle($budget->getLibelleBudget()));
    }

    public function testLibelleObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le libellé du budget est obligatoire');
        $this->manager->validateLibelle('');
    }

    public function testLibelleNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le libellé du budget est obligatoire');
        $this->manager->validateLibelle(null);
    }

    public function testLibelleTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('au moins 3 caractères');
        $this->manager->validateLibelle('AB');
    }

    public function testLibelleTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne doit pas dépasser 100 caractères');
        $this->manager->validateLibelle(str_repeat('A', 101));
    }

    public function testLibelleExactementMinimum(): void
    {
        $this->assertTrue($this->manager->validateLibelle('ABC'));
    }

    public function testLibelleExactementMaximum(): void
    {
        $this->assertTrue($this->manager->validateLibelle(str_repeat('A', 100)));
    }

    public function testLibelleAvecEspaces(): void
    {
        $this->assertTrue($this->manager->validateLibelle('  Budget Vacances  '));
    }

    // =========================================================
    //  2. MONTANT TOTAL
    // =========================================================

    public function testMontantTotalValide(): void
    {
        $budget = $this->makeValidBudget();
        // getMontantTotal retourne un string, on le convertit en float pour la validation
        $this->assertTrue($this->manager->validateMontantTotal((float)$budget->getMontantTotal()));
    }

    public function testMontantTotalPositif(): void
    {
        $this->assertTrue($this->manager->validateMontantTotal(100.50));
        $this->assertTrue($this->manager->validateMontantTotal(0.01));
        $this->assertTrue($this->manager->validateMontantTotal(9999999.99));
    }

    public function testMontantTotalZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strictement positif');
        $this->manager->validateMontantTotal(0);
    }

    public function testMontantTotalNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strictement positif');
        $this->manager->validateMontantTotal(-100);
    }

    public function testMontantTotalNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant total est obligatoire');
        $this->manager->validateMontantTotal(null);
    }

    public function testMontantTotalTropGrand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne doit pas dépasser');
        $this->manager->validateMontantTotal(10000000.00);
    }

    // =========================================================
    //  3. DEVISE (optionnelle)
    // =========================================================

    public function testDeviseValide(): void
    {
        $this->assertTrue($this->manager->validateDevise('EUR'));
        $this->assertTrue($this->manager->validateDevise('usd'));
        $this->assertTrue($this->manager->validateDevise('TND'));
        $this->assertTrue($this->manager->validateDevise('GBP'));
        $this->assertTrue($this->manager->validateDevise('CAD'));
        $this->assertTrue($this->manager->validateDevise('JPY'));
        $this->assertTrue($this->manager->validateDevise('CHF'));
    }

    public function testDeviseOptionnelle(): void
    {
        $this->assertTrue($this->manager->validateDevise(null));
        $this->assertTrue($this->manager->validateDevise(''));
        $this->assertTrue($this->manager->validateDevise('   '));
    }

    public function testDeviseInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Devise invalide');
        $this->manager->validateDevise('XYZ');
    }

    // =========================================================
    //  4. STATUT (optionnel)
    // =========================================================

    public function testStatutValide(): void
    {
        $this->assertTrue($this->manager->validateStatut('actif'));
        $this->assertTrue($this->manager->validateStatut('inactif'));
        $this->assertTrue($this->manager->validateStatut('clôturé'));
        $this->assertTrue($this->manager->validateStatut('en cours'));
    }

    public function testStatutOptionnel(): void
    {
        $this->assertTrue($this->manager->validateStatut(null));
        $this->assertTrue($this->manager->validateStatut(''));
        $this->assertTrue($this->manager->validateStatut('   '));
    }

    public function testStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statut invalide');
        $this->manager->validateStatut('brouillon');
    }

    // =========================================================
    //  5. DESCRIPTION (optionnelle)
    // =========================================================

    public function testDescriptionValide(): void
    {
        $budget = $this->makeValidBudget();
        $this->assertTrue($this->manager->validateDescription($budget->getDescriptionBudget()));
    }

    public function testDescriptionOptionnelle(): void
    {
        $this->assertTrue($this->manager->validateDescription(null));
        $this->assertTrue($this->manager->validateDescription(''));
        $this->assertTrue($this->manager->validateDescription('   '));
    }

    public function testDescriptionTropLongue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne doit pas dépasser 500 caractères');
        $this->manager->validateDescription(str_repeat('A', 501));
    }

    public function testDescriptionExactementMaximum(): void
    {
        $this->assertTrue($this->manager->validateDescription(str_repeat('A', 500)));
    }

    // =========================================================
    //  6. BUDGET COMPLET (validate global)
    // =========================================================

    public function testBudgetCompletValide(): void
    {
        $budget = $this->makeValidBudget();
        $this->assertTrue($this->manager->validate($budget));
    }

    public function testBudgetSansLibelle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le libellé du budget est obligatoire');
        
        $budget = $this->makeValidBudget();
        $budget->setLibelleBudget('');
        $this->manager->validate($budget);
    }

    /**
     * Test modifié car setMontantTotal attend un string, pas null
     * On teste le montant négatif à la place
     */
    public function testBudgetMontantNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strictement positif');
        
        $budget = $this->makeValidBudget();
        // setMontantTotal attend un string, on met un nombre négatif en string
        $budget->setMontantTotal('-500');
        // La validation doit échouer car le montant est négatif
        $this->manager->validate($budget);
    }

    public function testBudgetDeviseInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Devise invalide');
        
        $budget = $this->makeValidBudget();
        $budget->setDeviseBudget('XYZ');
        $this->manager->validate($budget);
    }

    public function testBudgetStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statut invalide');
        
        $budget = $this->makeValidBudget();
        $budget->setStatutBudget('Brouillon');
        $this->manager->validate($budget);
    }

    public function testBudgetDescriptionTropLongue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne doit pas dépasser 500 caractères');
        
        $budget = $this->makeValidBudget();
        $budget->setDescriptionBudget(str_repeat('A', 501));
        $this->manager->validate($budget);
    }

    // =========================================================
    //  7. GETTERS / SETTERS (test entité directe)
    // =========================================================

    public function testSetGetLibelle(): void
    {
        $budget = new Budget();
        $budget->setLibelleBudget('Budget Asie');
        $this->assertSame('Budget Asie', $budget->getLibelleBudget());
    }

    /**
     * Test corrigé : getMontantTotal retourne un string
     */
    
    public function testSetGetMontantTotal(): void
{
    $budget = new Budget();
    $budget->setMontantTotal('1500.50');
    // On accepte '1500.5' ou '1500.50'
    $this->assertIsString($budget->getMontantTotal());
    $this->assertEquals(1500.50, floatval($budget->getMontantTotal()));
}

    public function testSetGetDevise(): void
    {
        $budget = new Budget();
        $budget->setDeviseBudget('USD');
        $this->assertSame('USD', $budget->getDeviseBudget());
    }

    public function testSetGetStatut(): void
    {
        $budget = new Budget();
        $budget->setStatutBudget('actif');
        $this->assertSame('actif', $budget->getStatutBudget());
    }

    public function testSetGetDescription(): void
    {
        $budget = new Budget();
        $budget->setDescriptionBudget('Budget pour le voyage');
        $this->assertSame('Budget pour le voyage', $budget->getDescriptionBudget());
    }

    public function testGetIdBudget(): void
    {
        $budget = new Budget();
        $this->assertNull($budget->getIdBudget());
    }

    // =========================================================
    //  8. COLLECTIONS (depenses)
    // =========================================================

    public function testCollectionDepensesInitialisee(): void
    {
        $budget = new Budget();
        $this->assertCount(0, $budget->getDepenses());
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $budget->getDepenses());
    }

    // =========================================================
    //  9. STATIC HELPERS (get valid values)
    // =========================================================

    public function testGetValidCurrencies(): void
    {
        $currencies = BudgetManager::getValidCurrencies();
        $this->assertContains('TND', $currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('GBP', $currencies);
        $this->assertContains('CAD', $currencies);
        $this->assertContains('JPY', $currencies);
        $this->assertContains('CHF', $currencies);
        $this->assertCount(7, $currencies);
    }

    public function testGetValidStatuses(): void
    {
        $statuses = BudgetManager::getValidStatuses();
        $this->assertContains('actif', $statuses);
        $this->assertContains('inactif', $statuses);
        $this->assertContains('clôturé', $statuses);
        $this->assertContains('en cours', $statuses);
        $this->assertCount(4, $statuses);
    }
}