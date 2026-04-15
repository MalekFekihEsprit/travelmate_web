<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UnsplashController extends AbstractController
{
    private HttpClientInterface $client;
    private ?string $accessKey;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->accessKey = $_ENV['UNSPLASH_ACCESS_KEY'] ?? getenv('UNSPLASH_ACCESS_KEY') ?: null;
    }

    #[Route('/_unsplash/info', name: 'app_unsplash_info')]
    public function info(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        $countryParam = trim((string) $request->query->get('country', ''));

        if ($q === '') {
            return new JsonResponse(['error' => 'missing_query'], 400);
        }

        if (!$this->accessKey) {
            return new JsonResponse(['error' => 'no_api_key'], 500);
        }

        try {
            $response = $this->client->request('GET', 'https://api.unsplash.com/search/photos', [
                'query' => ['query' => $q, 'per_page' => 1],
                'headers' => [
                    'Authorization' => 'Client-ID ' . $this->accessKey,
                    'Accept-Version' => 'v1',
                ],
                'timeout' => 6,
            ]);

            if (200 !== $response->getStatusCode()) {
                return new JsonResponse(['error' => 'unsplash_error', 'status' => $response->getStatusCode()], 502);
            }

            $data = $response->toArray();

            if (empty($data['results'])) {
                return new JsonResponse(['found' => false]);
            }

            $photo = $data['results'][0];

            $out = [
                'found' => true,
                'id' => $photo['id'] ?? null,
                'description' => $photo['description'] ?? $photo['alt_description'] ?? null,
                'image_small' => $photo['urls']['small'] ?? null,
                'image_regular' => $photo['urls']['regular'] ?? null,
                'photographer' => $photo['user']['name'] ?? null,
                'photographer_link' => $photo['user']['links']['html'] ?? null,
                'location' => $photo['location']['name'] ?? null,
                'unsplash_link' => $photo['links']['html'] ?? null,
            ];

            // Try to determine country name: prefer explicit country param, otherwise try to parse location or query
            $countryName = null;
            if ($countryParam !== '') {
                $countryName = $countryParam;
            } elseif (!empty($photo['location']['country'])) {
                $countryName = $photo['location']['country'];
            } else {
                if (!empty($photo['location']['name']) && strpos($photo['location']['name'], ',') !== false) {
                    $parts = explode(',', $photo['location']['name']);
                    $countryName = trim(end($parts));
                } elseif (strpos($q, ',') !== false) {
                    $parts = explode(',', $q);
                    $countryName = trim(end($parts));
                }
            }

            if ($countryName) {
                try {
                    $rc = $this->client->request('GET', 'https://restcountries.com/v3.1/name/' . urlencode($countryName), [
                        'query' => ['fullText' => 'true'],
                        'timeout' => 6,
                    ]);

                    if ($rc->getStatusCode() === 200) {
                        $rcData = $rc->toArray();
                        if (!empty($rcData[0])) {
                            $countryInfo = $rcData[0];
                            // Languages
                            $languages = [];
                            if (!empty($countryInfo['languages']) && is_array($countryInfo['languages'])) {
                                $languages = array_values($countryInfo['languages']);
                            }

                            // Currencies (name + symbol)
                            $currencies = [];
                            if (!empty($countryInfo['currencies']) && is_array($countryInfo['currencies'])) {
                                foreach ($countryInfo['currencies'] as $code => $cinfo) {
                                    $label = $cinfo['name'] ?? $code;
                                    if (!empty($cinfo['symbol'])) {
                                        $label .= ' (' . $cinfo['symbol'] . ')';
                                    }
                                    $currencies[] = $label;
                                }
                            }

                            $out['country'] = [
                                'name_official' => $countryInfo['name']['official'] ?? ($countryInfo['name']['common'] ?? $countryName),
                                'capital' => !empty($countryInfo['capital'][0]) ? $countryInfo['capital'][0] : null,
                                'population' => $countryInfo['population'] ?? null,
                                'region' => $countryInfo['region'] ?? null,
                                'subregion' => $countryInfo['subregion'] ?? null,
                                'continents' => $countryInfo['continents'] ?? null,
                                'flags' => $countryInfo['flags']['png'] ?? $countryInfo['flags']['svg'] ?? null,
                                'languages' => $languages,
                                'currencies' => $currencies,
                                'timezones' => $countryInfo['timezones'] ?? null,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore country lookup failures; still return Unsplash data
                }
            }

            return new JsonResponse($out);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'exception', 'message' => $e->getMessage()], 502);
        }
    }
}
