<?php

namespace App\Controller;

use App\Repository\ItineraireRepository;
use App\Service\ReplicateVideoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class VideoController extends AbstractController
{
    #[Route('/itineraires/{id}/generate-video', name: 'app_itineraire_generate_video', methods: ['POST'])]
    public function generateVideo(
        int $id,
        ItineraireRepository $itineraireRepository,
        ReplicateVideoService $replicateVideoService,
    ): JsonResponse {
        // Allow up to 3 minutes for concurrent image downloads
        set_time_limit(180);

        $itineraire = $itineraireRepository->find($id);

        if (!$itineraire) {
            return $this->json(['status' => 'error', 'message' => 'Itinéraire introuvable.'], 404);
        }

        $result = $replicateVideoService->generateItineraryVideo($itineraire);

        // Always return 200 so the JS can parse the JSON body and show the message
        return $this->json($result);
    }
}
