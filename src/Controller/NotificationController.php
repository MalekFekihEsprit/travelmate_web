<?php

namespace App\Controller;

use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    private const SESSION_KEY = 'trip_dismissed_ids';

    /**
     * Returns upcoming voyages (starting in 2 days) that the user hasn't dismissed yet.
     * No DB table required — uses the existing voyage table + PHP session.
     */
    #[Route('/api/notifications/upcoming', name: 'api_notifications_upcoming', methods: ['GET'])]
    public function upcoming(
        Request $request,
        VoyageRepository $voyageRepository,
    ): JsonResponse {
        $session    = $request->getSession();
        $dismissed  = $session->get(self::SESSION_KEY, []);

        $voyages = $voyageRepository->findVoyagesStartingInDays(2);

        $notifications = [];
        foreach ($voyages as $voyage) {
            $id = $voyage->getId_voyage();
            if (in_array($id, $dismissed, true)) {
                continue;
            }
            $dateDebut   = $voyage->getDate_debut();
            $destination = $voyage->getDestination();

            // Calculate real days remaining
            $daysLeft = 0;
            if ($dateDebut) {
                $today     = new \DateTime('today');
                $tripDay   = (clone $dateDebut)->setTime(0, 0, 0);
                $diff      = (int) $today->diff($tripDay)->days;
                $daysLeft  = max(0, $diff);
            }

            if ($daysLeft === 0) {
                $when = "c'est aujourd'hui !";
            } elseif ($daysLeft === 1) {
                $when = "c'est demain !";
            } else {
                $when = "dans {$daysLeft} jours !";
            }

            $notifications[] = [
                'voyage_id'   => $id,
                'titre'       => $voyage->getTitre_voyage(),
                'date_debut'  => $dateDebut?->format('d/m/Y'),
                'days_left'   => $daysLeft,
                'destination' => $destination?->getNom_destination(),
                'message'     => sprintf(
                    '🧳 Votre voyage "%s" commence le %s — %s',
                    $voyage->getTitre_voyage(),
                    $dateDebut?->format('d/m/Y') ?? '?',
                    $when
                ),
            ];
        }

        return $this->json([
            'count'         => count($notifications),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Dismiss a notification for this session (no DB write).
     */
    #[Route('/api/notifications/{voyageId}/dismiss', name: 'api_notification_dismiss', methods: ['POST'])]
    public function dismiss(
        int $voyageId,
        Request $request,
    ): JsonResponse {
        $session   = $request->getSession();
        $dismissed = $session->get(self::SESSION_KEY, []);

        if (!in_array($voyageId, $dismissed, true)) {
            $dismissed[] = $voyageId;
            $session->set(self::SESSION_KEY, $dismissed);
        }

        return $this->json(['status' => 'ok']);
    }

    /**
     * Dismiss all current notifications.
     */
    #[Route('/api/notifications/dismiss-all', name: 'api_notification_dismiss_all', methods: ['POST'])]
    public function dismissAll(
        Request $request,
        VoyageRepository $voyageRepository,
    ): JsonResponse {
        $session = $request->getSession();
        $voyages = $voyageRepository->findVoyagesStartingInDays(2);

        $ids = array_map(fn($v) => $v->getId_voyage(), $voyages);
        $existing = $session->get(self::SESSION_KEY, []);
        $session->set(self::SESSION_KEY, array_unique(array_merge($existing, $ids)));

        return $this->json(['status' => 'ok', 'dismissed' => count($ids)]);
    }
}
