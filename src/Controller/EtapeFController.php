<?php

namespace App\Controller;

use App\Entity\Etape;
use App\Entity\Itineraire;
use App\Repository\EtapeRepository;
use App\Repository\ItineraireRepository;
use App\Service\OpenRouteServiceGeocoder;
use App\Service\WeatherService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etapes', name: 'app_etapes_')]
class EtapeFController extends AbstractController
{
    private static function getTotalDays(Itineraire $itineraire): int
    {
        $voyage = $itineraire->getVoyage();
        if ($voyage && $voyage->getDate_debut() && $voyage->getDate_fin()) {
            return max(1, $voyage->getDate_fin()->diff($voyage->getDate_debut())->days + 1);
        }

        $maxDay = 1;
        foreach ($itineraire->getEtapes() as $etape) {
            $maxDay = max($maxDay, (int) $etape->getNumero_jour());
        }

        return $maxDay;
    }

    private static function normalizeSort(string $sort): string
    {
        return in_array($sort, ['heure_asc', 'heure_desc', 'alpha_asc', 'alpha_desc'], true)
            ? $sort
            : 'heure_asc';
    }

    private static function compareEtapes(Etape $a, Etape $b, string $sort): int
    {
        $compareByTime = static function (Etape $left, Etape $right, bool $descending): int {
            $leftTime = $left->getHeure()?->format('H:i') ?? ($descending ? '' : '99:99');
            $rightTime = $right->getHeure()?->format('H:i') ?? ($descending ? '' : '99:99');
            $comparison = strcmp($leftTime, $rightTime);

            return $descending ? -$comparison : $comparison;
        };

        $compareAlphabetically = static function (Etape $left, Etape $right, bool $descending): int {
            $comparison = strcasecmp(
                mb_strtolower((string) $left->getDescription_etape()),
                mb_strtolower((string) $right->getDescription_etape())
            );

            return $descending ? -$comparison : $comparison;
        };

        return match ($sort) {
            'heure_desc' => $compareByTime($a, $b, true),
            'alpha_asc' => $compareAlphabetically($a, $b, false),
            'alpha_desc' => $compareAlphabetically($a, $b, true),
            default => $compareByTime($a, $b, false),
        };
    }

    private static function getWeekdayShortFr(\DateTimeInterface $date): string
    {
        $days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

        return $days[(int) $date->format('w')];
    }

    private static function getWeekdayLongFr(\DateTimeInterface $date): string
    {
        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

        return $days[(int) $date->format('w')];
    }

    private function fetchWeatherEmojis(Itineraire $itineraire, array $timelineDays, ?WeatherService $weatherService): array
    {
        if (!$weatherService) {
            return [];
        }

        $voyage = $itineraire->getVoyage();
        $destination = $voyage?->getDestination();
        $lat = $destination?->getLatitude_destination();
        $lon = $destination?->getLongitude_destination();

        if (!$lat || !$lon) {
            return [];
        }

        $baseDate = $voyage->getDate_debut()
            ? \DateTimeImmutable::createFromInterface($voyage->getDate_debut())->setTime(0, 0)
            : null;

        if (!$baseDate) {
            return [];
        }

        $dates = [];
        foreach ($timelineDays as $day) {
            $dates[] = $baseDate->modify(sprintf('+%d days', $day['number'] - 1));
        }

        $emojis = $weatherService->getWeatherEmojisForDates($lat, $lon, $dates);

        // Re-key by day number
        $result = [];
        foreach ($timelineDays as $day) {
            $date = $baseDate->modify(sprintf('+%d days', $day['number'] - 1));
            $dateKey = $date->format('Y-m-d');
            if (isset($emojis[$dateKey])) {
                $result[$day['number']] = $emojis[$dateKey];
            }
        }

        return $result;
    }

    private static function getMonthShortFr(\DateTimeInterface $date): string
    {
        $months = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'];

        return $months[(int) $date->format('n') - 1];
    }

    private static function getMonthLongFr(\DateTimeInterface $date): string
    {
        $months = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];

        return $months[(int) $date->format('n') - 1];
    }

    private static function getSortLabel(string $sort): string
    {
        return match ($sort) {
            'heure_desc' => 'Heure decroissante',
            'alpha_asc' => 'Description A a Z',
            'alpha_desc' => 'Description Z a A',
            default => 'Heure croissante',
        };
    }

    private static function normalizeSelectedDays(array $rawDays, int $totalDays, int $fallbackDay): array
    {
        $normalized = [];
        foreach ($rawDays as $rawDay) {
            if (!is_scalar($rawDay)) {
                continue;
            }

            $day = (int) $rawDay;
            if ($day >= 1 && $day <= $totalDays) {
                $normalized[$day] = $day;
            }
        }

        if ($normalized === []) {
            $normalized[$fallbackDay] = $fallbackDay;
        }

        ksort($normalized);

        return array_values($normalized);
    }

    private function buildMapPayload(
        Itineraire $itineraire,
        array $timelineDays,
        array $selectedDays,
        OpenRouteServiceGeocoder $geocoder
    ): array {
        if (!$geocoder->isConfigured()) {
            return [
                'status' => 'unconfigured',
                'message' => 'Ajoute la cle OpenRouteService pour activer la carte des activites.',
                'markers' => [],
                'cities' => [],
                'selectedDays' => $selectedDays,
            ];
        }

        $destination = $itineraire->getVoyage()?->getDestination();
        $selectedDayLookup = array_flip($selectedDays);
        $markers = [];
        $cities = [];

        foreach ($timelineDays as $timelineDay) {
            if (!isset($selectedDayLookup[$timelineDay['number']])) {
                continue;
            }

            foreach ($timelineDay['etapes'] as $etape) {
                $activite = $etape->getActivite();
                $place = trim((string) $activite?->getLieu());
                if ($place === '') {
                    continue;
                }

                $geocoded = $geocoder->geocodePlace($place, $destination);
                if ($geocoded === null) {
                    continue;
                }

                $city = trim((string) ($geocoded['locality'] ?? ''));
                if ($city !== '') {
                    $cities[$city] = $city;
                }

                $markers[] = [
                    'day' => $timelineDay['number'],
                    'dayLabel' => $timelineDay['full_label'],
                    'time' => $etape->getHeure()?->format('H:i') ?? '--:--',
                    'activity' => (string) ($activite?->getNom() ?: 'Activite'),
                    'description' => (string) ($etape->getDescription_etape() ?: ''),
                    'address' => (string) ($geocoded['label'] ?? $place),
                    'city' => $city,
                    'lat' => (float) $geocoded['lat'],
                    'lng' => (float) $geocoded['lng'],
                ];
            }
        }

        if ($markers === []) {
            return [
                'status' => 'empty',
                'message' => 'Aucune activite avec adresse exploitable n\'a ete trouvee pour les jours selectionnes.',
                'markers' => [],
                'cities' => [],
                'selectedDays' => $selectedDays,
            ];
        }

        sort($cities);

        return [
            'status' => 'ready',
            'message' => count($markers) . ' adresse(s) affichee(s) sur la carte.',
            'markers' => $markers,
            'cities' => array_values($cities),
            'selectedDays' => $selectedDays,
        ];
    }

    private static function buildPdfFileName(Itineraire $itineraire, int $jour): string
    {
        $rawName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $itineraire->getNom_itineraire()) ?: 'itineraire';
        $safeName = preg_replace('/[^A-Za-z0-9]+/', '-', $rawName) ?? 'itineraire';
        $safeName = strtolower(trim($safeName, '-'));

        if ($safeName === '') {
            $safeName = 'itineraire';
        }

        return sprintf('%s-jour-%d.pdf', $safeName, $jour);
    }

    private function buildJourViewData(
        Itineraire $itineraire,
        int $jour,
        Request $request,
        EtapeRepository $etapeRepository,
        ?WeatherService $weatherService = null
    ): array {
        $totalDays = self::getTotalDays($itineraire);
        if ($jour < 1 || $jour > $totalDays) {
            throw $this->createNotFoundException('Jour invalide');
        }

        $searchValue = trim((string) $request->query->get('q', ''));
        $search = mb_strtolower($searchValue);
        $sort = self::normalizeSort((string) $request->query->get('sort', 'heure_asc'));

        $allEtapes = $etapeRepository->findBy([
            'itineraire' => $itineraire,
        ]);

        if ($search !== '') {
            $allEtapes = array_values(array_filter($allEtapes, static function (Etape $e) use ($search): bool {
                $desc = mb_strtolower((string) $e->getDescription_etape());
                $act = $e->getActivite() ? mb_strtolower((string) $e->getActivite()->getNom()) : '';

                return str_contains($desc, $search) || ($act !== '' && str_contains($act, $search));
            }));
        }

        usort($allEtapes, static function (Etape $a, Etape $b) use ($sort): int {
            return self::compareEtapes($a, $b, $sort);
        });

        $etapesByDay = [];
        foreach ($allEtapes as $etape) {
            $dayNumber = (int) $etape->getNumero_jour();
            $etapesByDay[$dayNumber][] = $etape;
        }

        $plannedDays = 0;
        $matchingDays = 0;
        $timelineDays = [];
        $voyage = $itineraire->getVoyage();
        $baseDate = $voyage && $voyage->getDate_debut()
            ? \DateTimeImmutable::createFromInterface($voyage->getDate_debut())->setTime(0, 0)
            : null;

        for ($dayNumber = 1; $dayNumber <= $totalDays; ++$dayNumber) {
            $dayEtapes = $etapesByDay[$dayNumber] ?? [];
            if ($dayEtapes !== []) {
                ++$plannedDays;
                ++$matchingDays;
            }

            $date = $baseDate?->modify(sprintf('+%d days', $dayNumber - 1));

            $timelineDays[] = [
                'number' => $dayNumber,
                'calendar_day' => $date ? $date->format('j') : (string) $dayNumber,
                'month_label' => $date ? self::getMonthShortFr($date) : 'Jour',
                'weekday_short' => $date ? self::getWeekdayShortFr($date) : sprintf('J%d', $dayNumber),
                'full_label' => $date
                    ? sprintf(
                        '%s %s %s',
                        ucfirst(self::getWeekdayLongFr($date)),
                        $date->format('j'),
                        self::getMonthLongFr($date)
                    )
                    : sprintf('Jour %d', $dayNumber),
                'etapes' => $dayEtapes,
                'count' => count($dayEtapes),
                'has_content' => $dayEtapes !== [],
            ];
        }

        $activeTimelineDay = null;
        foreach ($timelineDays as $timelineDay) {
            if ($timelineDay['number'] === $jour) {
                $activeTimelineDay = $timelineDay;
                break;
            }
        }

        $visibleTimelineDays = array_values(array_filter(
            $timelineDays,
            static function (array $timelineDay) use ($jour): bool {
                return $timelineDay['count'] > 0 || $timelineDay['number'] === $jour;
            }
        ));

        return [
            'itineraire' => $itineraire,
            'jour' => $jour,
            'etapes' => $etapesByDay[$jour] ?? [],
            'timelineDays' => $timelineDays,
            'visibleTimelineDays' => $visibleTimelineDays,
            'activeTimelineDay' => $activeTimelineDay,
            'plannedDays' => $plannedDays,
            'matchingDays' => $matchingDays,
            'totalDays' => $totalDays,
            'totalEtapes' => count($allEtapes),
            'search_q' => $searchValue,
            'sort' => $sort,
            'sort_label' => self::getSortLabel($sort),
            'weatherEmojis' => $this->fetchWeatherEmojis($itineraire, $timelineDays, $weatherService),
        ];
    }

    #[Route('/jour/{itineraireId}/{jour}', name: 'jour', methods: ['GET'])]
    public function afficherJour(
        int $itineraireId,
        int $jour,
        Request $request,
        ItineraireRepository $itineraireRepository,
        EtapeRepository $etapeRepository,
        WeatherService $weatherService
    ): Response {
        $itineraire = $itineraireRepository->find($itineraireId);
        
        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        return $this->render('home/EtapeF.html.twig', $this->buildJourViewData(
            $itineraire,
            $jour,
            $request,
            $etapeRepository,
            $weatherService
        ));
    }

    #[Route('/{itineraireId}/map-data', name: 'map_data', methods: ['GET'])]
    public function mapData(
        int $itineraireId,
        Request $request,
        ItineraireRepository $itineraireRepository,
        EtapeRepository $etapeRepository,
        OpenRouteServiceGeocoder $openRouteServiceGeocoder
    ): JsonResponse {
        $itineraire = $itineraireRepository->find($itineraireId);
        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        $totalDays = self::getTotalDays($itineraire);
        $anchorDay = max(1, min((int) $request->query->get('jour', 1), $totalDays));
        $viewData = $this->buildJourViewData($itineraire, $anchorDay, $request, $etapeRepository);
        $selectedDays = self::normalizeSelectedDays((array) $request->query->all('days'), $totalDays, $anchorDay);

        return $this->json($this->buildMapPayload(
            $itineraire,
            $viewData['timelineDays'],
            $selectedDays,
            $openRouteServiceGeocoder
        ));
    }

    #[Route('/jour/{itineraireId}/{jour}/export-pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(
        int $itineraireId,
        int $jour,
        Request $request,
        ItineraireRepository $itineraireRepository,
        EtapeRepository $etapeRepository
    ): Response {
        $itineraire = $itineraireRepository->find($itineraireId);

        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        $viewData = $this->buildJourViewData($itineraire, $jour, $request, $etapeRepository);

        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->renderView('home/EtapeF_export_pdf.html.twig', array_merge(
            $viewData,
            ['generatedAt' => new \DateTimeImmutable()]
        )), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                self::buildPdfFileName($itineraire, $jour)
            ),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        ItineraireRepository $itineraireRepository,
        EtapeRepository $etapeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $itineraireId = $request->query->get('itineraireId');
        $jour = $request->query->get('jour');

        $itineraire = $itineraireRepository->find($itineraireId);
        
        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validation de la description
            if (empty($data['description_etape'])) {
                $errors[] = 'La description est obligatoire.';
            } elseif (strlen($data['description_etape']) < 10) {
                $errors[] = 'La description doit contenir au minimum 10 caractères.';
            }

            // Validation de l'heure
            if (empty($data['heure'])) {
                $errors[] = 'L\'heure est obligatoire.';
            }

            // Vérifier que l'heure est unique pour ce jour
            if (!empty($data['heure'])) {
                $heure = new \DateTime($data['heure']);
                $etapesDuJour = $etapeRepository->findBy([
                    'itineraire' => $itineraire,
                    'numero_jour' => (int)$jour
                ]);
                
                foreach ($etapesDuJour as $autreEtape) {
                    if ($autreEtape->getHeure() && $autreEtape->getHeure()->format('H:i') === $heure->format('H:i')) {
                        $errors[] = 'Une autre étape a déjà cette heure pour ce jour.';
                        break;
                    }
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                // Récupérer les activités du voyage
                $activites = [];
                if ($itineraire->getVoyage()) {
                    $allActivites = $itineraire->getVoyage()->getActivites();
                    $etapesDuJour = $etapeRepository->findBy([
                        'itineraire' => $itineraire,
                        'numero_jour' => (int)$jour
                    ]);
                    $activitesUtilisees = [];
                    foreach ($etapesDuJour as $etape) {
                        if ($etape->getActivite()) {
                            $activitesUtilisees[] = $etape->getActivite()->getId();
                        }
                    }
                    foreach ($allActivites as $activite) {
                        if (!in_array($activite->getId(), $activitesUtilisees)) {
                            $activites[] = $activite;
                        }
                    }
                }
                return $this->render('home/etape_form.html.twig', [
                    'etape' => null,
                    'itineraire' => $itineraire,
                    'jour' => $jour,
                    'activites' => $activites,
                    'title' => 'Créer une étape'
                ]);
            }

            $etape = new Etape();
            $etape->setItineraire($itineraire);
            $etape->setNumero_jour((int)($data['numero_jour'] ?? $jour));
            $etape->setDescription_etape($data['description_etape']);
            $etape->setHeure(new \DateTime($data['heure']));

            if (!empty($data['id_activite'])) {
                $voyage = $itineraire->getVoyage();
                if ($voyage) {
                    foreach ($voyage->getActivites() as $activite) {
                        if ($activite->getId() == $data['id_activite']) {
                            $etape->setActivite($activite);
                            break;
                        }
                    }
                }
            }

            $entityManager->persist($etape);
            $entityManager->flush();

            $this->addFlash('success', 'Étape créée avec succès!');

            return $this->redirectToRoute('app_etapes_jour', [
                'itineraireId' => $itineraire->getId_itineraire(),
                'jour' => $etape->getNumero_jour()
            ]);
        }

        // Récupérer les activités du voyage
        $activites = [];
        if ($itineraire->getVoyage()) {
            $allActivites = $itineraire->getVoyage()->getActivites();
            
            // Récupérer les étapes du jour pour exclure les activités déjà utilisées
            $etapesDuJour = $etapeRepository->findBy([
                'itineraire' => $itineraire,
                'numero_jour' => (int)$jour
            ]);
            
            $activitesUtilisees = [];
            foreach ($etapesDuJour as $etape) {
                if ($etape->getActivite()) {
                    $activitesUtilisees[] = $etape->getActivite()->getId();
                }
            }
            
            // Filtrer les activités pour exclure celles déjà utilisées
            foreach ($allActivites as $activite) {
                if (!in_array($activite->getId(), $activitesUtilisees)) {
                    $activites[] = $activite;
                }
            }
        }

        return $this->render('home/etape_form.html.twig', [
            'etape' => null,
            'itineraire' => $itineraire,
            'jour' => $jour,
            'activites' => $activites,
            'title' => 'Créer une étape'
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EtapeRepository $etapeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $etape = $etapeRepository->find($id);

        if (!$etape) {
            throw $this->createNotFoundException('Étape non trouvée');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validation de la description
            if (empty($data['description_etape'])) {
                $errors[] = 'La description est obligatoire.';
            } elseif (strlen($data['description_etape']) < 10) {
                $errors[] = 'La description doit contenir au minimum 10 caractères.';
            }

            // Validation de l'heure
            if (empty($data['heure'])) {
                $errors[] = 'L\'heure est obligatoire.';
            }

            // Vérifier que l'heure est unique pour ce jour (en excluant l'étape actuelle)
            if (!empty($data['heure'])) {
                $heure = new \DateTime($data['heure']);
                $etapesDuJour = $etapeRepository->findBy([
                    'itineraire' => $etape->getItineraire(),
                    'numero_jour' => (int)($data['numero_jour'] ?? $etape->getNumero_jour())
                ]);
                
                foreach ($etapesDuJour as $autreEtape) {
                    if ($autreEtape->getId_etape() !== $id && $autreEtape->getHeure() && $autreEtape->getHeure()->format('H:i') === $heure->format('H:i')) {
                        $errors[] = 'Une autre étape a déjà cette heure pour ce jour.';
                        break;
                    }
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                // Récupérer les activités du voyage
                $activites = [];
                if ($etape->getItineraire()->getVoyage()) {
                    $allActivites = $etape->getItineraire()->getVoyage()->getActivites();
                    $etapesDuJour = $etapeRepository->findBy([
                        'itineraire' => $etape->getItineraire(),
                        'numero_jour' => $etape->getNumero_jour()
                    ]);
                    $activitesUtilisees = [];
                    foreach ($etapesDuJour as $autreEtape) {
                        if ($autreEtape->getId_etape() !== $id && $autreEtape->getActivite()) {
                            $activitesUtilisees[] = $autreEtape->getActivite()->getId();
                        }
                    }
                    foreach ($allActivites as $activite) {
                        if (!in_array($activite->getId(), $activitesUtilisees)) {
                            $activites[] = $activite;
                        }
                    }
                }
                return $this->render('home/etape_form.html.twig', [
                    'etape' => $etape,
                    'itineraire' => $etape->getItineraire(),
                    'jour' => $etape->getNumero_jour(),
                    'activites' => $activites,
                    'title' => 'Modifier l\'étape'
                ]);
            }

            $etape->setDescription_etape($data['description_etape']);
            $etape->setNumero_jour((int)($data['numero_jour'] ?? $etape->getNumero_jour()));
            $etape->setHeure(new \DateTime($data['heure']));

            if (!empty($data['id_activite'])) {
                $voyage = $etape->getItineraire()->getVoyage();
                if ($voyage) {
                    foreach ($voyage->getActivites() as $activite) {
                        if ($activite->getId() == $data['id_activite']) {
                            $etape->setActivite($activite);
                            break;
                        }
                    }
                }
            } else {
                $etape->setActivite(null);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Étape modifiée avec succès!');

            return $this->redirectToRoute('app_etapes_jour', [
                'itineraireId' => $etape->getItineraire()->getId_itineraire(),
                'jour' => $etape->getNumero_jour()
            ]);
        }

        // Récupérer les activités du voyage
        $activites = [];
        if ($etape->getItineraire()->getVoyage()) {
            $allActivites = $etape->getItineraire()->getVoyage()->getActivites();
            
            // Récupérer les étapes du jour pour exclure les activités déjà utilisées
            $etapesDuJour = $etapeRepository->findBy([
                'itineraire' => $etape->getItineraire(),
                'numero_jour' => $etape->getNumero_jour()
            ]);
            
            $activitesUtilisees = [];
            foreach ($etapesDuJour as $autreEtape) {
                // Exclure l'activité de l'étape en cours d'édition
                if ($autreEtape->getId_etape() !== $id && $autreEtape->getActivite()) {
                    $activitesUtilisees[] = $autreEtape->getActivite()->getId();
                }
            }
            
            // Filtrer les activités pour exclure celles déjà utilisées
            foreach ($allActivites as $activite) {
                if (!in_array($activite->getId(), $activitesUtilisees)) {
                    $activites[] = $activite;
                }
            }
        }

        return $this->render('home/etape_form.html.twig', [
            'etape' => $etape,
            'itineraire' => $etape->getItineraire(),
            'jour' => $etape->getNumero_jour(),
            'activites' => $activites,
            'title' => 'Modifier l\'étape'
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        int $id,
        EtapeRepository $etapeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $etape = $etapeRepository->find($id);

        if (!$etape) {
            throw $this->createNotFoundException('Étape non trouvée');
        }

        $itineraireId = $etape->getItineraire()->getId_itineraire();
        $jour = $etape->getNumero_jour();

        $entityManager->remove($etape);
        $entityManager->flush();

        $this->addFlash('success', 'Étape supprimée avec succès!');

        return $this->redirectToRoute('app_etapes_jour', [
            'itineraireId' => $itineraireId,
            'jour' => $jour
        ]);
    }
}