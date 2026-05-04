<?php

namespace App\Tests\Service;

use App\Entity\Hebergement;
use App\Service\HebergementManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service HebergementManager.
 *
 * Règles métier testées :
 *  1. Le nom de l'hébergement est obligatoire.
 *  2. Le prix par nuit doit être ≥ 0.
 *  3. La note doit être comprise entre 0 et 5.
 *  4. La latitude doit être comprise entre -90 et 90.
 *  5. La longitude doit être comprise entre -180 et 180.
 *  6. Normalisation et comparaison de noms.
 *
 * Commande d'exécution :
 *   php bin/phpunit tests/Service/HebergementManagerTest.php
 */
class HebergementManagerTest extends TestCase
{
    private HebergementManager $manager;

    protected function setUp(): void
    {
        $this->manager = new HebergementManager();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function buildValidHebergement(): Hebergement
    {
        $hebergement = new Hebergement();
        $hebergement->setNomHebergement('Hôtel de Test');
        $hebergement->setPrixNuitHebergement(120.00);
        $hebergement->setNoteHebergement(4.5);
        $hebergement->setLatitudeHebergement(48.8566);
        $hebergement->setLongitudeHebergement(2.3522);
        return $hebergement;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 1 : nom obligatoire
    // ─────────────────────────────────────────────────────────────────────────

    public function testValidHebergementPassesValidation(): void
    {
        $hebergement = $this->buildValidHebergement();
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithoutNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'hébergement est obligatoire.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setNomHebergement('');
        $this->manager->validate($hebergement);
    }

    public function testHebergementWithWhitespaceOnlyNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'hébergement est obligatoire.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setNomHebergement('   ');
        $this->manager->validate($hebergement);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 2 : prix par nuit ≥ 0
    // ─────────────────────────────────────────────────────────────────────────

    public function testHebergementWithNullPriceIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setPrixNuitHebergement(null);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithZeroPriceIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setPrixNuitHebergement(0.0);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithNegativePriceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix par nuit doit être un nombre positif ou nul.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setPrixNuitHebergement(-50.0);
        $this->manager->validate($hebergement);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 3 : note entre 0 et 5
    // ─────────────────────────────────────────────────────────────────────────

    public function testHebergementWithNullNoteIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setNoteHebergement(null);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithMinimumNoteIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setNoteHebergement(0.0);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithMaximumNoteIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setNoteHebergement(5.0);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithNoteBelowZeroThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note doit être comprise entre 0 et 5.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setNoteHebergement(-0.1);
        $this->manager->validate($hebergement);
    }

    public function testHebergementWithNoteAboveFiveThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note doit être comprise entre 0 et 5.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setNoteHebergement(5.1);
        $this->manager->validate($hebergement);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 4 : latitude entre -90 et 90
    // ─────────────────────────────────────────────────────────────────────────

    public function testHebergementWithLatitudeAtPoleIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setLatitudeHebergement(90.0);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithNullLatitudeIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setLatitudeHebergement(null);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithLatitudeTooHighThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La latitude doit être comprise entre -90 et 90.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setLatitudeHebergement(91.0);
        $this->manager->validate($hebergement);
    }

    public function testHebergementWithLatitudeTooLowThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La latitude doit être comprise entre -90 et 90.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setLatitudeHebergement(-91.0);
        $this->manager->validate($hebergement);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 5 : longitude entre -180 et 180
    // ─────────────────────────────────────────────────────────────────────────

    public function testHebergementWithLongitudeBoundaryIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setLongitudeHebergement(-180.0);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithNullLongitudeIsValid(): void
    {
        $hebergement = $this->buildValidHebergement();
        $hebergement->setLongitudeHebergement(null);
        $this->assertTrue($this->manager->validate($hebergement));
    }

    public function testHebergementWithLongitudeTooHighThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La longitude doit être comprise entre -180 et 180.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setLongitudeHebergement(181.0);
        $this->manager->validate($hebergement);
    }

    public function testHebergementWithLongitudeTooLowThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La longitude doit être comprise entre -180 et 180.');

        $hebergement = $this->buildValidHebergement();
        $hebergement->setLongitudeHebergement(-181.0);
        $this->manager->validate($hebergement);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Règle 6 : normalisation et comparaison de noms
    // ─────────────────────────────────────────────────────────────────────────

    public function testNormalizeNameTrimsAndLowercases(): void
{
    $this->assertSame('hôtel de test', $this->manager->normalizeName('  Hôtel de Test  '));
    $this->assertSame('auberge', $this->manager->normalizeName('AUBERGE'));
    $this->assertSame('le grand', $this->manager->normalizeName(' Le Grand '));
}

    public function testAreNamesEquivalentReturnsTrueForSameName(): void
    {
        $this->assertTrue($this->manager->areNamesEquivalent('Hôtel de Test', 'hôtel de test'));
        $this->assertTrue($this->manager->areNamesEquivalent('  Le Grand  ', 'le grand'));
        $this->assertTrue($this->manager->areNamesEquivalent('AUBERGE', 'auberge'));
    }

    public function testAreNamesEquivalentReturnsFalseForDifferentNames(): void
    {
        $this->assertFalse($this->manager->areNamesEquivalent('Hôtel de Test', 'Auberge'));
        $this->assertFalse($this->manager->areNamesEquivalent('Le Grand', 'Le Petit'));
    }
}