<?php

namespace App\Tests\Service;

use App\Entity\Participation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Service\ParticipationManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service ParticipationManager.
 *
 * Règles métier testées :
 *  1. L'utilisateur est obligatoire.
 *  2. Le voyage est obligatoire.
 *  3. Le rôle doit être 'Participant' ou 'Observateur'.
 *  4. Unicité (simulée, nécessite repository pour la vraie vérification).
 *
 * Commande d'exécution :
 *   php bin/phpunit tests/Service/ParticipationManagerTest.php
 */
class ParticipationManagerTest extends TestCase
{
    private ParticipationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ParticipationManager();
    }

    // --------------------------------------------------------------
    // Helpers : création de participations valides
    // --------------------------------------------------------------

    private function createValidParticipation(): Participation
    {
        $user = $this->createMock(User::class);
        $voyage = $this->createMock(Voyage::class);

        $participation = new Participation();
        $participation->setUser($user);
        $participation->setVoyage($voyage);
        $participation->setRoleParticipation(Participation::DEFAULT_ROLE); // 'Participant'

        return $participation;
    }

    // --------------------------------------------------------------
    // Règle 1 : utilisateur obligatoire
    // --------------------------------------------------------------

    public function testValidParticipationPassesValidation(): void
    {
        $participation = $this->createValidParticipation();
        $this->assertTrue($this->manager->validate($participation));
    }

    public function testParticipationWithoutUserThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'utilisateur est obligatoire pour une participation.');

        $participation = $this->createValidParticipation();
        $participation->setUser(null);
        $this->manager->validate($participation);
    }

    // --------------------------------------------------------------
    // Règle 2 : voyage obligatoire
    // --------------------------------------------------------------

    public function testParticipationWithoutVoyageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le voyage est obligatoire pour une participation.');

        $participation = $this->createValidParticipation();
        $participation->setVoyage(null);
        $this->manager->validate($participation);
    }

    // --------------------------------------------------------------
    // Règle 3 : rôle autorisé
    // --------------------------------------------------------------

    public function testParticipationWithDefaultRoleIsValid(): void
    {
        $participation = $this->createValidParticipation();
        $participation->setRoleParticipation(Participation::DEFAULT_ROLE);
        $this->assertTrue($this->manager->validate($participation));
    }

    public function testParticipationWithObservateurRoleIsValid(): void
    {
        $participation = $this->createValidParticipation();
        $participation->setRoleParticipation('Observateur');
        $this->assertTrue($this->manager->validate($participation));
    }

    public function testParticipationWithOrganisateurRoleIsNotSelectable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le rôle "Organisateur" n\'est pas autorisé');

        $participation = $this->createValidParticipation();
        $participation->setRoleParticipation('Organisateur');
        $this->manager->validate($participation);
    }

    public function testParticipationWithInvalidRoleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le rôle "Chef" n\'est pas autorisé');

        $participation = $this->createValidParticipation();
        $participation->setRoleParticipation('Chef');
        $this->manager->validate($participation);
    }

    public function testParticipationWithEmptyRoleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le rôle "" n\'est pas autorisé');

        $participation = $this->createValidParticipation();
        $participation->setRoleParticipation('');
        $this->manager->validate($participation);
    }

    // --------------------------------------------------------------
    // Règle 4 : unicité (simulation sans repository)
    // --------------------------------------------------------------

    public function testIsUniqueReturnsTrueWhenNoRepositoryGiven(): void
    {
        $participation = $this->createValidParticipation();
        $this->assertTrue($this->manager->isUnique($participation));
    }

    // --------------------------------------------------------------
    // Tests supplémentaires : getters/setters et constantes
    // --------------------------------------------------------------

    public function testGetAvailableRolesReturnsAllRoles(): void
    {
        $roles = Participation::getAvailableRoles();
        $expected = ['Participant', 'Organisateur', 'Observateur'];
        $this->assertEquals($expected, $roles);
    }

    public function testGetSelectableRolesReturnsOnlySelectableRoles(): void
    {
        $roles = Participation::getSelectableRoles();
        $expected = ['Participant', 'Observateur'];
        $this->assertEquals($expected, $roles);
    }

    public function testIsSelectableRoleReturnsTrueForSelectableRoles(): void
    {
        $this->assertTrue(Participation::isSelectableRole('Participant'));
        $this->assertTrue(Participation::isSelectableRole('Observateur'));
        $this->assertFalse(Participation::isSelectableRole('Organisateur'));
        $this->assertFalse(Participation::isSelectableRole('Inconnu'));
    }

    public function testSetGetRoleParticipation(): void
    {
        $participation = new Participation();
        $participation->setRoleParticipation('Observateur');
        $this->assertSame('Observateur', $participation->getRoleParticipation());
        $this->assertSame('Observateur', $participation->getRole_participation());
    }

    public function testSetGetUser(): void
    {
        $user = $this->createMock(User::class);
        $participation = new Participation();
        $participation->setUser($user);
        $this->assertSame($user, $participation->getUser());
    }

    public function testSetGetVoyage(): void
    {
        $voyage = $this->createMock(Voyage::class);
        $participation = new Participation();
        $participation->setVoyage($voyage);
        $this->assertSame($voyage, $participation->getVoyage());
    }
}