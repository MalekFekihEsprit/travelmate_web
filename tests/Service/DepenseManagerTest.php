<?php

namespace App\Tests\Service;

use App\Entity\Depense;
use App\Entity\Budget;
use App\Service\DepenseManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour DepenseManager (CRUD de l'entité Depense)
 *
 * Règles métier testées :
 *  1. Le libellé est obligatoire (min 3, max 60 caractères)
 *  2. Le montant est obligatoire et doit être > 0
 *  3. La catégorie est obligatoire (parmi valeurs autorisées)
 *  4. La description est obligatoire (min 5, max 255 caractères)
 *  5. La devise est optionnelle mais doit être parmi les valeurs autorisées
 *  6. Le type de paiement est obligatoire (parmi valeurs autorisées)
 *  7. La date est obligatoire et ne peut pas être dans le futur
 */
class DepenseManagerTest extends TestCase
{
    private DepenseManager $manager;

    protected function setUp(): void
    {
        $this->manager = new DepenseManager();
    }

    // =========================================================
    //  Helper : crée une Depense valide par défaut
    // =========================================================

    private function makeValidDepense(): Depense
    {
        $depense = new Depense();
        $depense->setLibelleDepense('Dîner au restaurant');
        // setMontantDepense attend un string !
        $depense->setMontantDepense('45.50');
        $depense->setCategorieDepense('Restauration');
        $depense->setDescriptionDepense('Dîner avec des amis');
        $depense->setDeviseDepense('EUR');
        $depense->setTypePaiement('Carte bancaire');
        $depense->setDateCreation(new \DateTime('2026-05-01'));

        // Optional: add relationship
        $budgetMock = $this->createMock(Budget::class);
        $depense->setBudget($budgetMock);

        return $depense;
    }

    // =========================================================
    //  1. LIBELLÉ
    // =========================================================

    public function testLibelleValide(): void
    {
        $depense = $this->makeValidDepense();
        $this->assertTrue($this->manager->validateLibelle($depense->getLibelleDepense()));
    }

    public function testLibelleObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le libellé de la dépense est obligatoire');
        $this->manager->validateLibelle('');
    }

    public function testLibelleNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le libellé de la dépense est obligatoire');
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
        $this->expectExceptionMessage('ne doit pas dépasser 60 caractères');
        $this->manager->validateLibelle(str_repeat('A', 61));
    }

    public function testLibelleExactementMinimum(): void
    {
        $this->assertTrue($this->manager->validateLibelle('ABC'));
    }

    public function testLibelleExactementMaximum(): void
    {
        $this->assertTrue($this->manager->validateLibelle(str_repeat('A', 60)));
    }

    public function testLibelleAvecEspaces(): void
    {
        $this->assertTrue($this->manager->validateLibelle('  Dîner au restaurant  '));
    }

    // =========================================================
    //  2. MONTANT
    // =========================================================

    public function testMontantValide(): void
    {
        $depense = $this->makeValidDepense();
        // On convertit le string retourné en float pour la validation
        $this->assertTrue($this->manager->validateMontant((float)$depense->getMontantDepense()));
    }

    public function testMontantPositif(): void
    {
        $this->assertTrue($this->manager->validateMontant(10.50));
        $this->assertTrue($this->manager->validateMontant(0.01));
        $this->assertTrue($this->manager->validateMontant(9999999.99));
    }

    public function testMontantZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strictement positif');
        $this->manager->validateMontant(0);
    }

    public function testMontantNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strictement positif');
        $this->manager->validateMontant(-50);
    }

    public function testMontantNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant de la dépense est obligatoire');
        $this->manager->validateMontant(null);
    }

    public function testMontantTropGrand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne doit pas dépasser');
        $this->manager->validateMontant(10000000.00);
    }

    // =========================================================
    //  3. CATÉGORIE
    // =========================================================

    public function testCategorieValide(): void
    {
        $this->assertTrue($this->manager->validateCategorie('Hébergement'));
        $this->assertTrue($this->manager->validateCategorie('Transport'));
        $this->assertTrue($this->manager->validateCategorie('Restauration'));
        $this->assertTrue($this->manager->validateCategorie('Loisirs'));
        $this->assertTrue($this->manager->validateCategorie('Achats'));
        $this->assertTrue($this->manager->validateCategorie('Santé'));
        $this->assertTrue($this->manager->validateCategorie('Autre'));
    }

    public function testCategorieObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La catégorie de la dépense est obligatoire');
        $this->manager->validateCategorie('');
    }

    public function testCategorieNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La catégorie de la dépense est obligatoire');
        $this->manager->validateCategorie(null);
    }

    public function testCategorieInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Catégorie invalide');
        $this->manager->validateCategorie('Sport');
    }

    // =========================================================
    //  4. DESCRIPTION
    // =========================================================

    public function testDescriptionValide(): void
    {
        $depense = $this->makeValidDepense();
        $this->assertTrue($this->manager->validateDescription($depense->getDescriptionDepense()));
    }

    public function testDescriptionObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description de la dépense est obligatoire');
        $this->manager->validateDescription('');
    }

    public function testDescriptionNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description de la dépense est obligatoire');
        $this->manager->validateDescription(null);
    }

    public function testDescriptionTropCourte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('au moins 5 caractères');
        $this->manager->validateDescription('ABC');
    }

    public function testDescriptionExactementMinimum(): void
    {
        $this->assertTrue($this->manager->validateDescription('ABCDE'));
    }

    public function testDescriptionTropLongue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne doit pas dépasser 255 caractères');
        $this->manager->validateDescription(str_repeat('A', 256));
    }

    public function testDescriptionExactementMaximum(): void
    {
        $this->assertTrue($this->manager->validateDescription(str_repeat('A', 255)));
    }

    // =========================================================
    //  5. DEVISE (optionnelle)
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
    //  6. TYPE DE PAIEMENT
    // =========================================================

    public function testTypePaiementValide(): void
    {
        $this->assertTrue($this->manager->validateTypePaiement('Espèces'));
        $this->assertTrue($this->manager->validateTypePaiement('Carte bancaire'));
        $this->assertTrue($this->manager->validateTypePaiement('Virement'));
        $this->assertTrue($this->manager->validateTypePaiement('Mobile Pay'));
        $this->assertTrue($this->manager->validateTypePaiement('Autre'));
    }

    public function testTypePaiementObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de paiement est obligatoire');
        $this->manager->validateTypePaiement('');
    }

    public function testTypePaiementNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de paiement est obligatoire');
        $this->manager->validateTypePaiement(null);
    }

    public function testTypePaiementInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Type de paiement invalide');
        $this->manager->validateTypePaiement('Chèque');
    }

    // =========================================================
    //  7. DATE
    // =========================================================

    public function testDateValide(): void
    {
        $this->assertTrue($this->manager->validateDate('2026-05-01'));
    }

    public function testDateObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de la dépense est obligatoire');
        $this->manager->validateDate('');
    }

    public function testDateNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de la dépense est obligatoire');
        $this->manager->validateDate(null);
    }

    public function testDateFormatInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Format de date invalide');
        $this->manager->validateDate('01/05/2026');
    }

    // Test modifié : la date future est autorisée (pas de validation dans l'entité)
    // On vérifie juste que la validation accepte les dates futures si allowFuture = true
    public function testDateFutureAllowed(): void
    {
        $futureDate = (new \DateTime('+1 year'))->format('Y-m-d');
        // Avec allowFuture = true, la date future doit être acceptée
        $this->assertTrue($this->manager->validateDate($futureDate, true));
    }

    // Test pour vérifier que notre validation rejette les dates futures par défaut
    public function testDateFutureRejectedByDefault(): void
    {
        $futureDate = (new \DateTime('+1 year'))->format('Y-m-d');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne peut pas être dans le futur');
        $this->manager->validateDate($futureDate, false);
    }

    // =========================================================
    //  8. DEPENSE COMPLETE (validate global)
    // =========================================================

    public function testDepenseCompleteValide(): void
    {
        $depense = $this->makeValidDepense();
        $this->assertTrue($this->manager->validate($depense));
    }

    public function testDepenseSansLibelle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le libellé de la dépense est obligatoire');
        
        $depense = $this->makeValidDepense();
        $depense->setLibelleDepense('');
        $this->manager->validate($depense);
    }

    // Test modifié : setMontantDepense attend un string, on ne peut pas mettre null
    // On teste le montant négatif à la place
    public function testDepenseMontantNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strictement positif');
        
        $depense = $this->makeValidDepense();
        $depense->setMontantDepense('-50');
        // La validation doit échouer car le montant est négatif
        $this->manager->validate($depense);
    }

    public function testDepenseCategorieInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Catégorie invalide');
        
        $depense = $this->makeValidDepense();
        $depense->setCategorieDepense('Sport');
        $this->manager->validate($depense);
    }

    public function testDepenseSansDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description de la dépense est obligatoire');
        
        $depense = $this->makeValidDepense();
        $depense->setDescriptionDepense('');
        $this->manager->validate($depense);
    }

    public function testDepenseTypePaiementInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Type de paiement invalide');
        
        $depense = $this->makeValidDepense();
        $depense->setTypePaiement('Chèque');
        $this->manager->validate($depense);
    }

    // =========================================================
    //  9. GETTERS / SETTERS (test entité directe)
    // =========================================================

    public function testSetGetLibelle(): void
    {
        $depense = new Depense();
        $depense->setLibelleDepense('Achat de souvenirs');
        $this->assertSame('Achat de souvenirs', $depense->getLibelleDepense());
    }

    /**
     * Test corrigé : getMontantDepense retourne un string
     */
    public function testSetGetMontant(): void
    {
        $depense = new Depense();
        $depense->setMontantDepense('99.99');
        $this->assertSame('99.99', $depense->getMontantDepense());
    }

    public function testSetGetCategorie(): void
    {
        $depense = new Depense();
        $depense->setCategorieDepense('Transport');
        $this->assertSame('Transport', $depense->getCategorieDepense());
    }

    public function testSetGetDescription(): void
    {
        $depense = new Depense();
        $depense->setDescriptionDepense('Billets de train');
        $this->assertSame('Billets de train', $depense->getDescriptionDepense());
    }

    public function testSetGetDevise(): void
    {
        $depense = new Depense();
        $depense->setDeviseDepense('USD');
        $this->assertSame('USD', $depense->getDeviseDepense());
    }

    public function testSetGetTypePaiement(): void
    {
        $depense = new Depense();
        $depense->setTypePaiement('Mobile Pay');
        $this->assertSame('Mobile Pay', $depense->getTypePaiement());
    }

    public function testSetGetDateCreation(): void
    {
        $depense = new Depense();
        $date = new \DateTime('2026-06-15');
        $depense->setDateCreation($date);
        $this->assertEquals($date, $depense->getDateCreation());
    }

    public function testGetIdDepense(): void
    {
        $depense = new Depense();
        $this->assertNull($depense->getIdDepense());
    }

    // =========================================================
    //  10. RELATION BUDGET
    // =========================================================

    public function testSetGetBudget(): void
    {
        $depense = new Depense();
        $budget = new Budget();
        $depense->setBudget($budget);
        $this->assertSame($budget, $depense->getBudget());
    }

    // =========================================================
    //  11. STATIC HELPERS (get valid values)
    // =========================================================

    public function testGetValidCategories(): void
    {
        $categories = DepenseManager::getValidCategories();
        $this->assertContains('Hébergement', $categories);
        $this->assertContains('Transport', $categories);
        $this->assertContains('Restauration', $categories);
        $this->assertContains('Loisirs', $categories);
        $this->assertContains('Achats', $categories);
        $this->assertContains('Santé', $categories);
        $this->assertContains('Autre', $categories);
        $this->assertCount(7, $categories);
    }

    public function testGetValidPaymentTypes(): void
    {
        $types = DepenseManager::getValidPaymentTypes();
        $this->assertContains('Espèces', $types);
        $this->assertContains('Carte bancaire', $types);
        $this->assertContains('Virement', $types);
        $this->assertContains('Mobile Pay', $types);
        $this->assertContains('Autre', $types);
        $this->assertCount(5, $types);
    }

    public function testGetValidCurrencies(): void
    {
        $currencies = DepenseManager::getValidCurrencies();
        $this->assertContains('TND', $currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('GBP', $currencies);
        $this->assertContains('CAD', $currencies);
        $this->assertContains('JPY', $currencies);
        $this->assertContains('CHF', $currencies);
        $this->assertCount(7, $currencies);
    }
}