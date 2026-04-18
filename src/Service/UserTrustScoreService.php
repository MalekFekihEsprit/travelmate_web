<?php

namespace App\Service;

use App\Entity\User;

class UserTrustScoreService
{
    public function calculate(User $user, bool $suspiciousLogin = false): int
    {
        $score = 0;

        if ($user->isVerified()) {
            $score += 25;
        }

        if ($user->getFaceEmbedding()) {
            $score += 20;
        }

        if ($user->getTelephone()) {
            $score += 10;
        }

        if ($user->getPhotoFileName() || $user->getPhotoUrl()) {
            $score += 10;
        }

        if ($user->getNom() && $user->getPrenom() && $user->getDateNaissance()) {
            $score += 20;
        }

        if ($user->getFailedLoginAttempts() === 0) {
            $score += 15;
        }

        if ($suspiciousLogin) {
            $score -= 20;
        }

        if ($user->getFailedLoginAttempts() >= 3) {
            $score -= 20;
        } elseif ($user->getFailedLoginAttempts() > 0) {
            $score -= 10;
        }

        if ($user->getSuspiciousLoginCount() > 0) {
            $score -= min(20, $user->getSuspiciousLoginCount() * 5);
        }

        return max(0, min(100, $score));
    }

    public function getLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Élevé',
            $score >= 50 => 'Moyen',
            default => 'Faible',
        };
    }
}