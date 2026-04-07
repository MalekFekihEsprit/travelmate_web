<?php

namespace App\Controller;

use App\Repository\HebergementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/hebergement')]
class HebergementController extends AbstractController
{
    #[Route('/', name: 'app_hebergement_index', methods: ['GET'])]
    public function index(HebergementRepository $hebergementRepository): Response
    {
        $hebergements = $hebergementRepository->findAll();

        // Count unique types
        $types = [];
        $destinations = [];
        
        foreach ($hebergements as $hebergement) {
            if ($hebergement->getType_hebergement()) {
                $types[$hebergement->getType_hebergement()] = true;
            }
            if ($hebergement->getDestination() && $hebergement->getDestination()->getNomDestination()) {
                $destinations[$hebergement->getDestination()->getNomDestination()] = true;
            }
        }

        return $this->render('hebergement/index.html.twig', [
            'hebergements' => $hebergements,
            'unique_types' => count($types),
            'unique_destinations' => count($destinations),
        ]);
    }

    #[Route('/{id_hebergement}', name: 'app_hebergement_show', methods: ['GET'])]
    public function show(int $id_hebergement, HebergementRepository $hebergementRepository): Response
    {
        $hebergement = $hebergementRepository->find($id_hebergement);

        if (!$hebergement) {
            throw $this->createNotFoundException('Hébergement not found');
        }

        return $this->render('hebergement/show.html.twig', [
            'hebergement' => $hebergement,
        ]);
    }
}
