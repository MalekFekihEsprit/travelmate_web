<?php

namespace App\Tests\Service;

use App\Entity\Destination;
use App\Entity\Voyage;
use App\Service\VoyageManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour VoyageManager (CRUD de l'entité Voyage)
 *
 * Règles métier testées :
 *  1. Le titre est obligatoire (min 3, max 120 caractères)
 *  2. La date de début est obligatoire
 *  3. La date de fin est obligatoire et doit être >= date de début
 *  4. Le statut doit être parmi les valeurs autorisées
 *  5. La destination est obligatoire
 */
class VoyageManagerTest extends TestCase
{
    private VoyageManager $manager;

    protected function setUp(): void
    {
        $this->manager = new VoyageManager();
    }

    // =========================================================
    //  Helper : crée un Voyage valide par défaut
    // =========================================================

    private function makeValidVoyage(): Voyage
    {
        $voyage = new Voyage();
        $voyage->setTitreVoyage('Voyage en Italie');
        $voyage->setDateDebut(new \DateTime('2026-06-01'));
        $voyage->setDateFin(new \DateTime('2026-06-15'));
        $voyage->setStatut('Planifie');

        // Ajout d'un mock de Destination pour satisfaire la validation
        $destinationMock = $this->createMock(Destination::class);
        $voyage->setDestination($destinationMock);

        return $voyage;
    }

    // =========================================================
    //  1. TITRE
    // =========================================================

    public function testTitreValide(): void
    {
        $voyage = $this->makeValidVoyage();
        $this->assertTrue($this->manager->validateTitre($voyage->getTitreVoyage()));
    }

    public function testTitreObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du voyage est obligatoire');
        $this->manager->validateTitre('');
    }

    public function testTitreTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('au moins 3 caracteres');
        $this->manager->validateTitre('AB');
    }

    public function testTitreTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('depasser 120 caracteres');
        $this->manager->validateTitre(str_repeat('A', 121));
    }

    public function testTitreExactementMinimum(): void
    {
        $this->assertTrue($this->manager->validateTitre('ABC'));
    }

    public function testTitreExactementMaximum(): void
    {
        $this->assertTrue($this->manager->validateTitre(str_repeat('A', 120)));
    }

    // =========================================================
    //  2. STATUT
    // =========================================================

    public function testStatutPlanifieValide(): void
    {
        $this->assertTrue($this->manager->validateStatut('Planifie'));
    }

    public function testStatutEnCoursValide(): void
    {
        $this->assertTrue($this->manager->validateStatut('En cours'));
    }

    public function testStatutTermineValide(): void
    {
        $this->assertTrue($this->manager->validateStatut('Termine'));
    }

    public function testStatutAnnuleValide(): void
    {
        $this->assertTrue($this->manager->validateStatut('Annule'));
    }

    public function testStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statut invalide');
        $this->manager->validateStatut('Inconnu');
    }

    public function testStatutVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut est obligatoire');
        $this->manager->validateStatut('');
    }

    // =========================================================
    //  3. DATES
    // =========================================================

    public function testDatesValides(): void
    {
        $debut = new \DateTime('2026-06-01');
        $fin   = new \DateTime('2026-06-15');
        $this->assertTrue($this->manager->validateDates($debut, $fin));
    }

    public function testDateDebutEgaleFinValide(): void
    {
        $date = new \DateTime('2026-06-01');
        $this->assertTrue($this->manager->validateDates($date, $date));
    }

    public function testDateFinAvantDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit etre posterieure ou egale');
        $debut = new \DateTime('2026-06-15');
        $fin   = new \DateTime('2026-06-01');
        $this->manager->validateDates($debut, $fin);
    }

    // =========================================================
    //  4. VOYAGE COMPLET (validate global)
    // =========================================================

    public function testVoyageCompletValide(): void
    {
        $voyage = $this->makeValidVoyage();
        $this->assertTrue($this->manager->validate($voyage));
    }

    public function testVoyageSansTitre(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $voyage = $this->makeValidVoyage();
        $voyage->setTitreVoyage('');
        $this->manager->validate($voyage);
    }

    public function testVoyageStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $voyage = $this->makeValidVoyage();
        $voyage->setStatut('Brouillon');
        $this->manager->validate($voyage);
    }

    public function testVoyageDateFinIncorrecte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $voyage = $this->makeValidVoyage();
        $voyage->setDateDebut(new \DateTime('2026-07-01'));
        $voyage->setDateFin(new \DateTime('2026-06-01'));
        $this->manager->validate($voyage);
    }

    // =========================================================
    //  5. GETTERS / SETTERS (test entité directe)
    // =========================================================

    public function testSetGetTitre(): void
    {
        $voyage = new Voyage();
        $voyage->setTitreVoyage('Sejour Alpes');
        $this->assertSame('Sejour Alpes', $voyage->getTitreVoyage());
    }

    public function testSetGetStatut(): void
    {
        $voyage = new Voyage();
        $voyage->setStatut('En cours');
        $this->assertSame('En cours', $voyage->getStatut());
    }

    public function testSetGetDateDebut(): void
    {
        $voyage = new Voyage();
        $date   = new \DateTime('2026-09-10');
        $voyage->setDateDebut($date);
        $this->assertEquals($date, $voyage->getDateDebut());
    }

    public function testSetGetDateFin(): void
    {
        $voyage = new Voyage();
        $date   = new \DateTime('2026-09-20');
        $voyage->setDateFin($date);
        $this->assertEquals($date, $voyage->getDateFin());
    }

    public function testGetAvailableStatuts(): void
    {
        $statuts = Voyage::getAvailableStatuts();
        $this->assertContains('Planifie', $statuts);
        $this->assertContains('En cours', $statuts);
        $this->assertContains('Termine', $statuts);
        $this->assertContains('Annule', $statuts);
        $this->assertCount(4, $statuts);
    }

    // =========================================================
    //  6. COLLECTIONS (budgets, itinéraires, activités)
    // =========================================================

    public function testCollectionsBudgetsInitialisee(): void
    {
        $voyage = new Voyage();
        $this->assertCount(0, $voyage->getBudgets());
    }

    public function testCollectionsItinerairesInitialisee(): void
    {
        $voyage = new Voyage();
        $this->assertCount(0, $voyage->getItineraires());
    }

    public function testCollectionsActivitesInitialisee(): void
    {
        $voyage = new Voyage();
        $this->assertCount(0, $voyage->getActivites());
    }

    public function testCollectionsParticipationsInitialisee(): void
    {
        $voyage = new Voyage();
        $this->assertCount(0, $voyage->getParticipations());
    }
}