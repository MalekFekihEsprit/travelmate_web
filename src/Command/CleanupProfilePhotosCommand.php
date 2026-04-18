<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'app:cleanup-profile-photos')]
class CleanupProfilePhotosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel  // Change this from string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem();
        $uploadDir = $this->kernel->getProjectDir() . '/public/uploads/profiles';
        
        // Get all filenames from database
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $usedFiles = array_filter(array_map(fn($user) => $user->getPhotoFileName(), $users));
        
        // Scan upload directory
        if ($filesystem->exists($uploadDir)) {
            $scannedFiles = scandir($uploadDir);
            $deletedCount = 0;
            
            foreach ($scannedFiles as $file) {
                if ($file === '.' || $file === '..') continue;
                
                if (!in_array($file, $usedFiles)) {
                    $filesystem->remove($uploadDir.'/'.$file);
                    $output->writeln("Deleted orphaned file: $file");
                    $deletedCount++;
                }
            }
            
            $output->writeln("Cleaned up $deletedCount orphaned files.");
        } else {
            $output->writeln("Upload directory does not exist: $uploadDir");
        }
        
        return Command::SUCCESS;
    }
}