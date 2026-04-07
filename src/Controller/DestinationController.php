<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Form\DestinationType;
use App\Repository\DestinationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/destination')]
final class DestinationController extends AbstractController
{
    #[Route(name: 'app_destination_index', methods: ['GET'])]
    public function index(DestinationRepository $destinationRepository): Response
    {
        $destinations = $destinationRepository->findAll();
        
        // Count unique climates, seasons, and regions
        $climates = [];
        $seasons = [];
        $regions = [];
        foreach ($destinations as $destination) {
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
            'unique_climates' => $uniqueClimates,
            'unique_seasons' => $uniqueSeasons,
            'unique_regions' => $uniqueRegions,
        ]);
    }

    #[Route('/new', name: 'app_destination_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
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
    public function show(Destination $destination): Response
    {
        return $this->render('destination/show.html.twig', [
            'destination' => $destination,
        ]);
    }

    #[Route('/{id_destination}/edit', name: 'app_destination_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Destination $destination, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
