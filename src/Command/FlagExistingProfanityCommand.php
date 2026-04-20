<?php

namespace App\Command;

use App\Entity\Avis;
use App\Service\ProfanityCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:flag-profane-reviews')]
class FlagExistingProfanityCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProfanityCheckerService $profanityChecker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $avisList = $this->em->getRepository(Avis::class)->findAll();

        $count = 0;
        foreach ($avisList as $avis) {
            if ($avis->getCommentaire() && !$avis->isFlagged()) {
                if ($this->profanityChecker->containsProfanity($avis->getCommentaire())) {
                    $avis->setIsFlagged(true);
                    $count++;
                }
            }
        }

        $this->em->flush();
        $io->success(sprintf('Flagged %d existing reviews.', $count));
        return Command::SUCCESS;
    }
}