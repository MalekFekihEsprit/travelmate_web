<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CurrencyService
{
    private const BASE_URL = 'https://api.frankfurter.dev/v2';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache
    ) {}

    public function getRate(string $from, string $to): float
    {
        $cacheKey = 'currency_' . strtoupper($from) . '_' . strtoupper($to);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($from, $to) {
            $item->expiresAfter(3600);

            $response = $this->httpClient->request(
                'GET',
                self::BASE_URL . '/rate/' . strtoupper($from) . '/' . strtoupper($to)
            );

            $data = $response->toArray();

            return (float) $data['rate'];
        });
    }

    public function convert(string $from, string $to, float $amount): array
    {
        $rate = $this->getRate($from, $to);

        return [
            'from'      => strtoupper($from),
            'to'        => strtoupper($to),
            'amount'    => $amount,
            'rate'      => $rate,
            'converted' => round($amount * $rate, 2),
        ];
    }

    public function getAvailableCurrencies(): array
    {
        return $this->cache->get('currencies_list', function (ItemInterface $item) {
            $item->expiresAfter(86400);

            $response = $this->httpClient->request('GET', self::BASE_URL . '/currencies');

            return $response->toArray();
        });
    }
}