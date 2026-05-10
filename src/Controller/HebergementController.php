<?php

namespace App\Controller;

use App\Entity\Hebergement;
use App\Repository\DestinationRepository;
use App\Repository\HebergementRepository;
use App\Service\HebergementScraperService;
use App\Service\UnsplashImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/hebergement')]
class HebergementController extends AbstractController
{
    #[Route('/', name: 'app_hebergement_index', methods: ['GET'])]
    public function index(Request $request, HebergementRepository $hebergementRepository, DestinationRepository $destinationRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $typeFilter = trim((string) $request->query->get('type', ''));
        $sort = trim((string) $request->query->get('sort', 'default'));
        $destinationId = $request->query->getInt('destination', 0);

        $selectedDestination = $destinationId > 0 ? $destinationRepository->find($destinationId) : null;
        $allDestinations = $destinationRepository->findBy([], ['nom_destination' => 'ASC']);
        $allHebergements = $hebergementRepository->findBy([], ['idHebergement' => 'DESC']);
        $destinationOptionsMap = [];

        foreach ($allDestinations as $destination) {
            if ($destination->getIdDestination()) {
                $destinationOptionsMap[$destination->getIdDestination()] = [
                    'id' => $destination->getIdDestination(),
                    'name' => $destination->getNomDestination() ?? 'Destination',
                    'country' => $destination->getPaysDestination() ?? '',
                ];
            }
        }

        $destinationOptions = array_values($destinationOptionsMap);
        usort($destinationOptions, static fn (array $a, array $b): int => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));

        $hebergements = $allHebergements;

        $hebergements = array_values(array_filter($hebergements, static function ($hebergement) use ($search, $typeFilter): bool {
            if ($typeFilter !== '' && mb_strtolower((string) $hebergement->getTypeHebergement()) !== mb_strtolower($typeFilter)) {
                return false;
            }

            if ($search !== '') {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $hebergement->getNomHebergement() ?? '',
                    $hebergement->getTypeHebergement() ?? '',
                    $hebergement->getAdresseHebergement() ?? '',
                    $hebergement->getDestination()?->getNomDestination() ?? '',
                    $hebergement->getDestination()?->getPaysDestination() ?? '',
                ])));

                if (!str_contains($haystack, mb_strtolower($search))) {
                    return false;
                }
            }

            return true;
        }));

        usort($hebergements, static function ($left, $right) use ($sort): int {
            return match ($sort) {
                'name' => strcmp(mb_strtolower($left->getNomHebergement() ?? ''), mb_strtolower($right->getNomHebergement() ?? '')),
                'price-asc' => (float) ($left->getPrixNuitHebergement() ?? 0) <=> (float) ($right->getPrixNuitHebergement() ?? 0),
                'price-desc' => (float) ($right->getPrixNuitHebergement() ?? 0) <=> (float) ($left->getPrixNuitHebergement() ?? 0),
                'rating-desc' => (float) ($right->getNoteHebergement() ?? 0) <=> (float) ($left->getNoteHebergement() ?? 0),
                'rating-asc' => (float) ($left->getNoteHebergement() ?? 0) <=> (float) ($right->getNoteHebergement() ?? 0),
                default => ($right->getIdHebergement() ?? 0) <=> ($left->getIdHebergement() ?? 0),
            };
        });

        // Count unique types
        $types = [];
        $destinations = [];
        
        foreach ($hebergements as $hebergement) {
            if ($hebergement->getTypeHebergement()) {
                $types[$hebergement->getTypeHebergement()] = true;
            }
            if ($hebergement->getDestination() && $hebergement->getDestination()->getNomDestination()) {
                $destinations[$hebergement->getDestination()->getNomDestination()] = true;
            }
        }

        return $this->render('hebergement/index.html.twig', [
            'hebergements' => $hebergements,
            'unique_types' => count($types),
            'unique_destinations' => count($destinations),
            'search' => $search,
            'selected_type' => $typeFilter,
            'selected_sort' => $sort,
            'selected_destination' => $selectedDestination,
            'selected_destination_id' => $destinationId,
            'destination_options' => $destinationOptions,
        ]);
    }

    
    

// ──────────────────────────────────────────────────────────────────────────
    // NEW: Scraping actions
    // ──────────────────────────────────────────────────────────────────────────
 
    /**
     * GET /hebergement/scrape?destination=Paris
     *
     * Calls the scraper service and returns results as JSON.
     * The frontend renders the results as selectable cards.
     */
    #[Route('/scrape', name: 'app_hebergement_scrape', methods: ['GET'])]
    public function scrapeHebergements(
        Request $request,
        HebergementScraperService $scraperService,
        DestinationRepository $destinationRepository
    ): JsonResponse {
        $destinationId = $request->query->getInt('destination_id', 0);
        $destination = trim((string) $request->query->get('destination', 'Paris'));
        $maxResults = max(1, min(40, (int) $request->query->get('max', 20)));
 
        if ($destinationId > 0) {
            $selectedDestination = $destinationRepository->find($destinationId);
            if ($selectedDestination !== null && $selectedDestination->getNomDestination()) {
                $destination = $selectedDestination->getNomDestination();
            }
        }

        if ($destination === '') {
            return $this->json(['error' => 'Veuillez fournir une destination.'], Response::HTTP_BAD_REQUEST);
        }
 
        try {
            $results = $scraperService->scrape($destination, $maxResults);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur lors du scraping : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
 
        return $this->json([
            'success' => true,
            'count'   => count($results),
            'data'    => $results,
        ]);
    }
 
    /**
     * POST /hebergement/save-scraped
     *
     * Receives a JSON body: { "items": [ {...}, ... ] }
        * Creates Hebergement entities and stores the remote image URL directly.
     */
    #[Route('/save-scraped', name: 'app_hebergement_save_scraped', methods: ['POST'])]
    public function saveSelectedHebergements(
        Request $request,
        EntityManagerInterface $em,
        DestinationRepository $destinationRepository,
        UnsplashImageService $unsplashImageService
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
 
        if (!isset($payload['items']) || !is_array($payload['items'])) {
            return $this->json(['error' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $destinationId = isset($payload['destination_id']) ? (int) $payload['destination_id'] : 0;
        if ($destinationId <= 0) {
            return $this->json(['error' => 'Destination invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $destination = $destinationRepository->find($destinationId);
        if ($destination === null) {
            return $this->json(['error' => 'Destination introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        $destinationName = trim((string) $destination->getNomDestination());
        $destinationCountry = trim((string) ($destination->getPaysDestination() ?? ''));
 
        $saved  = 0;
        $errors = [];
 
        foreach ($payload['items'] as $index => $data) {
            try {
                $hebergement = new Hebergement();
                $hebergement->setNomHebergement((string) ($data['name'] ?? 'Sans nom'));
                $hebergement->setTypeHebergement($data['type'] ?? null);
                $hebergement->setPrixNuitHebergement(isset($data['price']) ? (float) $data['price'] : null);
                $hebergement->setAdresseHebergement($data['address'] ?? null);
                $hebergement->setNoteHebergement(isset($data['rating']) ? (float) $data['rating'] : null);
                $hebergement->setLatitudeHebergement(isset($data['latitude']) ? (float) $data['latitude'] : null);
                $hebergement->setLongitudeHebergement(isset($data['longitude']) ? (float) $data['longitude'] : null);
                $hebergement->setDestination($destination);
 
                $imageUrl = $this->resolveHebergementImageUrl(
                    $unsplashImageService,
                    (string) ($data['name'] ?? ''),
                    $destinationName,
                    $destinationCountry,
                    isset($data['image_url']) && is_string($data['image_url']) ? $data['image_url'] : null,
                );

                if ($imageUrl !== null && $imageUrl !== '' && mb_strlen($imageUrl) <= 2048) {
                    $hebergement->setImageName($imageUrl);
                    $hebergement->setUpdatedAt(new \DateTimeImmutable());
                }
 
                $em->persist($hebergement);
                ++$saved;
            } catch (\Throwable $e) {
                $errors[] = sprintf('Élément %d : %s', $index, $e->getMessage());
            }
        }
 
        $em->flush();
 
        return $this->json([
            'success' => true,
            'saved'   => $saved,
            'errors'  => $errors,
        ]);
    }

    private function canonicalizeRemoteImageUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        $trimmed = explode('#', $trimmed, 2)[0];
        $trimmed = explode('?', $trimmed, 2)[0];

        return trim($trimmed);
    }

    private function resolveHebergementImageUrl(
        UnsplashImageService $unsplashImageService,
        string $hebergementName,
        string $destinationName,
        string $destinationCountry,
        ?string $fallbackUrl = null
    ): ?string {
        $queries = array_values(array_filter(array_unique([
            $this->buildUnsplashQuery($hebergementName, $destinationName, $destinationCountry),
            $this->buildUnsplashQuery($hebergementName, $destinationName, ''),
            $this->buildUnsplashQuery($hebergementName, '', $destinationCountry),
            $this->buildUnsplashQuery('', $destinationName, $destinationCountry),
            $this->buildUnsplashQuery('', $destinationName, ''),
            $this->buildUnsplashQuery($hebergementName, '', ''),
        ])));

        foreach ($queries as $query) {
            $imageUrl = $unsplashImageService->findPhotoUrl($query);
            if ($imageUrl !== null && $imageUrl !== '') {
                return $this->canonicalizeRemoteImageUrl($imageUrl);
            }
        }

        if ($fallbackUrl !== null) {
            $fallbackUrl = $this->canonicalizeRemoteImageUrl($fallbackUrl);
            if ($fallbackUrl !== '') {
                return $fallbackUrl;
            }
        }

        return null;
    }

    private function buildUnsplashQuery(string $hebergementName, string $destinationName, string $destinationCountry): string
    {
        $parts = array_values(array_filter([
            trim($hebergementName),
            trim($destinationName),
            trim($destinationCountry),
        ]));

        return trim(preg_replace('/\s+/', ' ', implode(' - ', $parts)) ?? '');
    }

    #[Route('/{id_hebergement}', name: 'app_hebergement_show', methods: ['GET'], requirements: ['id_hebergement' => '\d+'])]
    public function show(Request $request, int $id_hebergement, HebergementRepository $hebergementRepository): Response
    {
        $hebergement = $hebergementRepository->find($id_hebergement);

        if (!$hebergement) {
            throw $this->createNotFoundException('Hébergement not found');
        }

        $payload = ['hebergement' => $hebergement];
        if ($request->isXmlHttpRequest()) {
            return $this->render('hebergement/_show_fragment.html.twig', $payload);
        }

        return $this->render('hebergement/show.html.twig', $payload);
    }

    #[Route('/scrape-debug', name: 'app_hebergement_scrape_debug', methods: ['GET'])]
public function scrapeDebug(
    Request $request,
    \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient,
): Response {
    $cookies     = trim((string) $request->query->get('cookies', ''));
    $destination = trim((string) $request->query->get('destination', 'Paris'));

    $checkin  = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $checkout = (new \DateTimeImmutable('+2 days'))->format('Y-m-d');

    $url = sprintf(
        'https://www.booking.com/searchresults.html?ss=%s&checkin=%s&checkout=%s&group_adults=2&no_rooms=1&lang=fr',
        urlencode($destination),
        $checkin,
        $checkout,
    );

    $headers = [
        'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'fr-FR,fr;q=0.9',
        'Cache-Control'   => 'no-cache',
    ];

    if ($cookies !== '') {
        $headers['Cookie'] = $cookies;
    }

    $response = $httpClient->request('GET', $url, [
        'timeout' => 30,
        'headers' => $headers,
    ]);

    $html       = $response->getContent(false);
    $statusCode = $response->getStatusCode();
    $length     = strlen($html);

    // Count how many property cards are found
    $crawler    = new \Symfony\Component\DomCrawler\Crawler($html);
    $cards1     = $crawler->filter('[data-testid="property-card"]')->count();
    $cards2     = $crawler->filter('.sr_property_block')->count();
    $hasCaptcha = str_contains($html, 'captcha') || str_contains($html, 'robot');

    return new \Symfony\Component\HttpFoundation\JsonResponse([
        'url'          => $url,
        'status_code'  => $statusCode,
        'html_length'  => $length,
        'has_captcha'  => $hasCaptcha,
        'cards_found_strategy1' => $cards1,
        'cards_found_strategy2' => $cards2,
        'html_preview' => substr($html, 0, 2000),
    ]);
}
}
 