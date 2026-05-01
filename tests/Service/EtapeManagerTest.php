<?php

namespace App\Tests\Service;

use App\Entity\Activite;
use App\Entity\Etape;
use App\Entity\Itineraire;
use App\Entity\Voyage;
use App\Repository\EtapeRepository;
use App\Service\EtapeManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EtapeManagerTest extends TestCase
{
    private EtapeManager $etapeManager;
    private EntityManagerInterface $entityManager;
    private EtapeRepository $etapeRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->etapeRepository = $this->createMock(EtapeRepository::class);

        $this->etapeManager = new EtapeManager(
            $this->entityManager,
            $this->etapeRepository
        );
    }

    // ========== TESTS DE VALIDATION - DESCRIPTION ==========

    public function testValidEtape(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        
        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setDescription_etape('Une description complète de l\'étape');
        $etape->setHeure($heures);
        $etape->setNumero_jour(1);
        $etape->setItineraire($itineraire);

        $result = $this->etapeManager->validate($etape);
        $this->assertTrue($result);
    }

    public function testEtapeWithoutDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description de l\'étape est obligatoire.');

        $itineraire = $this->createMock(Itineraire::class);
        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setHeure($heures);
        $etape->setNumero_jour(1);
        $etape->setItineraire($itineraire);

        $this->etapeManager->validate($etape);
    }

    public function testEtapeWithDescriptionTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description doit contenir au minimum 10 caractères.');

        $itineraire = $this->createMock(Itineraire::class);
        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setDescription_etape('Test');
        $etape->setHeure($heures);
        $etape->setNumero_jour(1);
        $etape->setItineraire($itineraire);

        $this->etapeManager->validate($etape);
    }

    // ========== TESTS DE VALIDATION - HEURE ==========

    public function testEtapeWithoutHeure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'heure de l\'étape est obligatoire.');

        $itineraire = $this->createMock(Itineraire::class);
        
        $etape = new Etape();
        $etape->setDescription_etape('Une description complète de l\'étape');
        $etape->setNumero_jour(1);
        $etape->setItineraire($itineraire);

        $this->etapeManager->validate($etape);
    }

    // ========== TESTS DE VALIDATION - NUMERO JOUR ==========

    public function testEtapeWithoutNumeroJour(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de jour est obligatoire et doit être supérieur à 0.');

        $itineraire = $this->createMock(Itineraire::class);
        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setDescription_etape('Une description complète de l\'étape');
        $etape->setHeure($heures);
        $etape->setItineraire($itineraire);

        $this->etapeManager->validate($etape);
    }

    public function testEtapeWithInvalidNumeroJour(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de jour est obligatoire et doit être supérieur à 0.');

        $itineraire = $this->createMock(Itineraire::class);
        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setDescription_etape('Une description complète de l\'étape');
        $etape->setHeure($heures);
        $etape->setNumero_jour(0);
        $etape->setItineraire($itineraire);

        $this->etapeManager->validate($etape);
    }

    // ========== TESTS DE VALIDATION - ITINERAIRE ==========

    public function testEtapeWithoutItineraire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'étape doit être liée à un itinéraire.');

        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setDescription_etape('Une description complète de l\'étape');
        $etape->setHeure($heures);
        $etape->setNumero_jour(1);

        $this->etapeManager->validate($etape);
    }

    // ========== TESTS D'UNICITE DE L'HEURE ==========

    public function testUniqueHourValidation(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        
        $existingEtape = $this->createMock(Etape::class);
        $existingEtape->method('getId_etape')->willReturn(2);
        $existingHeure = new \DateTime('09:00');
        $existingEtape->method('getHeure')->willReturn($existingHeure);

        $this->etapeRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'itineraire' => $itineraire,
                'numero_jour' => 1
            ])
            ->willReturn([$existingEtape]);

        $etape = new Etape();
        $etape->setItineraire($itineraire);
        $etape->setNumero_jour(1);
        $etape->setHeure(new \DateTime('09:00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Une autre étape existe déjà à 09:00 pour le jour 1.');

        $this->etapeManager->validateUniqueHour($etape);
    }

    public function testUniqueHourValidationWithExcludeCurrent(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        
        $existingEtape = $this->createMock(Etape::class);
        $existingEtape->method('getId_etape')->willReturn(1);
        $existingHeure = new \DateTime('09:00');
        $existingEtape->method('getHeure')->willReturn($existingHeure);

        $this->etapeRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'itineraire' => $itineraire,
                'numero_jour' => 1
            ])
            ->willReturn([$existingEtape]);

        $etape = new Etape();
        $etape->setId_etape(1);
        $etape->setItineraire($itineraire);
        $etape->setNumero_jour(1);
        $etape->setHeure(new \DateTime('09:00'));

        // Ne doit pas lancer d'exception car on exclut l'étape actuelle (ID 1)
        $result = $this->etapeManager->validateUniqueHour($etape, 1);
        $this->assertTrue($result);
    }

    public function testUniqueHourValidationDifferentHour(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        
        $existingEtape = $this->createMock(Etape::class);
        $existingEtape->method('getId_etape')->willReturn(2);
        $existingHeure = new \DateTime('10:00');
        $existingEtape->method('getHeure')->willReturn($existingHeure);

        $this->etapeRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'itineraire' => $itineraire,
                'numero_jour' => 1
            ])
            ->willReturn([$existingEtape]);

        $etape = new Etape();
        $etape->setItineraire($itineraire);
        $etape->setNumero_jour(1);
        $etape->setHeure(new \DateTime('09:00'));

        $result = $this->etapeManager->validateUniqueHour($etape);
        $this->assertTrue($result);
    }

    // ========== TESTS CREATE ==========

    public function testCreateEtape(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        
        $this->etapeRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setDescription_etape('Une description complète de l\'étape');
        $etape->setHeure($heures);
        $etape->setNumero_jour(1);
        $etape->setItineraire($itineraire);

        $result = $this->etapeManager->create($etape);
        $this->assertSame($etape, $result);
    }

    // ========== TESTS UPDATE ==========

    public function testUpdateEtape(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        
        $this->etapeRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setId_etape(1);
        $etape->setDescription_etape('Une description complète de l\'étape mise à jour');
        $etape->setHeure($heures);
        $etape->setNumero_jour(1);
        $etape->setItineraire($itineraire);

        $result = $this->etapeManager->update($etape);
        $this->assertSame($etape, $result);
    }

    // ========== TESTS DELETE ==========

    public function testDeleteEtape(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('remove');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $etape = new Etape();
        $this->etapeManager->delete($etape);
    }

    // ========== TESTS NOMBRE MAX D'ETAPES PAR JOUR ==========

    public function testMaxStepsPerDayValidation(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        
        $this->etapeRepository
            ->expects($this->once())
            ->method('count')
            ->with([
                'itineraire' => $itineraire,
                'numero_jour' => 1
            ])
            ->willReturn(10);

        $etape = new Etape();
        $etape->setItineraire($itineraire);
        $etape->setNumero_jour(1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre maximum d\'étapes par jour est de 10');

        $this->etapeManager->validateMaxStepsPerDay($etape);
    }

    public function testMaxStepsPerDayValidationWithLimit(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        
        $this->etapeRepository
            ->expects($this->once())
            ->method('count')
            ->with([
                'itineraire' => $itineraire,
                'numero_jour' => 1
            ])
            ->willReturn(5);

        $etape = new Etape();
        $etape->setItineraire($itineraire);
        $etape->setNumero_jour(1);

        $result = $this->etapeManager->validateMaxStepsPerDay($etape);
        $this->assertTrue($result);
    }

    // ========== TESTS AVEC ACTIVITE OPTIONNELLE ==========

    public function testEtapeWithActivite(): void
    {
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getId_itineraire')->willReturn(1);
        $activite = $this->createMock(Activite::class);
        
        $this->etapeRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $heures = new \DateTime('14:30');
        
        $etape = new Etape();
        $etape->setDescription_etape('Visite du musée du Louvre');
        $etape->setHeure($heures);
        $etape->setNumero_jour(2);
        $etape->setItineraire($itineraire);
        $etape->setActivite($activite);

        $result = $this->etapeManager->create($etape);
        $this->assertSame($etape, $result);
        $this->assertNotNull($result->getActivite());
    }

    // ========== TEST AVEC DATE DE VOYAGE - NUMERO JOUR VALIDE ==========

    public function testEtapeWithVoyageDateRange(): void
    {
        $dateDebut = new \DateTime('2025-06-01');
        $dateFin = new \DateTime('2025-06-07');
        
        $voyage = $this->createMock(Voyage::class);
        $voyage->method('getDate_debut')->willReturn($dateDebut);
        $voyage->method('getDate_fin')->willReturn($dateFin);
        
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getVoyage')->willReturn($voyage);
        
        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setDescription_etape('Une description complète de l\'étape');
        $etape->setHeure($heures);
        $etape->setNumero_jour(7); // Jour dans la plage (7 jours: 1-7)
        $etape->setItineraire($itineraire);

        $result = $this->etapeManager->validate($etape);
        $this->assertTrue($result);
    }

    public function testEtapeWithVoyageDateRangeExceeding(): void
    {
        $dateDebut = new \DateTime('2025-06-01');
        $dateFin = new \DateTime('2025-06-07');
        
        $voyage = $this->createMock(Voyage::class);
        $voyage->method('getDate_debut')->willReturn($dateDebut);
        $voyage->method('getDate_fin')->willReturn($dateFin);
        
        $itineraire = $this->createMock(Itineraire::class);
        $itineraire->method('getVoyage')->willReturn($voyage);
        
        $heures = new \DateTime('09:00');
        
        $etape = new Etape();
        $etape->setDescription_etape('Une description complète de l\'étape');
        $etape->setHeure($heures);
        $etape->setNumero_jour(10); // Jour dépasse la durée (max 7)
        $etape->setItineraire($itineraire);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de jour (10) dépasse la durée du voyage (7 jours).');

        $this->etapeManager->validate($etape);
    }
}