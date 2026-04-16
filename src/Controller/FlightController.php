<?php

namespace App\Controller;

use App\Repository\VoyageRepository;
use App\Service\FlightScraperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FlightController extends AbstractController
{
    #[Route('/flights', name: 'app_flights', methods: ['GET'])]
    public function index(
        Request $request,
        FlightScraperService $flightScraper,
        VoyageRepository $voyageRepository,
    ): Response {
        $voyageId    = $request->query->get('voyageId');
        $destination = trim((string) $request->query->get('destination', ''));
        $date        = trim((string) $request->query->get('date', ''));

        $voyageSelectionne = null;

        // Lire destination / date depuis le voyage sélectionné
        if ($voyageId) {
            $voyageSelectionne = $voyageRepository->find($voyageId);
            if ($voyageSelectionne) {
                if ($destination === '') {
                    $destination = $voyageSelectionne->getDestination()?->getNom_destination() ?? '';
                }
                if ($date === '') {
                    $date = $voyageSelectionne->getDate_debut()?->format('Y-m-d') ?? '';
                }
            }
        }

        $results  = null;
        $searched = false;
        $originCode = 'TUN'; // Départ par défaut : Tunis
        $destCode   = null;

        if ($destination !== '' && $date !== '') {
            $searched = true;
            $destCode = $flightScraper->resolveIataCode($destination);

            if ($destCode) {
                $results = $flightScraper->searchFlights($originCode, $destCode, $date);
            } else {
                $results = [
                    'status'  => 'error',
                    'message' => sprintf(
                        'Code IATA introuvable pour « %s ». Essayez avec le code IATA directement (ex : CDG, IST, MRS).',
                        htmlspecialchars($destination, \ENT_QUOTES)
                    ),
                    'flights' => [],
                ];
            }
        }

        return $this->render('flight/index.html.twig', [
            'destination'       => $destination,
            'destCode'          => $destCode,
            'date'              => $date,
            'results'           => $results,
            'searched'          => $searched,
            'voyageId'          => $voyageId,
            'voyageSelectionne' => $voyageSelectionne,
        ]);
    }
}
