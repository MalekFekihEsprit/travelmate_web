<?php

namespace App\Service;

use App\Entity\User;

class UserSecurityService
{
    public function checkLoginAttempts(User $user): bool
    {
        if ($user->getFailedLoginAttempts() >= 3) {
            return false;
        }

        return true;
    }

    public function calculateTrustScore(User $user): int
    {
        $score = 50;

        if ($user->isVerified()) $score += 20;
        if ($user->getPhotoFileName()) $score += 10;
        if ($user->getLastLogin()) $score += 10;

        return min($score, 100);
    }
}