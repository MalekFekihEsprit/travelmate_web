<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/mes-reservations')]
class MyReservationsController extends AbstractController
{
    private ReservationRepository $reservationRepository;

    public function __construct(ReservationRepository $reservationRepository)
    {
        $this->reservationRepository = $reservationRepository;
    }

    #[Route('/', name: 'app_my_reservations', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Pour l'instant, nous allons afficher toutes les réservations
        // En production, il faudra filtrer par utilisateur connecté
        $reservations = $this->reservationRepository->findBy(
            [],
            ['dateReservation' => 'DESC']
        );

        return $this->render('my_reservations/index.html.twig', [
            'reservations' => $reservations
        ]);
    }

    #[Route('/{id}', name: 'app_my_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('my_reservations/show.html.twig', [
            'reservation' => $reservation
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_my_reservation_cancel', methods: ['POST'])]
    public function cancel(Request $request, Reservation $reservation): Response
    {
        if ($this->isCsrfTokenValid('cancel' . $reservation->getId(), $request->request->get('_token'))) {
            // Vérifier si la réservation peut être annulée (moins de 24h avant)
            $now = new \DateTime();
            $reservationDate = $reservation->getDateReservation();
            $interval = $now->diff($reservationDate);
            
            if ($interval->days >= 1 || $interval->h >= 24) {
                $reservation->setStatutPaiement('annulee');
                $this->reservationRepository->save($reservation, true);
                
                $this->addFlash('success', 'Votre réservation a été annulée avec succès.');
            } else {
                $this->addFlash('error', 'Impossible d\'annuler la réservation moins de 24h avant la date.');
            }
        }

        return $this->redirectToRoute('app_my_reservations');
    }
}
