<?php

namespace App\Controller;

use App\Repository\ItineraireRepository;
use App\Service\FalVideoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class VideoController extends AbstractController
{
    #[Route('/itineraires/{id}/generate-video', name: 'app_itineraire_generate_video', methods: ['POST'])]
    public function generateVideo(
        int $id,
        ItineraireRepository $itineraireRepository,
        FalVideoService $falVideoService,
    ): JsonResponse {
        $itineraire = $itineraireRepository->find($id);

        if (!$itineraire) {
            return $this->json(['status' => 'error', 'message' => 'Itinéraire introuvable.'], 404);
        }

        $result = $falVideoService->generateItineraryVideo($itineraire);

        $httpStatus = match ($result['status']) {
            'ok' => 200,
            'unconfigured' => 503,
            default => 500,
        };

        return $this->json($result, $httpStatus);
    }

    #[Route('/itineraires/video-status/{requestId}', name: 'app_itineraire_video_status', methods: ['GET'])]
    public function videoStatus(
        string $requestId,
        FalVideoService $falVideoService,
    ): JsonResponse {
        $result = $falVideoService->checkStatus($requestId);

        return $this->json($result);
    }
}
