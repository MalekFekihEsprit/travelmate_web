<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Entity\NoteDestination;
use App\Entity\User;
use App\Form\DestinationType;
use App\Repository\DestinationRepository;
use App\Repository\NoteDestinationRepository;
use App\Repository\UserRepository;
use App\Service\RestCountriesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/destination')]
final class DestinationController extends AbstractController
{
    #[Route(name: 'app_destination_index', methods: ['GET'])]
    public function index(DestinationRepository $destinationRepository, NoteDestinationRepository $noteDestinationRepository): Response
    {
        $destinations = $destinationRepository->findAll();
        $averageScores = [];
        
        // Count unique climates, seasons, and regions
        $climates = [];
        $seasons = [];
        $regions = [];
        foreach ($destinations as $destination) {
            $averageScores[$destination->getIdDestination()] = $noteDestinationRepository->getAverageForDestination($destination);

            if ($destination->getClimat_destination()) {
                $climates[] = $destination->getClimat_destination();
            }
            if ($destination->getSaison_destination()) {
                $seasons[] = $destination->getSaison_destination();
            }
            if ($destination->getRegion_destination()) {
                $regions[] = $destination->getRegion_destination();
            }
        }
        
        $uniqueClimates = count(array_unique($climates));
        $uniqueSeasons = count(array_unique($seasons));
        $uniqueRegions = count(array_unique($regions));
        
        return $this->render('destination/index.html.twig', [
            'destinations' => $destinations,
            'average_scores' => $averageScores,
            'unique_climates' => $uniqueClimates,
            'unique_seasons' => $uniqueSeasons,
            'unique_regions' => $uniqueRegions,
        ]);
    }

    #[Route('/new', name: 'app_destination_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, RestCountriesService $restCountriesService): Response
    {
        $destination = new Destination();
        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Check if form is valid
            if (!$form->isValid()) {
                // Form has validation errors, they will be displayed in the template
                return $this->render('destination/new.html.twig', [
                    'destination' => $destination,
                    'form' => $form,
                ]);
            }

            // Fetch country data from RESTcountries API
            $countryData = $restCountriesService->getCountryData($destination->getPays_destination());
            if ($countryData) {
                $destination->setCurrency_destination($countryData['currency']);
                $destination->setLanguages_destination($countryData['languages']);
                $destination->setFlag_destination($countryData['flag']);
            }

            // Get logged-in user or use user with id 2 as default
            $user = $this->getUser();
            if (!$user) {
                $user = $userRepository->find(2);
            }
            
            if (!$user) {
                $this->addFlash('error', 'Aucun utilisateur n\'a été trouvé');
                return $this->render('destination/new.html.twig', [
                    'destination' => $destination,
                    'form' => $form,
                ]);
            }

            $destination->setUser($user);
            $destination->setScore_destination(0.0);

            try {
                $entityManager->persist($destination);
                $entityManager->flush();
                
                $this->addFlash('success', 'La destination a été créée avec succès');
                return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
                return $this->render('destination/new.html.twig', [
                    'destination' => $destination,
                    'form' => $form,
                ]);
            }
        }

        return $this->render('destination/new.html.twig', [
            'destination' => $destination,
            'form' => $form,
        ]);
    }

    #[Route('/{id_destination}', name: 'app_destination_show', methods: ['GET'])]
    public function show(Destination $destination, NoteDestinationRepository $noteDestinationRepository): Response
    {
        return $this->render('destination/show.html.twig', [
            'destination' => $destination,
            'average_score' => $noteDestinationRepository->getAverageForDestination($destination),
        ]);
    }

    #[Route('/{id_destination}/rate', name: 'app_destination_rate', methods: ['POST'])]
    public function rate(
        Request $request,
        Destination $destination,
        NoteDestinationRepository $noteDestinationRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('rate_destination_'.$destination->getId_destination(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_destination_show', ['id_destination' => $destination->getId_destination()]);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour noter une destination.');
            return $this->redirectToRoute('app_login');
        }

        $noteValue = (float) $request->request->get('note', 0);
        if ($noteValue < 0 || $noteValue > 5) {
            $this->addFlash('error', 'La note doit être comprise entre 0 et 5.');
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
        return $this->redirectToRoute('app_destination_show', ['id_destination' => $destination->getId_destination()]);
    }

    #[Route('/{id_destination}/edit', name: 'app_destination_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Destination $destination, EntityManagerInterface $entityManager, RestCountriesService $restCountriesService): Response
    {
        $originalCountry = $destination->getPays_destination();
        
        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // If country changed, fetch new country data
            if ($originalCountry !== $destination->getPays_destination()) {
                $countryData = $restCountriesService->getCountryData($destination->getPays_destination());
                if ($countryData) {
                    $destination->setCurrency_destination($countryData['currency']);
                    $destination->setLanguages_destination($countryData['languages']);
                    $destination->setFlag_destination($countryData['flag']);
                }
            }
            
            $entityManager->flush();

            return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('destination/edit.html.twig', [
            'destination' => $destination,
            'form' => $form,
        ]);
    }

    #[Route('/{id_destination}', name: 'app_destination_delete', methods: ['POST'])]
    public function delete(Request $request, Destination $destination, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$destination->getId_destination(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($destination);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);
    }
}
