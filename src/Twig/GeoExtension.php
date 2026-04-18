<?php

namespace App\Twig;

use App\Service\GeoIpService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GeoExtension extends AbstractExtension
{
    public function __construct(
        private GeoIpService $geoIpService,
        private RequestStack $requestStack
    ) {}
    
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_geo_phone_context', [$this, 'getGeoPhoneContext']),
        ];
    }
    
    public function getGeoPhoneContext(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $ip = $this->geoIpService->getClientIp($request);
        $data = $this->geoIpService->lookupIp($ip);
        
        if (!$data['success']) {
            return [
                'success' => true,
                'country_name' => 'Tunisie',
                'country_code' => 'TN',
                'calling_code' => '+216',
                'flag_emoji' => '🇹🇳',
                'flag_url' => 'https://flagpedia.net/data/flags/icon/72x54/tn.png',
            ];
        }
        
        $data['flag_url'] = sprintf(
            'https://flagpedia.net/data/flags/icon/72x54/%s.png',
            strtolower($data['country_code'])
        );
        
        return $data;
    }
}