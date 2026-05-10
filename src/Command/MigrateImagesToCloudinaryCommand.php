<?php

namespace App\Command;

use App\Repository\ActiviteRepository;
use Cloudinary\Cloudinary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-images-cloudinary',
    description: 'Migre les images locales des activités vers Cloudinary',
)]
class MigrateImagesToCloudinaryCommand extends Command
{
    public function __construct(
        private ActiviteRepository     $activiteRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration des images locales → Cloudinary');

        // ── Initialiser Cloudinary ────────────────────────────────────────
        $cloudinary = new Cloudinary($_ENV['CLOUDINARY_URL']);

        // ── Récupérer toutes les activités ────────────────────────────────
        $activites = $this->activiteRepository->findAll();

        $total    = 0;
        $migrated = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($activites as $activite) {
            $imagePath = $activite->getImagePath();

            // Ignorer si pas d'image ou si c'est déjà une URL Cloudinary
            if (!$imagePath) {
                $io->text('⏭  [' . $activite->getNom() . '] — pas d\'image, ignoré.');
                $skipped++;
                continue;
            }

            if (str_starts_with($imagePath, 'http')) {
                $io->text('✅ [' . $activite->getNom() . '] — déjà sur Cloudinary, ignoré.');
                $skipped++;
                continue;
            }

            $total++;

            // ── Construire le chemin absolu du fichier local ──────────────
            // imagePath stocké en BDD : "uploads/activites/mon-image.jpg"
            // Chemin réel sur disque   : public/uploads/activites/mon-image.jpg
            $localPath = __DIR__ . '/../../public/' . ltrim($imagePath, '/');

            if (!file_exists($localPath)) {
                $io->warning('❌ [' . $activite->getNom() . '] — fichier introuvable : ' . $localPath);
                $errors++;
                continue;
            }

            // ── Uploader vers Cloudinary ──────────────────────────────────
            try {
                $result = $cloudinary->uploadApi()->upload(
                    $localPath,
                    [
                        'folder'         => 'activites',
                        'transformation' => [
                            ['quality' => 'auto', 'fetch_format' => 'auto'],
                        ],
                    ]
                );

                $secureUrl = $result['secure_url'] ?? null;

                if (!$secureUrl) {
                    $io->warning('❌ [' . $activite->getNom() . '] — Cloudinary n\'a pas retourné d\'URL.');
                    $errors++;
                    continue;
                }

                // ── Mettre à jour la BDD ──────────────────────────────────
                $activite->setImagePath($secureUrl);
                $this->entityManager->persist($activite);

                $io->text('✅ [' . $activite->getNom() . '] → ' . $secureUrl);
                $migrated++;

            } catch (\Exception $e) {
                $io->warning('❌ [' . $activite->getNom() . '] — Erreur : ' . $e->getMessage());
                $errors++;
            }
        }

        // ── Sauvegarder toutes les modifications en une seule fois ────────
        if ($migrated > 0) {
            $this->entityManager->flush();
        }

        // ── Résumé final ──────────────────────────────────────────────────
        $io->success(sprintf(
            'Migration terminée ! %d migré(s), %d ignoré(s), %d erreur(s).',
            $migrated,
            $skipped,
            $errors
        ));

        if ($errors > 0) {
            $io->note('Vérifiez les fichiers manquants dans public/uploads/activites/');
        }

        return Command::SUCCESS;
    }
}