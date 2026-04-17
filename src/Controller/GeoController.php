<?php

namespace App\Controller;

use App\Service\GeoIpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GeoController extends AbstractController
{
    #[Route('/geo/phone-context', name: 'app_geo_phone_context', methods: ['GET'])]
    public function phoneContext(Request $request, GeoIpService $geoIpService): JsonResponse
    {
        $ip = $geoIpService->getClientIp($request);
        $data = $geoIpService->lookupIp($ip);

        if (!$data['success']) {
            return $this->json([
                'success' => true,
                'country_name' => 'Tunisie',
                'country_code' => 'TN',
                'calling_code' => '+216',
                'flag_emoji' => '🇹🇳',
                'flag_url' => 'https://flagpedia.net/data/flags/icon/72x54/tn.png', // Real flag URL
                'fallback' => true,
            ]);
        }
        
        // Add real flag URL
        $data['flag_url'] = sprintf(
            'https://flagpedia.net/data/flags/icon/72x54/%s.png',
            strtolower($data['country_code'])
        );

        return $this->json($data);
    }
}
