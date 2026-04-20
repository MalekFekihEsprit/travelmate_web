<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DestinationVoyageNotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    #[Route('/api/notifications/upcoming', name: 'api_notifications_upcoming', methods: ['GET'])]
    public function upcoming(
        DestinationVoyageNotificationRepository $notificationRepository,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'count' => 0,
                'notifications' => [],
            ]);
        }

        $notifications = $notificationRepository->findActiveByUser($user);

        $items = [];
        foreach ($notifications as $notification) {
            $voyage = $notification->getVoyage();
            if ($voyage === null) {
                continue;
            }

            $destination = $voyage->getDestination();
            $destinationName = $destination?->getNom_destination() ?? 'Destination';
            $dateDebut = $voyage->getDate_debut();

            $items[] = [
                'voyage_id'   => $voyage->getId_voyage(),
                'titre'       => $voyage->getTitre_voyage(),
                'date_debut'  => $dateDebut?->format('d/m/Y'),
                'destination' => $destinationName,
                'message'     => sprintf(
                    '🧭 Nouveau voyage vers %s : "%s"%s',
                    $destinationName,
                    $voyage->getTitre_voyage(),
                    $dateDebut ? ' (début le ' . $dateDebut->format('d/m/Y') . ')' : ''
                ),
            ];
        }

        return $this->json([
            'count'         => count($items),
            'notifications' => $items,
        ]);
    }

    #[Route('/api/notifications/{voyageId}/dismiss', name: 'api_notification_dismiss', methods: ['POST'])]
    public function dismiss(
        int $voyageId,
        DestinationVoyageNotificationRepository $notificationRepository,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['status' => 'ok']);
        }

        $notificationRepository->dismissByUserAndVoyageId($user, $voyageId);

        return $this->json(['status' => 'ok']);
    }

    #[Route('/api/notifications/dismiss-all', name: 'api_notification_dismiss_all', methods: ['POST'])]
    public function dismissAll(
        DestinationVoyageNotificationRepository $notificationRepository,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['status' => 'ok', 'dismissed' => 0]);
        }

        $dismissedCount = $notificationRepository->dismissAllByUser($user);

        return $this->json(['status' => 'ok', 'dismissed' => $dismissedCount]);
    }
}
