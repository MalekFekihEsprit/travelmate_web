<?php

namespace App\Tests;

use App\Entity\User;
use App\Service\UserSecurityEvaluator;
use PHPUnit\Framework\TestCase;

class UserSecurityEvaluatorTest extends TestCase
{
    public function testTrustScoreIsHighForSecureUser(): void
    {
        $user = new User();
        $user->setNom('Fekih');
        $user->setPrenom('Malek');
        $user->setEmail('malek@test.com');
        $user->setTelephone('+216 12 345 678');
        $user->setDateNaissance(new \DateTime('2000-01-01'));
        $user->setIsVerified(true);
        $user->setPhotoFileName('profile.jpg');
        $user->setFaceEmbedding('[0.1,0.2,0.3]');
        $user->setFailedLoginAttempts(0);

        $evaluator = new UserSecurityEvaluator();

        $this->assertGreaterThanOrEqual(80, $evaluator->calculateTrustScore($user));
    }

    public function testTrustScoreDecreasesAfterFailedAttempts(): void
    {
        $user = new User();
        $user->setNom('Fekih');
        $user->setPrenom('Malek');
        $user->setEmail('malek@test.com');
        $user->setDateNaissance(new \DateTime('2000-01-01'));
        $user->setIsVerified(true);
        $user->setFailedLoginAttempts(3);

        $evaluator = new UserSecurityEvaluator();

        $this->assertLessThan(80, $evaluator->calculateTrustScore($user));
    }

    public function testSecurityAlertIsSentAfterThreeFailedAttempts(): void
    {
        $user = new User();
        $user->setFailedLoginAttempts(3);

        $evaluator = new UserSecurityEvaluator();

        $this->assertTrue($evaluator->shouldSendSecurityAlert($user));
    }

    public function testSecurityAlertIsNotSentBeforeThreeFailedAttempts(): void
    {
        $user = new User();
        $user->setFailedLoginAttempts(2);

        $evaluator = new UserSecurityEvaluator();

        $this->assertFalse($evaluator->shouldSendSecurityAlert($user));
    }

    public function testProfileCompleteness(): void
    {
        $user = new User();
        $user->setNom('Ben Salah');
        $user->setPrenom('Aya');
        $user->setEmail('aya@test.com');
        $user->setTelephone('+216 99 999 999');
        $user->setDateNaissance(new \DateTime('2001-05-10'));

        $evaluator = new UserSecurityEvaluator();

        $this->assertTrue($evaluator->isProfileComplete($user));
    }
}