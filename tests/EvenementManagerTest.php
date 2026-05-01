<?php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Service\EvenementManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Evenement (CRUD métier).
 *
 * Couvre :
 *  - CREATE  : création valide et rejets
 *  - READ    : lecture des propriétés, places restantes
 *  - UPDATE  : modification et re-validation
 *  - DELETE  : logique de nettoyage (participations)
 */
class EvenementManagerTest extends TestCase
{
    private EvenementManager $manager;

    protected function setUp(): void
    {
        $this->manager = new EvenementManager();
    }

    // =========================================================
    //  Helpers
    // =========================================================

    /** Date dans le futur (demain). */
    private function dateFuture(int $joursEnPlus = 1): \DateTime
    {
        $date = new \DateTime('today');
        $date->modify("+{$joursEnPlus} days");
        return $date;
    }

    private function validData(): array
    {
        return [
            'titre'    => 'Festival du Sahara',
            'date'     => $this->dateFuture(30),
            'heure'    => new \DateTime('18:00:00'),
            'lieu'     => 'Douz, Kébili',
            'nbPlaces' => 500,
        ];
    }

    // =========================================================
    //  CREATE — cas valides
    // =========================================================

    public function testCreateEvenementValide(): void
    {
        $evenement = $this->manager->create($this->validData());

        $this->assertInstanceOf(Evenement::class, $evenement);
        $this->assertEquals('Festival du Sahara', $evenement->getTitre());
        $this->assertEquals(500, $evenement->getNbPlaces());
        $this->assertEquals('Douz, Kébili', $evenement->getLieu());
    }

    public function testCreateAvecDescriptionOptionnelle(): void
    {
        $data = $this->validData();
        $data['description'] = 'Un festival culturel exceptionnel au cœur du désert tunisien.';

        $evenement = $this->manager->create($data);
        $evenement->setDescription($data['description']);

        $this->assertEquals($data['description'], $evenement->getDescription());
    }

    public function testCreateAvecLienGroupeTelegram(): void
    {
        $evenement = $this->manager->create($this->validData());
        $evenement->setLienGroupe('https://t.me/+abcXYZ123');

        $this->assertEquals('https://t.me/+abcXYZ123', $evenement->getLienGroupe());
    }

    public function testCreateAvecCoordonnees(): void
    {
        $evenement = $this->manager->create($this->validData());
        $evenement->setLatitude(33.50);
        $evenement->setLongitude(9.00);

        $this->assertEquals(33.50, $evenement->getLatitude());
        $this->assertEquals(9.00, $evenement->getLongitude());
    }

    // =========================================================
    //  CREATE — rejets (règles métier)
    // =========================================================

    public function testCreateEchoueTitreVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('titre');

        $data = $this->validData();
        $data['titre'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchoueTitreTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['titre'] = 'Fête'; // 4 chars, min = 5
        $this->manager->create($data);
    }

    public function testCreateEchoueTitreTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['titre'] = str_repeat('T', 256);
        $this->manager->create($data);
    }

    public function testCreateEchoueDateNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('date');

        $evenement = new Evenement();
        $evenement->setTitre('Titre valide ici');
        $evenement->setHeure(new \DateTime('10:00'));
        $evenement->setLieu('Tunis');
        $evenement->setNbPlaces(100);
        // date non définie → null

        $this->manager->validate($evenement);
    }

    public function testCreateEchoueDatePassee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('passé');

        $data = $this->validData();
        $data['date'] = new \DateTime('yesterday');
        $this->manager->create($data);
    }

    public function testCreateEchoueDateAujourdhui(): void
    {
        // La date d'aujourd'hui est >= today, donc valide (GreaterThanOrEqual 'today')
        $data = $this->validData();
        $data['date'] = new \DateTime('today');

        // Doit passer — today n'est pas dans le passé
        $evenement = $this->manager->create($data);
        $this->assertInstanceOf(Evenement::class, $evenement);
    }

    public function testCreateEchoueLieuVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('lieu');

        $data = $this->validData();
        $data['lieu'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchoueLieuTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['lieu'] = 'AB';
        $this->manager->create($data);
    }

    public function testCreateEchoueNbPlacesZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('places');

        $data = $this->validData();
        $data['nbPlaces'] = 0;
        $this->manager->create($data);
    }

    public function testCreateEchoueNbPlacesNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['nbPlaces'] = -10;
        $this->manager->create($data);
    }

    public function testCreateEchoueNbPlacesDepasse10000(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('10 000');

        $data = $this->validData();
        $data['nbPlaces'] = 10001;
        $this->manager->create($data);
    }

    public function testCreateEchoueHeureNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('heure');

        $evenement = new Evenement();
        $evenement->setTitre('Titre valide pour test');
        $evenement->setDate($this->dateFuture());
        $evenement->setLieu('Tunis');
        $evenement->setNbPlaces(100);
        // heure non définie → null

        $this->manager->validate($evenement);
    }

    // =========================================================
    //  READ — propriétés et logique métier
    // =========================================================

    public function testReadProprietesEvenement(): void
    {
        $date  = $this->dateFuture(15);
        $heure = new \DateTime('20:30');

        $evenement = new Evenement();
        $evenement->setTitre('Concert de Jazz à Carthage');
        $evenement->setDate($date);
        $evenement->setHeure($heure);
        $evenement->setLieu('Amphithéâtre de Carthage');
        $evenement->setNbPlaces(1200);

        $this->assertEquals('Concert de Jazz à Carthage', $evenement->getTitre());
        $this->assertSame($date, $evenement->getDate());
        $this->assertSame($heure, $evenement->getHeure());
        $this->assertEquals('Amphithéâtre de Carthage', $evenement->getLieu());
        $this->assertEquals(1200, $evenement->getNbPlaces());
    }

    public function testReadPlacesRestantesEgalesNbPlaces(): void
    {
        // Sans participations, les places restantes = nbPlaces
        $evenement = $this->manager->create($this->validData());
        $this->assertEquals(500, $evenement->getPlacesRestantes());
    }

    public function testReadIsCompletFaux(): void
    {
        $evenement = $this->manager->create($this->validData());
        $this->assertFalse($evenement->isComplet());
    }

    public function testReadCollectionParticipationsVideInitialement(): void
    {
        $evenement = $this->manager->create($this->validData());
        $this->assertCount(0, $evenement->getParticipations());
    }

    public function testReadIdNullParDefaut(): void
    {
        $evenement = new Evenement();
        $this->assertNull($evenement->getId());
    }

    public function testReadDescriptionNullParDefaut(): void
    {
        $evenement = new Evenement();
        $this->assertNull($evenement->getDescription());
    }

    public function testReadImagePathNullParDefaut(): void
    {
        $evenement = new Evenement();
        $this->assertNull($evenement->getImagePath());
    }

    // =========================================================
    //  UPDATE — modification valide et invalide
    // =========================================================

    public function testUpdateEvenementValide(): void
    {
        $evenement = $this->manager->create($this->validData());

        $updated = $this->manager->update($evenement, [
            'titre'    => 'Salon International du Tourisme de Tunis',
            'nbPlaces' => 2000,
        ]);

        $this->assertEquals('Salon International du Tourisme de Tunis', $updated->getTitre());
        $this->assertEquals(2000, $updated->getNbPlaces());
    }

    public function testUpdateDateVersPloinFutur(): void
    {
        $evenement  = $this->manager->create($this->validData());
        $nouvDate   = $this->dateFuture(365);
        $updated    = $this->manager->update($evenement, ['date' => $nouvDate]);

        $this->assertSame($nouvDate, $updated->getDate());
    }

    public function testUpdateEchoueTitreVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $evenement = $this->manager->create($this->validData());
        $this->manager->update($evenement, ['titre' => '']);
    }

    public function testUpdateEchoueDatePassee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $evenement = $this->manager->create($this->validData());
        $this->manager->update($evenement, ['date' => new \DateTime('2020-01-01')]);
    }

    public function testUpdateEchoueNbPlacesDepasseMax(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $evenement = $this->manager->create($this->validData());
        $this->manager->update($evenement, ['nbPlaces' => 99999]);
    }

    // =========================================================
    //  DELETE — logique de collection
    // =========================================================

    public function testDeleteRetraitImagePath(): void
    {
        $evenement = $this->manager->create($this->validData());
        $evenement->setImagePath('festival.jpg');
        $this->assertEquals('festival.jpg', $evenement->getImagePath());

        $evenement->setImagePath(null);
        $this->assertNull($evenement->getImagePath());
    }

    public function testDeleteEvenementSansParticipants(): void
    {
        $evenement = $this->manager->create($this->validData());
        // Sans EntityManager, on vérifie que la collection est accessible
        $this->assertCount(0, $evenement->getParticipations());
        $this->assertEquals(500, $evenement->getPlacesRestantes());
    }

    // =========================================================
    //  Cas limites
    // =========================================================

    public function testNbPlacesMinimum1(): void
    {
        $data = $this->validData();
        $data['nbPlaces'] = 1;
        $evenement = $this->manager->create($data);
        $this->assertEquals(1, $evenement->getNbPlaces());
    }

    public function testNbPlacesMaximum10000(): void
    {
        $data = $this->validData();
        $data['nbPlaces'] = 10000;
        $evenement = $this->manager->create($data);
        $this->assertEquals(10000, $evenement->getNbPlaces());
    }

    public function testTitreExactement5Caracteres(): void
    {
        $data = $this->validData();
        $data['titre'] = 'Festi'; // exactement 5
        $evenement = $this->manager->create($data);
        $this->assertEquals('Festi', $evenement->getTitre());
    }

    public function testTelegramGroupIdSetGet(): void
    {
        $evenement = $this->manager->create($this->validData());
        $evenement->setTelegramGroupId('-100123456789');
        $this->assertEquals('-100123456789', $evenement->getTelegramGroupId());
    }
}