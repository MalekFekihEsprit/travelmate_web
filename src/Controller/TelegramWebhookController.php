<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TelegramWebhookController extends AbstractController
{
    #[Route('/telegram/webhook', name: 'telegram_webhook', methods: ['POST'])]
    public function handle(
        Request $request,
        TelegramService $telegram,
        EvenementRepository $evenementRepo,
        EntityManagerInterface $em
    ): Response {
        $update = json_decode($request->getContent(), true);

        // Listen for a new member joining the group
        if (isset($update['chat_member'])) {
            $chatId = $update['chat_member']['chat']['id'];
            $newStatus = $update['chat_member']['new_chat_member']['status'];
            $oldStatus = $update['chat_member']['old_chat_member']['status'] ?? null;

            // User just joined (status changed to 'member')
            if ($newStatus === 'member' && $oldStatus !== 'member') {
                $user = $update['chat_member']['new_chat_member']['user'];
                $firstName = $user['first_name'] ?? '';
                $lastName = $user['last_name'] ?? '';
                $userName = trim($firstName . ' ' . $lastName);

                // Find which event corresponds to this Telegram group
                $event = $evenementRepo->findOneBy(['telegramGroupId' => (string) $chatId]);
                if ($event) {
                    $telegram->sendWelcome($chatId, $userName, $event->getTitre());
                }
            }
        }

        return new Response('OK');
    }
}