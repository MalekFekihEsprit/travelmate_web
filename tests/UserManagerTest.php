<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité User (CRUD métier).
 *
 * Couvre :
 *  - CREATE  : création valide et rejets
 *  - READ    : getters, rôles, image de profil, téléphone
 *  - UPDATE  : modification et re-validation
 *  - DELETE  : logique de nettoyage (collections vides)
 */
class UserManagerTest extends TestCase
{
    private UserManager $manager;

    protected function setUp(): void
    {
        $this->manager = new UserManager();
    }

    // =========================================================
    //  Helpers
    // =========================================================

    private function dateNaissanceValide(): \DateTime
    {
        return new \DateTime('1995-06-15');
    }

    private function validData(): array
    {
        return [
            'nom'            => 'Ben Ali',
            'prenom'         => 'Salim',
            'email'          => 'salim.benali@email.tn',
            'date_naissance' => $this->dateNaissanceValide(),
            'mot_de_passe'   => 'Motdepasse@123',
            'role'           => 'USER',
        ];
    }

    // =========================================================
    //  CREATE — cas valides
    // =========================================================

    public function testCreateUserValide(): void
    {
        $user = $this->manager->create($this->validData());

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Ben Ali', $user->getNom());
        $this->assertEquals('Salim', $user->getPrenom());
        $this->assertEquals('salim.benali@email.tn', $user->getEmail());
        $this->assertEquals('USER', $user->getRole());
        $this->assertNotNull($user->getCreated_at());
    }

    public function testCreateUserAdmin(): void
    {
        $data = $this->validData();
        $data['role'] = 'ADMIN';
        $user = $this->manager->create($data);
        $this->assertEquals('ADMIN', $user->getRole());
    }

    public function testCreateUserRoleParDefautUser(): void
    {
        $data = $this->validData();
        unset($data['role']);
        $data['role'] = 'USER'; // explicitement USER par défaut dans create()
        $user = $this->manager->create($data);
        $this->assertEquals('USER', $user->getRole());
    }

    public function testCreateUserAvecTelephone(): void
    {
        $user = $this->manager->create($this->validData());
        $user->setTelephone('  +216 55 123 456  ');
        // setTelephone supprime les espaces
        $this->assertEquals('+21655123456', $user->getTelephone());
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

    public function testCreateEchouePrenomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('prénom');

        $data = $this->validData();
        $data['prenom'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchoueEmailVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('email');

        $data = $this->validData();
        $data['email'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchoueEmailInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('valide');

        $data = $this->validData();
        $data['email'] = 'pas-un-email';
        $this->manager->create($data);
    }

    public function testCreateEchoueEmailSansDomaine(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->validData();
        $data['email'] = 'user@';
        $this->manager->create($data);
    }

    public function testCreateEchoueDateNaissanceNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('naissance');

        $user = new User();
        $user->setNom('Test');
        $user->setPrenom('User');
        $user->setEmail('test@email.tn');
        $user->setMot_de_passe('MotDePasse123!');
        $user->setRole('USER');
        // date_naissance non définie → null

        $this->manager->validate($user);
    }

    public function testCreateEchoueDateNaissanceDansFutur(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('passé');

        $data = $this->validData();
        $data['date_naissance'] = new \DateTime('+1 year');
        $this->manager->create($data);
    }

    public function testCreateEchoueAgeMoinsde13Ans(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('13 ans');

        $data = $this->validData();
        $data['date_naissance'] = new \DateTime('-10 years');
        $this->manager->create($data);
    }

    public function testCreateEchoueMotDePasseVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mot de passe');

        $data = $this->validData();
        $data['mot_de_passe'] = '';
        $this->manager->create($data);
    }

    public function testCreateEchoueMotDePasseTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('8 caractères');

        $data = $this->validData();
        $data['mot_de_passe'] = 'abc123'; // < 8 chars
        $this->manager->create($data);
    }

    public function testCreateEchoueRoleInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ADMIN');

        $data = $this->validData();
        $data['role'] = 'SUPERUSER';
        $this->manager->create($data);
    }

    // =========================================================
    //  READ — getters et logique d'affichage
    // =========================================================

    public function testReadProprietesUser(): void
    {
        $date = new \DateTime('1990-03-20');
        $now  = new \DateTime();

        $user = new User();
        $user->setNom('Gharbi');
        $user->setPrenom('Ines');
        $user->setEmail('ines.gharbi@esprit.tn');
        $user->setDate_naissance($date);
        $user->setMot_de_passe('SecretMotDePasse!');
        $user->setRole('USER');
        $user->setCreated_at($now);

        $this->assertEquals('Gharbi', $user->getNom());
        $this->assertEquals('Ines', $user->getPrenom());
        $this->assertEquals('ines.gharbi@esprit.tn', $user->getEmail());
        $this->assertSame($date, $user->getDate_naissance());
        $this->assertEquals('USER', $user->getRole());
        $this->assertSame($now, $user->getCreated_at());
    }

    public function testReadGetRolesRetourneROLE_USER(): void
    {
        $user = $this->manager->create($this->validData());
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testReadGetRolesRetourneROLE_ADMIN(): void
    {
        $data = $this->validData();
        $data['role'] = 'ADMIN';
        $user = $this->manager->create($data);
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testReadGetUserIdentifierRetourneEmail(): void
    {
        $user = $this->manager->create($this->validData());
        $this->assertEquals('salim.benali@email.tn', $user->getUserIdentifier());
    }

    public function testReadGetPasswordRetourneMotDePasse(): void
    {
        $user = $this->manager->create($this->validData());
        $this->assertEquals('Motdepasse@123', $user->getPassword());
    }

    public function testReadGetProfileImageAvecPhotoFileName(): void
    {
        $user = $this->manager->create($this->validData());
        $user->setPhoto_file_name('avatar.png');
        $this->assertEquals('/uploads/profiles/avatar.png', $user->getProfileImage());
    }

    public function testReadGetProfileImageAvecPhotoUrl(): void
    {
        $user = $this->manager->create($this->validData());
        $user->setPhoto_url('https://example.com/photo.jpg');
        $this->assertEquals('https://example.com/photo.jpg', $user->getProfileImage());
    }

    public function testReadGetProfileImageFallbackGravatar(): void
    {
        $user = $this->manager->create($this->validData());
        // Pas de photo_file_name ni photo_url → Gravatar
        $profileImg = $user->getProfileImage();
        $this->assertStringContainsString('gravatar.com', $profileImg);
    }

    public function testReadTelephoneSupprimeLesEspaces(): void
    {
        $user = new User();
        $user->setTelephone('+216 55 123 456');
        $this->assertEquals('+21655123456', $user->getTelephone());
    }

    public function testReadTelephoneNullAccepte(): void
    {
        $user = new User();
        $user->setTelephone(null);
        $this->assertNull($user->getTelephone());
    }

    public function testReadTrustScoreDefaut50(): void
    {
        $user = new User();
        $this->assertEquals(50, $user->getTrustScore());
    }

    public function testReadFailedLoginAttemptsDefaut0(): void
    {
        $user = new User();
        $this->assertEquals(0, $user->getFailedLoginAttempts());
    }

    // =========================================================
    //  UPDATE — modification valide et invalide
    // =========================================================

    public function testUpdateUserEmailValide(): void
    {
        $user = $this->manager->create($this->validData());

        $updated = $this->manager->update($user, [
            'email' => 'nouveau@email.tn',
            'nom'   => 'Trabelsi',
        ]);

        $this->assertEquals('nouveau@email.tn', $updated->getEmail());
        $this->assertEquals('Trabelsi', $updated->getNom());
    }

    public function testUpdateMotDePasseValide(): void
    {
        $user    = $this->manager->create($this->validData());
        $updated = $this->manager->update($user, ['mot_de_passe' => 'NouveauMDP@999']);
        $this->assertEquals('NouveauMDP@999', $updated->getMot_de_passe());
    }

    public function testUpdateRoleVersAdmin(): void
    {
        $user    = $this->manager->create($this->validData());
        $updated = $this->manager->update($user, ['role' => 'ADMIN']);
        $this->assertEquals('ADMIN', $updated->getRole());
    }

    public function testUpdateEchoueEmailInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = $this->manager->create($this->validData());
        $this->manager->update($user, ['email' => 'invalide@@email']);
    }

    public function testUpdateEchoueNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = $this->manager->create($this->validData());
        $this->manager->update($user, ['nom' => '   ']);
    }

    public function testUpdateEchoueMotDePasseTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = $this->manager->create($this->validData());
        $this->manager->update($user, ['mot_de_passe' => '123']);
    }

    public function testUpdateEchoueRoleInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = $this->manager->create($this->validData());
        $this->manager->update($user, ['role' => 'MODERATEUR']);
    }

    // =========================================================
    //  DELETE — logique métier (collections)
    // =========================================================

    public function testDeleteUserCollectionsBudgetsVide(): void
    {
        $user = $this->manager->create($this->validData());
        $this->assertCount(0, $user->getBudgets());
    }

    public function testDeleteUserCollectionDestinationsVide(): void
    {
        $user = $this->manager->create($this->validData());
        $this->assertCount(0, $user->getDestinations());
    }

    public function testDeleteUserEraseCredentials(): void
    {
        $user = $this->manager->create($this->validData());
        // eraseCredentials() ne doit pas lever d'exception
        $this->assertNull($user->eraseCredentials());
    }

    // =========================================================
    //  Téléphone — validateTelephone()
    // =========================================================

    public function testValidateTelephoneValide(): void
    {
        $result = $this->manager->validateTelephone('+21655123456');
        $this->assertTrue($result);
    }

    public function testValidateTelephoneSansIndicatif(): void
    {
        $result = $this->manager->validateTelephone('55123456');
        $this->assertTrue($result);
    }

    public function testValidateTelephoneInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('téléphone');

        $this->manager->validateTelephone('abc-def');
    }

    public function testValidateTelephoneTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->validateTelephone('123'); // < 8 chiffres
    }

    // =========================================================
    //  Cas limites — sécurité
    // =========================================================

    public function testUpdateTrustScore(): void
    {
        $user = $this->manager->create($this->validData());
        $user->setTrustScore(75);
        $this->assertEquals(75, $user->getTrustScore());
    }

    public function testIncrementFailedLoginAttempts(): void
    {
        $user = $this->manager->create($this->validData());
        $user->setFailedLoginAttempts(3);
        $this->assertEquals(3, $user->getFailedLoginAttempts());
    }

    public function testSuspiciousLoginCountIncremente(): void
    {
        $user = $this->manager->create($this->validData());
        $user->setSuspiciousLoginCount(2);
        $this->assertEquals(2, $user->getSuspiciousLoginCount());
    }

    public function testIsVerifiedParDefautNull(): void
    {
        $user = new User();
        $this->assertNull($user->is_verified());
    }

    public function testSetIsVerifiedTrue(): void
    {
        $user = $this->manager->create($this->validData());
        $user->setIs_verified(true);
        $this->assertTrue($user->is_verified());
    }
}