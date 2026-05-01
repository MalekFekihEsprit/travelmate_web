<?php

namespace App\Tests\Service;

use App\Entity\Destination;
use App\Service\DestinationManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service DestinationManager.
 *
 * Règles métier testées :
 *  1. Le nom de la destination est obligatoire.
 *  2. Le pays de la destination est obligatoire.
 *  3. Le score doit être compris entre 0 et 10.
 *  4. La latitude doit être comprise entre -90 et 90.
 *  5. La longitude doit être comprise entre -180 et 180.
 *  6. Normalisation et comparaison de noms.
 *
 * Commande d'exécution :
 *   php bin/phpunit tests/Service/DestinationManagerTest.php
 */
class DestinationManagerTest extends TestCase
{
    private DestinationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new DestinationManager();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crée une Destination valide prête à être personnalisée dans chaque test.
     */
    private function buildValidDestination(): Destination
    {
        $destination = new Destination();
        $destination->setNomDestination('Paris');
        $destination->setPaysDestination('France');
        $destination->setScoreDestination(8.5);
        $destination->setLatitudeDestination(48.8566);
        $destination->setLongitudeDestination(2.3522);
        return $destination;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 1 : nom obligatoire
    // ─────────────────────────────────────────────────────────────────────────

    public function testValidDestinationPassesValidation(): void
    {
        $destination = $this->buildValidDestination();
        $this->assertTrue($this->manager->validate($destination));
    }

    public function testDestinationWithoutNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de la destination est obligatoire.');

        $destination = $this->buildValidDestination();
        $destination->setNomDestination('');
        $this->manager->validate($destination);
    }

    public function testDestinationWithWhitespaceOnlyNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de la destination est obligatoire.');

        $destination = $this->buildValidDestination();
        $destination->setNomDestination('   ');
        $this->manager->validate($destination);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 2 : pays obligatoire
    // ─────────────────────────────────────────────────────────────────────────

    public function testDestinationWithoutCountryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le pays de la destination est obligatoire.');

        $destination = $this->buildValidDestination();
        $destination->setPaysDestination('');
        $this->manager->validate($destination);
    }

    public function testDestinationWithWhitespaceOnlyCountryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le pays de la destination est obligatoire.');

        $destination = $this->buildValidDestination();
        $destination->setPaysDestination('   ');
        $this->manager->validate($destination);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 3 : score entre 0 et 10
    // ─────────────────────────────────────────────────────────────────────────

    public function testDestinationWithScoreZeroIsValid(): void
    {
        $destination = $this->buildValidDestination();
        $destination->setScoreDestination(0.0);
        $this->assertTrue($this->manager->validate($destination));
    }

    public function testDestinationWithScoreTenIsValid(): void
    {
        $destination = $this->buildValidDestination();
        $destination->setScoreDestination(10.0);
        $this->assertTrue($this->manager->validate($destination));
    }

    public function testDestinationWithNullScoreIsValid(): void
    {
        $destination = $this->buildValidDestination();
        $destination->setScoreDestination(null);
        $this->assertTrue($this->manager->validate($destination));
    }

    public function testDestinationWithNegativeScoreThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le score doit être compris entre 0 et 10.');

        $destination = $this->buildValidDestination();
        $destination->setScoreDestination(-1.0);
        $this->manager->validate($destination);
    }

    public function testDestinationWithScoreAboveTenThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le score doit être compris entre 0 et 10.');

        $destination = $this->buildValidDestination();
        $destination->setScoreDestination(10.1);
        $this->manager->validate($destination);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 4 : latitude entre -90 et 90
    // ─────────────────────────────────────────────────────────────────────────

    public function testDestinationWithLatitudeAtPoleIsValid(): void
    {
        $destination = $this->buildValidDestination();
        $destination->setLatitudeDestination(90.0);
        $this->assertTrue($this->manager->validate($destination));
    }

    public function testDestinationWithNullLatitudeIsValid(): void
    {
        $destination = $this->buildValidDestination();
        $destination->setLatitudeDestination(null);
        $this->assertTrue($this->manager->validate($destination));
    }

    public function testDestinationWithLatitudeTooHighThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La latitude doit être comprise entre -90 et 90.');

        $destination = $this->buildValidDestination();
        $destination->setLatitudeDestination(91.0);
        $this->manager->validate($destination);
    }

    public function testDestinationWithLatitudeTooLowThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La latitude doit être comprise entre -90 et 90.');

        $destination = $this->buildValidDestination();
        $destination->setLatitudeDestination(-91.0);
        $this->manager->validate($destination);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 5 : longitude entre -180 et 180
    // ─────────────────────────────────────────────────────────────────────────

    public function testDestinationWithLongitudeBoundaryIsValid(): void
    {
        $destination = $this->buildValidDestination();
        $destination->setLongitudeDestination(-180.0);
        $this->assertTrue($this->manager->validate($destination));
    }

    public function testDestinationWithNullLongitudeIsValid(): void
    {
        $destination = $this->buildValidDestination();
        $destination->setLongitudeDestination(null);
        $this->assertTrue($this->manager->validate($destination));
    }

    public function testDestinationWithLongitudeTooHighThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La longitude doit être comprise entre -180 et 180.');

        $destination = $this->buildValidDestination();
        $destination->setLongitudeDestination(181.0);
        $this->manager->validate($destination);
    }

    public function testDestinationWithLongitudeTooLowThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La longitude doit être comprise entre -180 et 180.');

        $destination = $this->buildValidDestination();
        $destination->setLongitudeDestination(-181.0);
        $this->manager->validate($destination);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 6 : normalisation et comparaison de noms
    // ─────────────────────────────────────────────────────────────────────────

    public function testNormalizeNameTrimsAndLowercases(): void
    {
        $this->assertSame('paris', $this->manager->normalizeName('  Paris  '));
        $this->assertSame('tunis', $this->manager->normalizeName('TUNIS'));
        $this->assertSame('new york', $this->manager->normalizeName(' New York '));
    }

    public function testAreNamesEquivalentReturnsTrueForSameName(): void
    {
        $this->assertTrue($this->manager->areNamesEquivalent('Paris', 'paris'));
        $this->assertTrue($this->manager->areNamesEquivalent('  Tunis  ', 'tunis'));
        $this->assertTrue($this->manager->areNamesEquivalent('ROME', 'rome'));
    }

    public function testAreNamesEquivalentReturnsFalseForDifferentNames(): void
    {
        $this->assertFalse($this->manager->areNamesEquivalent('Paris', 'Lyon'));
        $this->assertFalse($this->manager->areNamesEquivalent('Tunis', 'Sfax'));
    }
}