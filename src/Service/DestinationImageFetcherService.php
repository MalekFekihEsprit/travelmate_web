<?php

namespace App\Service;

use App\Entity\Destination;
use Psr\Log\LoggerInterface;

class DestinationImageFetcherService
{
    public function __construct(
        private readonly UnsplashImageService $unsplashService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetchAndAssign(Destination $destination): bool
    {
        $query = implode(' ', array_filter([
        $destination->getNomDestination(),
        $destination->getPaysDestination()
        //,
        //'famous landmark architecture ',
    ]));

        $imageUrl = $this->unsplashService->findPhotoUrl($query);

        if ($imageUrl === null) {
            $this->logger->warning('No Unsplash image found for: ' . $query);
            return false;
        }

        $canonicalUrl = $this->canonicalizeRemoteImageUrl($imageUrl);
        if ($canonicalUrl === '') {
            $this->logger->warning('Empty canonical image URL derived from: ' . $imageUrl);
            return false;
        }

        // Many DB schemas still have image_name as VARCHAR(255). Keep it safe.
        if (mb_strlen($canonicalUrl) > 255) {
            $this->logger->warning('Canonical image URL too long for image_name: ' . $canonicalUrl);
            return false;
        }

        // Persist the remote image URL directly (no download).
        $destination->setImageName($canonicalUrl);
        $destination->setUpdatedAt(new \DateTimeImmutable());

        $this->logger->info('Destination image URL saved: ' . $canonicalUrl);

        return true;
    }

    private function canonicalizeRemoteImageUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        // Drop query string + fragment to keep URLs short and stable.
        $trimmed = explode('#', $trimmed, 2)[0];
        $trimmed = explode('?', $trimmed, 2)[0];

        return trim($trimmed);
    }
}