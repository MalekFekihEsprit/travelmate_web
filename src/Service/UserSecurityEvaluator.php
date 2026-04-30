<?php

namespace App\Service;

use App\Entity\User;

class UserSecurityEvaluator
{
    public function calculateTrustScore(User $user, bool $suspiciousLogin = false): int
    {
        $score = 0;

        if ($user->isVerified()) {
            $score += 25;
        }

        if ($user->getTelephone()) {
            $score += 10;
        }

        if ($user->getPhotoFileName() || $user->getPhotoUrl()) {
            $score += 10;
        }

        if ($user->getFaceEmbedding()) {
            $score += 20;
        }

        if ($user->getNom() && $user->getPrenom() && $user->getDateNaissance()) {
            $score += 20;
        }

        if ($user->getFailedLoginAttempts() === 0) {
            $score += 15;
        }

        if ($user->getFailedLoginAttempts() >= 3) {
            $score -= 20;
        }

        if ($suspiciousLogin) {
            $score -= 20;
        }

        return max(0, min(100, $score));
    }

    public function shouldSendSecurityAlert(User $user): bool
    {
        return $user->getFailedLoginAttempts() >= 3;
    }

    public function isProfileComplete(User $user): bool
    {
        return
            !empty($user->getNom()) &&
            !empty($user->getPrenom()) &&
            !empty($user->getEmail()) &&
            $user->getDateNaissance() !== null &&
            !empty($user->getTelephone());
    }
}