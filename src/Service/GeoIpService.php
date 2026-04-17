<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;

class GeoIpService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl
    ) {
    }

    public function getClientIp(Request $request): ?string
    {
        $ip = $request->getClientIp();

        if (!$ip) {
            return null;
        }

        if (
            $ip === '127.0.0.1' ||
            $ip === '::1' ||
            str_starts_with($ip, '192.168.') ||
            str_starts_with($ip, '10.') ||
            preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip)
        ) {
            return null;
        }

        return $ip;
    }

    public function lookupIp(?string $ip = null): array
    {
        try {
            $url = rtrim($this->baseUrl, '/').'/';
            if ($ip) {
                $url .= $ip.'/json/';
            } else {
                $url .= 'json/';
            }

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);

            $countryCode = strtoupper((string) ($data['country_code'] ?? ''));
            $countryName = (string) ($data['country_name'] ?? '');
            $callingCode = (string) ($data['country_calling_code'] ?? '');

            return [
                'success' => true,
                'ip' => (string) ($data['ip'] ?? $ip ?? ''),
                'country_name' => $countryName,
                'country_code' => $countryCode,
                'calling_code' => $callingCode,
                'flag_emoji' => $this->countryCodeToFlagEmoji($countryCode),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'ip' => $ip ?? '',
                'country_name' => '',
                'country_code' => '',
                'calling_code' => '',
                'flag_emoji' => '',
            ];
        }
    }

    private function countryCodeToFlagEmoji(string $countryCode): string
    {
        if (strlen($countryCode) !== 2) {
            return '';
        }

        $countryCode = strtoupper($countryCode);
        $first = mb_chr(127397 + ord($countryCode[0]), 'UTF-8');
        $second = mb_chr(127397 + ord($countryCode[1]), 'UTF-8');

        return $first.$second;
    }
}