<?php

namespace App\Command;

use App\Repository\EvenementRepository;
use Cloudinary\Cloudinary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-evenements-cloudinary',
    description: 'Migre les images locales des événements vers Cloudinary',
)]
class MigrateEvenementsToCloudinaryCommand extends Command
{
    public function __construct(
        private EvenementRepository    $evenementRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration des images événements locales → Cloudinary');

        $cloudinary = new Cloudinary($_ENV['CLOUDINARY_URL']);

        $evenements = $this->evenementRepository->findAll();

        $migrated = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($evenements as $evenement) {
            $imagePath = $evenement->getImagePath();

            if (!$imagePath) {
                $io->text('⏭  [' . $evenement->getTitre() . '] — pas d\'image, ignoré.');
                $skipped++;
                continue;
            }

            if (str_starts_with($imagePath, 'http')) {
                $io->text('✅ [' . $evenement->getTitre() . '] — déjà sur Cloudinary, ignoré.');
                $skipped++;
                continue;
            }

            // imagePath stocké en BDD : juste le nom du fichier (ex: mon-image-abc123.jpg)
            // Chemin réel : public/uploads/evenements/mon-image-abc123.jpg
            $localPath = __DIR__ . '/../../public/uploads/evenements/' . ltrim($imagePath, '/');

            if (!file_exists($localPath)) {
                $io->warning('❌ [' . $evenement->getTitre() . '] — fichier introuvable : ' . $localPath);
                $errors++;
                continue;
            }

            try {
                $result = $cloudinary->uploadApi()->upload(
                    $localPath,
                    [
                        'folder'         => 'evenements',
                        'transformation' => [
                            ['quality' => 'auto', 'fetch_format' => 'auto'],
                        ],
                    ]
                );

                $secureUrl = $result['secure_url'] ?? null;

                if (!$secureUrl) {
                    $io->warning('❌ [' . $evenement->getTitre() . '] — Cloudinary n\'a pas retourné d\'URL.');
                    $errors++;
                    continue;
                }

                $evenement->setImagePath($secureUrl);
                $this->entityManager->persist($evenement);

                $io->text('✅ [' . $evenement->getTitre() . '] → ' . $secureUrl);
                $migrated++;

            } catch (\Exception $e) {
                $io->warning('❌ [' . $evenement->getTitre() . '] — Erreur : ' . $e->getMessage());
                $errors++;
            }
        }

        if ($migrated > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'Migration terminée ! %d migré(s), %d ignoré(s), %d erreur(s).',
            $migrated,
            $skipped,
            $errors
        ));

        return Command::SUCCESS;
    }
}