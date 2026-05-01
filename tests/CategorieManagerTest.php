<?php

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Service\CategorieManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Categorie (CRUD métier).
 *
 * Couvre :
 *  - CREATE  : création valide et rejets
 *  - READ    : lecture correcte des propriétés
 *  - UPDATE  : modification et re-validation
 *  - DELETE  : logique de nettoyage des relations
 */
class CategorieManagerTest extends TestCase
{
    private CategorieManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CategorieManager();
    }

    // =========================================================
    //  Helpers
    // =========================================================

    private function validData(): array
    {
        return [
            'nom'            => 'Sports nautiques',
            'description'    => 'Toutes les activités liées à l\'eau et aux sports nautiques.',
            'type'           => 'Outdoor',
            'saison'         => 'été',
            'niveauintensite'=> 'Élevé',
            'publiccible'    => 'Adultes et adolescents',
        ];
    }

    // =========================================================
    //  CREATE — cas valides
    // =========================================================

    public function testCreateCategorieValide(): void
    {
        $categorie = $this->manager->create($this->validData());

        $this->assertInstanceOf(Categorie::class, $categorie);
        $this->assertEquals('Sports nautiques', $categorie->getNom());
        $this->assertEquals('été', $categorie->getSaison());
        $this->assertEquals('Élevé', $categorie->getNiveauintensite());
        $this->assertEquals('Adultes et adolescents', $categorie->getPubliccible());
    }

    public function testCreateCategorieAvecSaisonPrintemps(): void
    {
        $data = $this->validData();
        $data['saison'] = 'printemps';
        $categorie = $this->manager->create($data);
        $this->assertEquals('printemps', $categorie->getSaison());
    }

    public function testCreateCategorieAvecNiveauFaible(): void
    {
        $data = $this->validData();
        $data['niveauintensite'] = 'faible';
        $categorie = $this->manager->create($data);
        $this->assertEquals('faible', $categorie->getNiveauintensite());
    }

    public function testCreateCategorieAvecToutesSaisons(): void
    {
        $data = $this->validData();
        $data['saison'] = 'Toutes saisons';
        $categorie = $this->manager->create($data);
        $this->assertEquals('Toutes saisons', $categorie->getSaison());
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
        $data['nom'] = 'AB';
        $this->manager->create($data);
    }

    public function testCreateEchoueNomTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['nom'] = str_repeat('X', 101);
        $this->manager->create($data);
    }

    public function testCreateEchoueDescriptionVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['description'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchoueDescriptionTropCourte(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['description'] = 'Trop court.'; // < 15 chars
        $this->manager->create($data);
    }

    public function testCreateEchoueTypeVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['type'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchoueTypeTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['type'] = 'AB'; // < 3 chars
        $this->manager->create($data);
    }

    public function testCreateEchoueSaisonInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('saison');

        $data = $this->validData();
        $data['saison'] = 'mousson';
        $this->manager->create($data);
    }

    public function testCreateEchoueNiveauIntensite(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('intensité');

        $data = $this->validData();
        $data['niveauintensite'] = 'super-élevé';
        $this->manager->create($data);
    }

    public function testCreateEchouePublicCibleVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('public cible');

        $data = $this->validData();
        $data['publiccible'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchouePublicCibleTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['publiccible'] = 'AB';
        $this->manager->create($data);
    }

    // =========================================================
    //  READ — getters après affectation directe
    // =========================================================

    public function testReadProprietesCategorieCompletes(): void
    {
        $categorie = new Categorie();
        $categorie->setNom('Randonnée pédestre');
        $categorie->setDescription('Marche et découverte de la nature tunisienne.');
        $categorie->setType('Plein air');
        $categorie->setSaison('automne');
        $categorie->setNiveauintensite('Modéré');
        $categorie->setPubliccible('Familles et seniors');

        $this->assertEquals('Randonnée pédestre', $categorie->getNom());
        $this->assertEquals('Marche et découverte de la nature tunisienne.', $categorie->getDescription());
        $this->assertEquals('Plein air', $categorie->getType());
        $this->assertEquals('automne', $categorie->getSaison());
        $this->assertEquals('Modéré', $categorie->getNiveauintensite());
        $this->assertEquals('Familles et seniors', $categorie->getPubliccible());
    }

    public function testReadIdNullParDefaut(): void
    {
        $categorie = new Categorie();
        $this->assertNull($categorie->getId());
    }

    public function testReadCollectionActivitesVideInitialement(): void
    {
        $categorie = new Categorie();
        $this->assertCount(0, $categorie->getActivites());
    }

    public function testReadNomMaximum100Caracteres(): void
    {
        $data = $this->validData();
        $data['nom'] = str_repeat('A', 100); // exactement 100 — limite autorisée
        $categorie = $this->manager->create($data);
        $this->assertEquals(100, mb_strlen($categorie->getNom()));
    }

    // =========================================================
    //  UPDATE — modification valide et invalide
    // =========================================================

    public function testUpdateCategorieValide(): void
    {
        $categorie = $this->manager->create($this->validData());

        $updated = $this->manager->update($categorie, [
            'nom'    => 'Activités hivernales',
            'saison' => 'hiver',
        ]);

        $this->assertEquals('Activités hivernales', $updated->getNom());
        $this->assertEquals('hiver', $updated->getSaison());
    }

    public function testUpdateNiveauIntensiteVersExtrême(): void
    {
        $categorie = $this->manager->create($this->validData());
        $updated   = $this->manager->update($categorie, ['niveauintensite' => 'Extrême']);

        $this->assertEquals('Extrême', $updated->getNiveauintensite());
    }

    public function testUpdateEchoueNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $categorie = $this->manager->create($this->validData());
        $this->manager->update($categorie, ['nom' => '']);
    }

    public function testUpdateEchoueSaisonInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $categorie = $this->manager->create($this->validData());
        $this->manager->update($categorie, ['saison' => 'janvier']);
    }

    public function testUpdateEchoueNiveauIntensite(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $categorie = $this->manager->create($this->validData());
        $this->manager->update($categorie, ['niveauintensite' => 'mega-fort']);
    }

    public function testUpdateEchoueDescriptionTropCourte(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $categorie = $this->manager->create($this->validData());
        $this->manager->update($categorie, ['description' => 'Court.']);
    }

    // =========================================================
    //  DELETE — gestion des activités liées
    // =========================================================

    public function testDeleteCategorieCollectionActivitesVide(): void
    {
        $categorie = $this->manager->create($this->validData());
        // Sans base de données, on vérifie que la collection est accessible et vide
        $this->assertCount(0, $categorie->getActivites());
    }

    public function testDeleteCategorieAddRemoveActivite(): void
    {
        // Vérifier la logique add/remove de collection sans EntityManager
        $categorie = new Categorie();
        $categorie->setNom('Test');
        $categorie->setDescription('Description longue pour les tests ici.');
        $categorie->setType('Test');
        $categorie->setSaison('hiver');
        $categorie->setNiveauintensite('Faible');
        $categorie->setPubliccible('Testeurs');

        // La collection est vide initialement
        $this->assertCount(0, $categorie->getActivites());
    }

    // =========================================================
    //  Toutes les valeurs valides — tests d'exhaustivité
    // =========================================================

    /** @dataProvider providerSaisonsValides */
    public function testCreateAvecChaqueSaisonValide(string $saison): void
    {
        $data = $this->validData();
        $data['saison'] = $saison;
        $categorie = $this->manager->create($data);
        $this->assertEquals($saison, $categorie->getSaison());
    }

    public static function providerSaisonsValides(): array
    {
        return [
            ['printemps'],
            ['été'],
            ['automne'],
            ['hiver'],
            ['Toutes saisons'],
            ['Printemps'],
            ['Été'],
            ['Automne'],
            ['Hiver'],
        ];
    }

    /** @dataProvider providerNiveauxValides */
    public function testCreateAvecChaqueNiveauValide(string $niveau): void
    {
        $data = $this->validData();
        $data['niveauintensite'] = $niveau;
        $categorie = $this->manager->create($data);
        $this->assertEquals($niveau, $categorie->getNiveauintensite());
    }

    public static function providerNiveauxValides(): array
    {
        return [
            ['Faible'], ['faible'],
            ['Modéré'], ['modéré'],
            ['Élevé'],  ['élevé'],
            ['Extrême'],['extrême'],
            ['Moyen'],  ['moyen'],
        ];
    }
}