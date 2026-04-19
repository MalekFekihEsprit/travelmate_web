<?php

namespace App\Controller;

use App\Service\SecurityAlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SecurityCaptureController extends AbstractController
{
    #[Route('/security/capture-failed-login-photo', name: 'app_security_capture_failed_login_photo', methods: ['POST'])]
    public function captureFailedLoginPhoto(
        Request $request,
        SecurityAlertService $securityAlertService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $image = $data['image'] ?? null;

        if (!$image || !is_string($image)) {
            return $this->json([
                'success' => false,
                'message' => 'Aucune image reçue.',
            ], 400);
        }

        $filename = $securityAlertService->saveBase64Photo($image, 'failed-login');

        if (!$filename) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de sauvegarder l’image.',
            ], 400);
        }

        $request->getSession()->set('security_failed_login_photo_filename', $filename);

        return $this->json([
            'success' => true,
            'filename' => $filename,
        ]);
    }
}