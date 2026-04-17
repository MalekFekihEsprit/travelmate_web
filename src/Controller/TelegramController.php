<?php

namespace App\Controller;

use App\Service\TelegramService;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TelegramController extends AbstractController
{
    #[Route('/evenement/{id}/telegram-link', name: 'telegram_invite_link')]
    public function getInviteLink(
        int $id,
        EvenementRepository $repo,
        TelegramService $telegram,
        EntityManagerInterface $em
    ): JsonResponse {
        $evenement = $repo->find($id);

        if (!$evenement || !$evenement->getTelegramGroupId()) {
            return $this->json(['error' => 'Groupe Telegram non configuré'], 404);
        }

        // ✅ Appel API 1 — génération du lien d'invitation
        $link = $telegram->createInviteLink($evenement->getTelegramGroupId());

        // ✅ Sauvegarde du lien en base
        $evenement->setLienGroupe($link);
        $em->flush();

        // ✅ Récupération du prénom et nom depuis User.php
        $user = $this->getUser();
        $userName = $user
            ? ($user->getPrenom() . ' ' . $user->getNom())  // getPrenom() ✅ ligne 51 | getNom() ✅ ligne 38
            : 'Nouveau participant';

        // ✅ Appel API 2 — message de bienvenue dans le groupe Telegram
        $telegram->sendWelcome(
            $evenement->getTelegramGroupId(),
            $userName,
            $evenement->getTitre()
        );

        return $this->json(['invite_link' => $link]);
    }
}