<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Form\DestinationType;
use App\Repository\DestinationRepository;
use App\Repository\UserRepository;
use App\Service\CityCountryLookupService;
use App\Service\DestinationImageFetcherService;
use App\Service\RestCountriesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/destinations')]
class DestinationAdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_destinations', methods: ['GET'])]
    public function index(Request $request, DestinationRepository $repo): Response
    {
        $search        = trim((string) $request->query->get('q', ''));
        $countryFilter = trim((string) $request->query->get('country', ''));
        $regionFilter  = trim((string) $request->query->get('region', ''));
        $climateFilter = trim((string) $request->query->get('climate', ''));
        $sort          = (string) $request->query->get('sort', 'recent');

        $allDestinations = $repo->findBy([], ['id_destination' => 'DESC']);

        $destinations = array_values(array_filter($allDestinations, static function (Destination $destination) use ($search, $countryFilter, $regionFilter, $climateFilter): bool {
            if ($search !== '') {
                $haystack = mb_strtolower(sprintf('%s %s %s %s',
                    $destination->getNomDestination() ?? '',
                    $destination->getPaysDestination() ?? '',
                    $destination->getRegionDestination() ?? '',
                    $destination->getClimatDestination() ?? ''
                ));
                if (mb_strpos($haystack, mb_strtolower($search)) === false) {
                    return false;
                }
            }
            if ($countryFilter !== '' && mb_strtolower((string) $destination->getPaysDestination()) !== mb_strtolower($countryFilter)) {
                return false;
            }
            if ($regionFilter !== '' && mb_strtolower((string) $destination->getRegionDestination()) !== mb_strtolower($regionFilter)) {
                return false;
            }
            if ($climateFilter !== '' && mb_strtolower((string) $destination->getClimatDestination()) !== mb_strtolower($climateFilter)) {
                return false;
            }
            return true;
        }));

        usort($destinations, static function (Destination $left, Destination $right) use ($sort): int {
            return match ($sort) {
                'name_asc'    => strcmp(mb_strtolower($left->getNomDestination() ?? ''), mb_strtolower($right->getNomDestination() ?? '')),
                'name_desc'   => strcmp(mb_strtolower($right->getNomDestination() ?? ''), mb_strtolower($left->getNomDestination() ?? '')),
                'country_asc' => strcmp(mb_strtolower($left->getPaysDestination() ?? ''), mb_strtolower($right->getPaysDestination() ?? '')),
                'country_desc'=> strcmp(mb_strtolower($right->getPaysDestination() ?? ''), mb_strtolower($left->getPaysDestination() ?? '')),
                'score_asc'   => (float) ($left->getScoreDestination() ?? 0) <=> (float) ($right->getScoreDestination() ?? 0),
                'score_desc'  => (float) ($right->getScoreDestination() ?? 0) <=> (float) ($left->getScoreDestination() ?? 0),
                default       => ($right->getIdDestination() ?? 0) <=> ($left->getIdDestination() ?? 0),
            };
        });

        $countries   = [];
        $regions     = [];
        $climates    = [];
        $scoreValues = [];

        foreach ($allDestinations as $destination) {
            if ($destination->getPaysDestination())    $countries[]   = $destination->getPaysDestination();
            if ($destination->getRegionDestination())  $regions[]     = $destination->getRegionDestination();
            if ($destination->getClimatDestination())  $climates[]    = $destination->getClimatDestination();
            if ($destination->getScoreDestination() !== null) $scoreValues[] = (float) $destination->getScoreDestination();
        }

        $countries = array_values(array_unique($countries)); sort($countries);
        $regions   = array_values(array_unique($regions));   sort($regions);
        $climates  = array_values(array_unique($climates));  sort($climates);

        $averageScore = $scoreValues !== [] ? round(array_sum($scoreValues) / count($scoreValues), 1) : null;

        return $this->render('destination_admin/index.html.twig', [
            'destinations'  => $destinations,
            'search'        => $search,
            'countryFilter' => $countryFilter,
            'regionFilter'  => $regionFilter,
            'climateFilter' => $climateFilter,
            'sort'          => $sort,
            'countries'     => $countries,
            'regions'       => $regions,
            'climates'      => $climates,
            'stats'         => [
                'total'        => count($allDestinations),
                'countries'    => count($countries),
                'regions'      => count($regions),
                'climates'     => count($climates),
                'averageScore' => $averageScore,
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_admin_destinations_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Request $request, Destination $destination): Response
    {
        if ($request->query->getBoolean('inline')) {
            return $this->render('destination_admin/_show_content.html.twig', [
                'destination' => $destination,
            ]);
        }

        return $this->render('destination_admin/show.html.twig', [
            'destination' => $destination,
        ]);
    }

    #[Route('/new', name: 'app_admin_destinations_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        RestCountriesService $restCountriesService,
        CityCountryLookupService $lookupService,
        DestinationImageFetcherService $imageFetcher,
    ): Response {
        $destination = new Destination();
        $destination->setScore_destination(0.0);
        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyLocationValidation($form, $destination, $lookupService);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Fetch country data
            $countryData = $restCountriesService->getCountryData($destination->getPays_destination());
            if ($countryData) {
                $destination->setCurrency_destination($countryData['currency']);
                $destination->setLanguages_destination($countryData['languages']);
                $destination->setFlag_destination($countryData['flag']);
                if (!$destination->getRegion_destination() && !empty($countryData['region'])) {
                    $destination->setRegion_destination($countryData['region']);
                }
            }

            // Fetch coordinates (city is stored in nom_destination)
            $city    = trim((string) ($destination->getNom_destination() ?: $destination->getRegion_destination()));
            $country = $destination->getPays_destination();
            if ($city && $country) {
                $coordinates = $lookupService->getCoordinates($city, $country);
                if ($coordinates) {
                    if (isset($coordinates['latitude']))  $destination->setLatitude_destination($coordinates['latitude']);
                    if (isset($coordinates['longitude'])) $destination->setLongitude_destination($coordinates['longitude']);
                }
            }

            $user = $this->getUser() ?? $userRepository->find(2);
            if ($user) {
                $destination->setUser($user);
            }

            // ── Auto-fetch image from Unsplash and assign via VichUploader ──
            $imageFetcher->fetchAndAssign($destination);
            $destination->setScore_destination(0.0);

            $em->persist($destination);


            $em->flush();

            $this->addFlash('success', 'Destination ajoutée avec succès ✅');

            return $this->redirectToRoute('app_admin_destinations');
        }

        return $this->render('destination_admin/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_destinations_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(
        Request $request,
        Destination $destination,
        EntityManagerInterface $em,
        RestCountriesService $restCountriesService,
        CityCountryLookupService $lookupService,
    ): Response {
        $originalCountry = $destination->getPays_destination();

        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyLocationValidation($form, $destination, $lookupService);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($originalCountry !== $destination->getPays_destination() || !$destination->getRegion_destination()) {
                $countryData = $restCountriesService->getCountryData($destination->getPays_destination());
                if ($countryData) {
                    $destination->setCurrency_destination($countryData['currency']);
                    $destination->setLanguages_destination($countryData['languages']);
                    $destination->setFlag_destination($countryData['flag']);
                    if (!$destination->getRegion_destination() && !empty($countryData['region'])) {
                        $destination->setRegion_destination($countryData['region']);
                    }
                }
            }

            // Fetch coordinates (city is stored in nom_destination)
            $city    = trim((string) ($destination->getNom_destination() ?: $destination->getRegion_destination()));
            $country = $destination->getPays_destination();
            if ($city && $country) {
                $coordinates = $lookupService->getCoordinates($city, $country);
                if ($coordinates) {
                    if (isset($coordinates['latitude']))  $destination->setLatitude_destination($coordinates['latitude']);
                    if (isset($coordinates['longitude'])) $destination->setLongitude_destination($coordinates['longitude']);
                }
            }

            $em->flush();

            $this->addFlash('success', 'Destination modifiée avec succès ✏️');

            return $this->redirectToRoute('app_admin_destinations');
        }

        return $this->render('destination_admin/edit.html.twig', [
            'form'        => $form->createView(),
            'destination' => $destination,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_destinations_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Destination $destination, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_destination_' . $destination->getIdDestination(), $request->request->get('_token'))) {
            $em->remove($destination);
            $em->flush();
            $this->addFlash('success', 'Destination supprimée 🗑️');
        }

        return $this->redirectToRoute('app_admin_destinations');
    }

    #[Route('/bulk-delete', name: 'app_admin_destinations_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, DestinationRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete_destinations', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_destinations');
        }

        $ids = array_filter(array_map('intval', (array) $request->request->all('ids')));

        if (empty($ids)) {
            $this->addFlash('error', 'Veuillez sélectionner au moins une destination.');
            return $this->redirectToRoute('app_admin_destinations');
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            $destination = $repo->find($id);
            if ($destination) {
                $em->remove($destination);
                ++$deletedCount;
            }
        }

        if ($deletedCount > 0) {
            $em->flush();
            $this->addFlash('success', sprintf('%d destination(s) supprimée(s) avec succès 🗑️', $deletedCount));
        } else {
            $this->addFlash('error', 'Aucune destination valide à supprimer.');
        }

        return $this->redirectToRoute('app_admin_destinations');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function applyLocationValidation($form, Destination $destination, CityCountryLookupService $lookupService): void
    {
        $city    = trim((string) $destination->getNom_destination());
        $country = trim((string) $destination->getPays_destination());

        if ($country !== '' && !$lookupService->isValidCountry($country)) {
            $form->get('pays_destination')->addError(new FormError('Ce pays n\'existe pas dans la base locale.'));
            return;
        }

        if ($city !== '' && $country !== '' && !$lookupService->isValidCityForCountry($city, $country)) {
            $form->get('nom_destination')->addError(new FormError('Cette ville ne correspond pas au pays sélectionné dans la base locale.'));
        }
    }
}
