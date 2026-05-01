<?php

namespace App\Tests\Service;

use App\Entity\Itineraire;
use App\Entity\Voyage;
use App\Repository\ItineraireRepository;
use App\Service\ItineraireManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ItineraireManagerTest extends TestCase
{
    private ItineraireManager $itineraireManager;
    private EntityManagerInterface $entityManager;
    private ItineraireRepository $itineraireRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->itineraireRepository = $this->createMock(ItineraireRepository::class);

        $this->itineraireManager = new ItineraireManager(
            $this->entityManager,
            $this->itineraireRepository
        );
    }

    // ========== TESTS DE VALIDATION ==========

    public function testValidItineraire(): void
    {
        $voyage = $this->createMock(Voyage::class);
        
        $itineraire = new Itineraire();
        $itineraire->setNom_itineraire('Paris Tour');
        $itineraire->setDescription_itineraire('Une magnifique visite de Paris');
        $itineraire->setVoyage($voyage);

        $result = $this->itineraireManager->validate($itineraire);
        $this->assertTrue($result);
    }

    public function testItineraireWithoutName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'itinéraire est obligatoire.');

        $voyage = $this->createMock(Voyage::class);
        
        $itineraire = new Itineraire();
        $itineraire->setDescription_itineraire('Une magnifique visite de Paris');
        $itineraire->setVoyage($voyage);

        $this->itineraireManager->validate($itineraire);
    }

    public function testItineraireWithNameTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'itinéraire doit contenir au minimum 3 caractères.');

        $voyage = $this->createMock(Voyage::class);
        
        $itineraire = new Itineraire();
        $itineraire->setNom_itineraire('Pa');
        $itineraire->setDescription_itineraire('Une magnifique visite de Paris');
        $itineraire->setVoyage($voyage);

        $this->itineraireManager->validate($itineraire);
    }

    public function testItineraireWithoutDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description est obligatoire.');

        $voyage = $this->createMock(Voyage::class);
        
        $itineraire = new Itineraire();
        $itineraire->setNom_itineraire('Paris Tour');
        $itineraire->setVoyage($voyage);

        $this->itineraireManager->validate($itineraire);
    }

    public function testItineraireWithDescriptionTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description doit contenir au minimum 10 caractères.');

        $voyage = $this->createMock(Voyage::class);
        
        $itineraire = new Itineraire();
        $itineraire->setNom_itineraire('Paris Tour');
        $itineraire->setDescription_itineraire('Test');
        $itineraire->setVoyage($voyage);

        $this->itineraireManager->validate($itineraire);
    }

    public function testItineraireWithoutVoyage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'itinéraire doit être lié à un voyage.');

        $itineraire = new Itineraire();
        $itineraire->setNom_itineraire('Paris Tour');
        $itineraire->setDescription_itineraire('Une magnifique visite de Paris');

        $this->itineraireManager->validate($itineraire);
    }

    // ========== TESTS D'UNICITE ==========

    public function testUniqueNameValidation(): void
    {
        $voyage = $this->createMock(Voyage::class);
        $voyage->method('getId_voyage')->willReturn(1);
        
        $existingItineraire = $this->createMock(Itineraire::class);
        $existingItineraire->method('getId_itineraire')->willReturn(2);

        $this->itineraireRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'voyage' => $voyage,
                'nom_itineraire' => 'Paris Tour'
            ])
            ->willReturn($existingItineraire);

        $itineraire = new Itineraire();
        $itineraire->setNom_itineraire('Paris Tour');
        $itineraire->setVoyage($voyage);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un itinéraire avec ce nom existe déjà pour ce voyage.');

        $this->itineraireManager->validateUniqueName($itineraire);
    }

    public function testUniqueNameValidationExcludeCurrent(): void
    {
        $voyage = $this->createMock(Voyage::class);
        $voyage->method('getId_voyage')->willReturn(1);
        
        $existingItineraire = $this->createMock(Itineraire::class);
        $existingItineraire->method('getId_itineraire')->willReturn(1);

        $this->itineraireRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'voyage' => $voyage,
                'nom_itineraire' => 'Paris Tour'
            ])
            ->willReturn($existingItineraire);

        $itineraire = new Itineraire();
        $itineraire->setNom_itineraire('Paris Tour');
        $itineraire->setVoyage($voyage);

        // Ne doit pas lancer d'exception car on exclut l'itinéraire actuel (ID 1)
        $result = $this->itineraireManager->validateUniqueName($itineraire, 1);
        $this->assertTrue($result);
    }

    // ========== TESTS CREATE ==========

    public function testCreateItineraire(): void
    {
        $voyage = $this->createMock(Voyage::class);
        
        $this->itineraireRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $itineraire = new Itineraire();
        $itineraire->setNom_itineraire('Paris Tour');
        $itineraire->setDescription_itineraire('Une magnifique visite de Paris');
        $itineraire->setVoyage($voyage);

        $result = $this->itineraireManager->create($itineraire);
        $this->assertSame($itineraire, $result);
    }

    // ========== TESTS UPDATE ==========

    public function testUpdateItineraire(): void
    {
        $voyage = $this->createMock(Voyage::class);
        
        $this->itineraireRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $itineraire = new Itineraire();
        $itineraire->setId_itineraire(1);
        $itineraire->setNom_itineraire('Paris Tour Updated');
        $itineraire->setDescription_itineraire('Une magnifique visite de Paris mise à jour');
        $itineraire->setVoyage($voyage);

        $result = $this->itineraireManager->update($itineraire);
        $this->assertSame($itineraire, $result);
    }

    // ========== TESTS DELETE ==========

    public function testDeleteItineraire(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('remove');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $itineraire = new Itineraire();
        $this->itineraireManager->delete($itineraire);
    }

    // ========== TESTS LIKES ==========

    public function testAddLike(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $itineraire = new Itineraire();
        $itineraire->setJaime(5);
        
        $result = $this->itineraireManager->addLike($itineraire);
        
        $this->assertEquals(6, $result->getJaime());
        $this->assertSame($itineraire, $result);
    }

    public function testRemoveLike(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $itineraire = new Itineraire();
        $itineraire->setJaime(5);
        
        $result = $this->itineraireManager->removeLike($itineraire);
        
        $this->assertEquals(4, $result->getJaime());
        $this->assertSame($itineraire, $result);
    }

    public function testRemoveLikeWhenZero(): void
    {
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $itineraire = new Itineraire();
        $itineraire->setJaime(0);
        
        $result = $this->itineraireManager->removeLike($itineraire);
        
        $this->assertEquals(0, $result->getJaime());
    }
}