<?php
// src/Command/HebergementBackfillImagesCommand.php
//
// Usage :
//   php bin/console app:hebergement:backfill-images
//   php bin/console app:hebergement:backfill-images --force   (re-fetch même ceux qui ont déjà une image)

namespace App\Command;

use App\Repository\HebergementRepository;
use App\Service\WikimediaImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:hebergement:backfill-images',
    description: "Attribue une image a tous les hebergements qui n'en ont pas (via Wikimedia/Pexels + fallback Unsplash).",
)]
class HebergementBackfillImagesCommand extends Command
{
    public function __construct(
        private readonly HebergementRepository  $hebergementRepository,
        private readonly WikimediaImageService  $wikimediaImageService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Re-fetche même les hébergements qui ont déjà une image');
        $this->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Taille du batch de flush (défaut : 10)', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $batch = max(1, (int) $input->getOption('batch'));

        $all = $this->hebergementRepository->findAll();

        $toProcess = $force
            ? $all
            : array_filter($all, static fn ($h) => empty($h->getImageName()));

        $toProcess = array_values($toProcess);
        $total     = count($toProcess);

        if ($total === 0) {
            $io->success('Tous les hébergements ont déjà une image. Utilisez --force pour les re-fetcher.');
            return Command::SUCCESS;
        }

        $io->title("Backfill images — $total hébergement(s) à traiter");
        $io->progressStart($total);

        $updated = 0;
        $failed  = 0;

        foreach ($toProcess as $i => $hebergement) {
            $name        = (string) ($hebergement->getNomHebergement() ?? '');
            $destination = $hebergement->getDestination();
            $destName    = $destination ? (string) ($destination->getNomDestination() ?? '') : '';
            $destCountry = $destination ? (string) ($destination->getPaysDestination() ?? '') : '';

            $url = null;

            // ── 1. Wikimedia / Pexels ────────────────────────────────────────
            $url = $this->wikimediaImageService->findPhotoUrl($name, $destName);

            if (!$url && $destCountry) {
                $url = $this->wikimediaImageService->findPhotoUrl($name, $destCountry);
            }

            if (!$url) {
                $url = $this->wikimediaImageService->findPhotoUrl($name);
            }

            // ── 2. Fallback Unsplash source (aucune clé nécessaire) ──────────
            if (!$url) {
                $url = $this->buildUnsplashFallback($name, $destName);
            }

            if ($url) {
                $hebergement->setImageName($url);
                $hebergement->setUpdatedAt(new \DateTimeImmutable());
                ++$updated;
            } else {
                ++$failed;
            }

            // Flush par batch
            if (($i + 1) % $batch === 0) {
                $this->em->flush();
            }

            $io->progressAdvance();
        }

        // Flush restant
        $this->em->flush();
        $io->progressFinish();

        $io->success("Terminé — $updated image(s) attribuée(s), $failed sans résultat.");
        return Command::SUCCESS;
    }

    /**
     * Construit une URL Unsplash source.unsplash.com qui fonctionne SANS clé API.
     * Retourne toujours une URL valide (fallback garanti).
     */
    private function buildUnsplashFallback(string $name, string $destination): string
    {
        $nameLower = mb_strtolower($name);

        $keywords = [
            'villa'   => 'villa luxury',
            'auberge' => 'hostel interior',
            'hostel'  => 'hostel interior',
            'appart'  => 'apartment interior',
            'apart'   => 'apartment interior',
            'resort'  => 'resort pool',
            'chateau' => 'chateau castle',
            'château' => 'chateau castle',
            'camping' => 'camping nature',
            'riad'    => 'riad morocco',
            'spa'     => 'spa hotel',
        ];

        $keyword = 'hotel';
        foreach ($keywords as $k => $v) {
            if (str_contains($nameLower, $k)) {
                $keyword = $v;
                break;
            }
        }

        $query = trim($keyword . ($destination ? ' ' . $destination : ''));

        // source.unsplash.com/featured — fonctionne sans clé, retourne une image aléatoire
        return 'https://source.unsplash.com/featured/800x600/?' . urlencode($query);
    }
}