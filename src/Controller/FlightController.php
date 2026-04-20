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

        if ($destination !== '') {
            $searched = true;
            $results = $flightScraper->searchFlights($destination, $date);
        }

        return $this->render('flight/index.html.twig', [
            'destination'       => $destination,
            'date'              => $date,
            'results'           => $results,
            'searched'          => $searched,
            'voyageId'          => $voyageId,
            'voyageSelectionne' => $voyageSelectionne,
        ]);
    }
}
