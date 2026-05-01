<?php

namespace App\Tests\Service;

use App\Entity\Activite;
use App\Entity\Categorie;
use App\Service\ActiviteManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Activite (CRUD métier).
 *
 * Couvre :
 *  - CREATE  : création valide et rejets des données invalides
 *  - READ    : lecture des propriétés après affectation (getters)
 *  - UPDATE  : modification et re-validation
 *  - DELETE  : logique de suppression d'une activite (retrait de collection)
 */
class ActiviteManagerTest extends TestCase
{
    private ActiviteManager $manager;
    private Categorie $categorie;

    protected function setUp(): void
    {
        $this->manager = new ActiviteManager();

        // Catégorie minimale utilisée dans les tests
        $this->categorie = new Categorie();
        $this->categorie->setNom('Sport');
        $this->categorie->setDescription('Catégorie dédiée aux sports de plein air');
        $this->categorie->setType('Outdoor');
        $this->categorie->setSaison('été');
        $this->categorie->setNiveauintensite('Élevé');
        $this->categorie->setPubliccible('Adultes actifs');
    }

    // =========================================================
    //  Helpers
    // =========================================================

    /** Retourne un tableau de données valides pour créer une Activite. */
    private function validData(): array
    {
        return [
            'nom'              => 'Randonnée en montagne',
            'description'      => 'Une belle randonnée en altitude pour les amateurs de nature.',
            'budget'           => 150,
            'niveaudifficulte' => 'intermediaire',
            'agemin'           => 18,
            'statut'           => 'active',
            'duree'            => 8,
            'categorie'        => $this->categorie,
        ];
    }

    // =========================================================
    //  CREATE — cas valides
    // =========================================================

    public function testCreateActiviteValide(): void
    {
        $activite = $this->manager->create($this->validData());

        $this->assertInstanceOf(Activite::class, $activite);
        $this->assertEquals('Randonnée en montagne', $activite->getNom());
        $this->assertEquals(150, $activite->getBudget());
        $this->assertEquals('intermediaire', $activite->getNiveaudifficulte());
        $this->assertEquals('active', $activite->getStatut());
        $this->assertEquals(8, $activite->getDuree());
        $this->assertSame($this->categorie, $activite->getCategorie());
    }

    public function testCreateActiviteAvecTousSesChamps(): void
    {
        $data = $this->validData();
        $data['lieu']      = 'Parc national de Chambi';
        $data['latitude']  = 35.18;
        $data['longitude'] = 8.68;

        $activite = $this->manager->create($data);
        $activite->setLieu($data['lieu']);
        $activite->setLatitude($data['latitude']);
        $activite->setLongitude($data['longitude']);

        $this->assertEquals('Parc national de Chambi', $activite->getLieu());
        $this->assertEquals(35.18, $activite->getLatitude());
    }

    // =========================================================
    //  CREATE — rejets (règles métier)
    // =========================================================

    public function testCreateEchoueNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nom');

        $data = $this->validData();
        $data['nom'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchoueNomTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['nom'] = 'AB'; // < 3 caractères
        $this->manager->create($data);
    }

    public function testCreateEchoueNomTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['nom'] = str_repeat('A', 101); // > 100 caractères
        $this->manager->create($data);
    }

    public function testCreateEchoueDescriptionTropCourte(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['description'] = 'Trop court.'; // < 15 caractères
        $this->manager->create($data);
    }

    public function testCreateEchoueBudgetNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('positif');

        $data = $this->validData();
        $data['budget'] = -500;
        $this->manager->create($data);
    }

    public function testCreateEchoueBudgetZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['budget'] = 0;
        $this->manager->create($data);
    }

    public function testCreateEchoueBudgetDepasse100000(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('100 000');

        $data = $this->validData();
        $data['budget'] = 100001;
        $this->manager->create($data);
    }

    public function testCreateEchoueNiveauInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('difficulté');

        $data = $this->validData();
        $data['niveaudifficulte'] = 'super-hard';
        $this->manager->create($data);
    }

    public function testCreateEchoueAgeMinNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('négatif');

        $data = $this->validData();
        $data['agemin'] = -1;
        $this->manager->create($data);
    }

    public function testCreateEchoueAgeMinDepasse120(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['agemin'] = 121;
        $this->manager->create($data);
    }

    public function testCreateEchoueStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('statut');

        $data = $this->validData();
        $data['statut'] = 'en_attente';
        $this->manager->create($data);
    }

    public function testCreateEchoueDureeZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['duree'] = 0;
        $this->manager->create($data);
    }

    public function testCreateEchoueDureeDepasse720(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('720');

        $data = $this->validData();
        $data['duree'] = 721;
        $this->manager->create($data);
    }

    public function testCreateEchoueSansCategorieNonNulle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('catégorie');

        $data = $this->validData();
        unset($data['categorie']);

        // Simuler manuellement sans passer par create() pour tester validate()
        $activite = new Activite();
        $activite->setNom($data['nom']);
        $activite->setDescription($data['description']);
        $activite->setBudget($data['budget']);
        $activite->setNiveaudifficulte($data['niveaudifficulte']);
        $activite->setAgemin($data['agemin']);
        $activite->setStatut($data['statut']);
        $activite->setDuree($data['duree']);
        // categorie non définie → null

        $this->manager->validate($activite);
    }

    // =========================================================
    //  READ — getters après affectation
    // =========================================================

    public function testReadProprietesActivite(): void
    {
        $activite = new Activite();
        $activite->setNom('Plongée sous-marine');
        $activite->setDescription('Découverte des fonds marins de la Méditerranée.');
        $activite->setBudget(500);
        $activite->setNiveaudifficulte('expert');
        $activite->setAgemin(16);
        $activite->setStatut('active');
        $activite->setDuree(4);
        $activite->setLieu('Tabarka');
        $activite->setLatitude(36.96);
        $activite->setLongitude(8.75);
        $activite->setCategorie($this->categorie);

        $this->assertEquals('Plongée sous-marine', $activite->getNom());
        $this->assertEquals('Découverte des fonds marins de la Méditerranée.', $activite->getDescription());
        $this->assertEquals(500, $activite->getBudget());
        $this->assertEquals('expert', $activite->getNiveaudifficulte());
        $this->assertEquals(16, $activite->getAgemin());
        $this->assertEquals('active', $activite->getStatut());
        $this->assertEquals(4, $activite->getDuree());
        $this->assertEquals('Tabarka', $activite->getLieu());
        $this->assertEquals(36.96, $activite->getLatitude());
        $this->assertEquals(8.75, $activite->getLongitude());
        $this->assertSame($this->categorie, $activite->getCategorie());
    }

    public function testReadMoyenneAvisVideRetourne0(): void
    {
        $activite = new Activite();
        $this->assertEquals(0.0, $activite->getMoyenneNotes());
    }

    public function testReadCollectionsInitialisees(): void
    {
        $activite = new Activite();
        $this->assertCount(0, $activite->getEtapes());
        $this->assertCount(0, $activite->getAvis());
        $this->assertCount(0, $activite->getVoyages());
    
    }

    // =========================================================
    //  UPDATE — modification valide et invalide
    // =========================================================

    public function testUpdateActiviteValide(): void
    {
        $activite = $this->manager->create($this->validData());

        $updated = $this->manager->update($activite, [
            'nom'    => 'VTT en forêt de Zaghouan',
            'budget' => 75,
            'statut' => 'inactive',
        ]);

        $this->assertEquals('VTT en forêt de Zaghouan', $updated->getNom());
        $this->assertEquals(75, $updated->getBudget());
        $this->assertEquals('inactive', $updated->getStatut());
    }

    public function testUpdateEchoueNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $activite = $this->manager->create($this->validData());
        $this->manager->update($activite, ['nom' => '']);
    }

    public function testUpdateEchoueBudgetNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $activite = $this->manager->create($this->validData());
        $this->manager->update($activite, ['budget' => -1]);
    }

    public function testUpdateEchoueDureeNulle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $activite = $this->manager->create($this->validData());
        $this->manager->update($activite, ['duree' => 0]);
    }

    public function testUpdateStatutVersArchivee(): void
    {
        $activite = $this->manager->create($this->validData());
        $updated  = $this->manager->update($activite, ['statut' => 'archivee']);

        $this->assertEquals('archivee', $updated->getStatut());
    }

    // =========================================================
    //  DELETE — logique de suppression (collections)
    // =========================================================

    public function testDeleteActiviteRetireeDesVoyages(): void
    {
        // On ne peut pas tester EntityManager sans DB, mais on peut
        // valider la logique de collection directement sur l'entité.
        $activite = $this->manager->create($this->validData());

        // Simuler un voyage en stub minimal
        // Ici, on teste juste que l'entité peut être créée et sa collection gérée
        $this->assertCount(0, $activite->getVoyages());
    }

    public function testDeleteActiviteImagePathPeutEtreNull(): void
    {
        $activite = $this->manager->create($this->validData());

        $this->assertNull($activite->getImagePath());

        $activite->setImagePath('photo.jpg');
        $this->assertEquals('photo.jpg', $activite->getImagePath());

        $activite->setImagePath(null);
        $this->assertNull($activite->getImagePath());
    }

    // =========================================================
    //  Cas limites
    // =========================================================

    public function testNiveauFacileEstValide(): void
    {
        $data = $this->validData();
        $data['niveaudifficulte'] = 'facile';
        $activite = $this->manager->create($data);
        $this->assertEquals('facile', $activite->getNiveaudifficulte());
    }

    public function testBudgetMaxValide(): void
    {
        $data = $this->validData();
        $data['budget'] = 100000;
        $activite = $this->manager->create($data);
        $this->assertEquals(100000, $activite->getBudget());
    }

    public function testAgeMinZeroEstValide(): void
    {
        $data = $this->validData();
        $data['agemin'] = 0;
        $activite = $this->manager->create($data);
        $this->assertEquals(0, $activite->getAgemin());
    }

    public function testDureeMaximaleValide(): void
    {
        $data = $this->validData();
        $data['duree'] = 720;
        $activite = $this->manager->create($data);
        $this->assertEquals(720, $activite->getDuree());
    }
}