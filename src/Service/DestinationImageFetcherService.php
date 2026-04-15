<?php

namespace App\Service;

use App\Entity\Destination;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

class DestinationImageFetcherService
{
    private string $uploadDir;

    public function __construct(
        private readonly UnsplashImageService $unsplashService,
        private readonly ImageDownloaderService $imageDownloader,
        private readonly LoggerInterface $logger,
        string $projectDir,
    ) {
        $this->uploadDir = $projectDir . '/public/uploads/destinations/';
    }

    public function fetchAndAssign(Destination $destination): bool
    {
        $query = implode(' ', array_filter([
        $destination->getNomDestination(),
        $destination->getPaysDestination(),
        'famous landmark architecture ',
    ]));

        $imageUrl = $this->unsplashService->findPhotoUrl($query);

        if ($imageUrl === null) {
            $this->logger->warning('No Unsplash image found for: ' . $query);
            return false;
        }

        $file = $this->imageDownloader->download($imageUrl);

        if ($file === null) {
            $this->logger->warning('Failed to download image: ' . $imageUrl);
            return false;
        }

        // Generate a unique filename
        $filename = uniqid('dest_', true) . '.' . $file->getExtension();

        // Make sure upload dir exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        // Move the file manually to the upload directory
        $file->move($this->uploadDir, $filename);

        // Tell the entity about the stored filename
        $destination->setImageName($filename);
        $destination->setUpdatedAt(new \DateTimeImmutable());

        $this->logger->info('Image saved: ' . $filename);

        return true;
    }
}