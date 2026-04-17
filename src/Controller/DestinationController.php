<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Entity\NoteDestination;
use App\Entity\User;
use App\Form\DestinationType;
use App\Repository\DestinationRepository;
use App\Repository\NoteDestinationRepository;
use App\Repository\UserRepository;
use App\Service\CityCountryLookupService;
use App\Service\DestinationImageFetcherService;
use App\Service\RestCountriesService;
use App\Service\YouTubeVideoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\UnsplashImageService;
use App\Service\ImageDownloaderService;



#[Route('/destination')]
final class DestinationController extends AbstractController
{
    #[Route('/location/validate', name: 'app_destination_location_validate', methods: ['GET'])]
    public function validateLocation(Request $request, CityCountryLookupService $lookupService): JsonResponse
    {
        $city    = trim((string) $request->query->get('city', ''));
        $country = trim((string) $request->query->get('country', ''));

        $validation = $lookupService->validate($city, $country);

        return $this->json([
            'city'                 => $city,
            'country'              => $country,
            'is_valid_country'     => $validation['is_valid_country'],
            'is_valid_city'        => $validation['is_valid_city'],
            'is_valid_pair'        => $validation['is_valid_pair'],
            'country_suggestions'  => $lookupService->suggestCountries($country, 5),
            'city_suggestions'     => $lookupService->suggestCities($city, $country !== '' ? $country : null, 5),
        ]);
    }

    #[Route('/', name: 'app_destination_index', methods: ['GET'])]
    public function index(
        DestinationRepository $destinationRepository,
        NoteDestinationRepository $noteDestinationRepository,
    ): Response {
        $destinations = $destinationRepository->findBy([], ['id_destination' => 'DESC']);

        $averageScores = [];
        $regions       = [];
        $climates      = [];
        $seasons       = [];

        foreach ($destinations as $destination) {
            $average = $noteDestinationRepository->getAverageForDestination($destination);
            $averageScores[$destination->getId_destination()] = $average > 0 ? $average : $destination->getScore_destination();

            if ($destination->getRegion_destination()) {
                $regions[] = $destination->getRegion_destination();
            }

            if ($destination->getClimat_destination()) {
                $climates[] = $destination->getClimat_destination();
            }

            if ($destination->getSaison_destination()) {
                $seasons[] = $destination->getSaison_destination();
            }
        }

        return $this->render('destination/index.html.twig', [
            'destinations'    => $destinations,
            'average_scores'  => $averageScores,
            'unique_regions'  => count(array_unique($regions)),
            'unique_climates' => count(array_unique($climates)),
            'unique_seasons'  => count(array_unique($seasons)),
        ]);
    }

#[Route('/test-vich', name: 'app_destination_test_vich', methods: ['GET'])]
public function testVich(
    EntityManagerInterface $entityManager,
    ImageDownloaderService $imageDownloader,
): Response {
    $destination = new \App\Entity\Destination();
    $destination->setNom_destination('VichTest');
    $destination->setPays_destination('France');
    $destination->setScore_destination(0.0);

    // Download a real image
    $file = $imageDownloader->download(
        'https://images.unsplash.com/photo-1499856871958-5b9627545d1a?w=800'
    );

    dump('File downloaded: ', $file?->getPathname(), $file?->getSize());

    $destination->setImageFile($file);

    dump('imageFile after set: ', $destination->getImageFile());
    dump('imageName after set: ', $destination->getImageName());
    dump('updatedAt after set: ', $destination->getUpdatedAt());

    $entityManager->persist($destination);

    dump('imageName after persist: ', $destination->getImageName());

    $entityManager->flush();

    dump('imageName after flush: ', $destination->getImageName());

    $uploadDir = '%kernel.project_dir%/public/uploads/destinations/';
    $files = glob('/Users/neyrouzchekir/PISymfony/Esprit-PIDEV-3A9-2526-TravelMate/public/uploads/destinations/*');

    return new Response(json_encode([
        'image_name'      => $destination->getImageName(),
        'updated_at'      => $destination->getUpdatedAt()?->format('Y-m-d H:i:s'),
        'files_in_dir'    => $files,
        'file_downloaded' => $file?->getPathname(),
    ]));
}
    #[Route('/new', name: 'app_destination_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        RestCountriesService $restCountriesService,
        CityCountryLookupService $lookupService,
        DestinationImageFetcherService $imageFetcher,
        YouTubeVideoService $youTubeVideoService,
    ): Response {
        $destination = new Destination();
        $form        = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyLocationValidation($form, $destination, $lookupService);

            if (!$form->isValid()) {
                return $this->render('destination/new.html.twig', [
                    'destination' => $destination,
                    'form'        => $form,
                ]);
            }

            // Fetch country data (currency, flag, languages)
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
                    if (isset($coordinates['latitude'])) {
                        $destination->setLatitude_destination($coordinates['latitude']);
                    }
                    if (isset($coordinates['longitude'])) {
                        $destination->setLongitude_destination($coordinates['longitude']);
                    }
                }
            }

            // Assign user
            $user = $this->getUser() ?? $userRepository->find(2);
            if (!$user) {
                $this->addFlash('error', 'Aucun utilisateur n\'a été trouvé');
                return $this->render('destination/new.html.twig', [
                    'destination' => $destination,
                    'form'        => $form,
                ]);
            }

            $destination->setUser($user);
            $destination->setScore_destination(0.0);

            $videoUrl = $youTubeVideoService->fetchVideoUrl(
                (string) $destination->getNom_destination(),
                (string) $destination->getPays_destination()
            );

            if ($videoUrl !== null) {
                $destination->setVideo_url($videoUrl);
            } else {
                $this->addFlash('warning', 'Vidéo YouTube non trouvée automatiquement pour cette destination.');
            }

            // ── Auto-fetch image from Unsplash and assign via VichUploader ──
                $imageFetcher->fetchAndAssign($destination);

            try {
                $entityManager->persist($destination);

                

                $entityManager->flush();

                $this->addFlash('success', 'La destination a été créée avec succès');
                return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
                return $this->render('destination/new.html.twig', [
                    'destination' => $destination,
                    'form'        => $form,
                ]);
            }
        }

        return $this->render('destination/new.html.twig', [
            'destination' => $destination,
            'form'        => $form,
        ]);
    }

    #[Route('/{id_destination}', name: 'app_destination_show', methods: ['GET'])]
    public function show(
        Destination $destination,
        NoteDestinationRepository $noteDestinationRepository,
    ): Response {
        $userNote = null;
        $user     = $this->getUser();

        if ($user instanceof User) {
            $note = $noteDestinationRepository->findOneByDestinationAndUser($destination, $user);
            if ($note instanceof NoteDestination) {
                $userNote = $note->getNote();
            }
        }

        return $this->render('destination/show.html.twig', [
            'destination'   => $destination,
            'average_score' => $noteDestinationRepository->getAverageForDestination($destination),
            'user_note'     => $userNote,
        ]);
    }

    #[Route('/{id_destination}/rate', name: 'app_destination_rate', methods: ['POST'])]
    public function rate(
        Request $request,
        Destination $destination,
        NoteDestinationRepository $noteDestinationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $isAjax = $request->isXmlHttpRequest();

        if (!$this->isCsrfTokenValid('rate_destination_' . $destination->getId_destination(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            if ($isAjax) {
                return $this->render('destination/show.html.twig', [
                    'destination'   => $destination,
                    'average_score' => $noteDestinationRepository->getAverageForDestination($destination),
                    'user_note'     => null,
                ]);
            }
            return $this->redirectToRoute('app_destination_show', ['id_destination' => $destination->getId_destination()]);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour noter une destination.');
            if ($isAjax) {
                return $this->render('destination/show.html.twig', [
                    'destination'   => $destination,
                    'average_score' => $noteDestinationRepository->getAverageForDestination($destination),
                    'user_note'     => null,
                ]);
            }
            return $this->redirectToRoute('app_login');
        }

        $noteValue = (float) $request->request->get('note', 0);
        if ($noteValue < 0 || $noteValue > 5) {
            $this->addFlash('error', 'La note doit être comprise entre 0 et 5.');
            if ($isAjax) {
                $note = $noteDestinationRepository->findOneByDestinationAndUser($destination, $user);
                return $this->render('destination/show.html.twig', [
                    'destination'   => $destination,
                    'average_score' => $noteDestinationRepository->getAverageForDestination($destination),
                    'user_note'     => $note instanceof NoteDestination ? $note->getNote() : null,
                ]);
            }
            return $this->redirectToRoute('app_destination_show', ['id_destination' => $destination->getId_destination()]);
        }

        $note = $noteDestinationRepository->findOneByDestinationAndUser($destination, $user);
        if (!$note instanceof NoteDestination) {
            $note = new NoteDestination();
            $note->setDestination($destination);
            $note->setUser($user);
            $entityManager->persist($note);
        }

        $note->setNote($noteValue);
        $entityManager->flush();

        $destination->setScore_destination($noteDestinationRepository->getAverageForDestination($destination));
        $entityManager->flush();

        $this->addFlash('success', 'Votre note a été enregistrée.');

        if ($isAjax) {
            return $this->render('destination/show.html.twig', [
                'destination'   => $destination,
                'average_score' => $noteDestinationRepository->getAverageForDestination($destination),
                'user_note'     => $note->getNote(),
            ]);
        }

        return $this->redirectToRoute('app_destination_show', ['id_destination' => $destination->getId_destination()]);
    }

    #[Route('/{id_destination}/edit', name: 'app_destination_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Destination $destination,
        EntityManagerInterface $entityManager,
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

            // Refresh coordinates on edit as well
            $city    = trim((string) ($destination->getNom_destination() ?: $destination->getRegion_destination()));
            $country = $destination->getPays_destination();
            if ($city && $country) {
                $coordinates = $lookupService->getCoordinates($city, $country);
                if ($coordinates) {
                    if (isset($coordinates['latitude'])) {
                        $destination->setLatitude_destination($coordinates['latitude']);
                    }
                    if (isset($coordinates['longitude'])) {
                        $destination->setLongitude_destination($coordinates['longitude']);
                    }
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('destination/edit.html.twig', [
            'destination' => $destination,
            'form'        => $form,
        ]);
    }

    #[Route('/{id_destination}', name: 'app_destination_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Destination $destination,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $destination->getId_destination(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($destination);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);
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
