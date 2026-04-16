<?php

namespace App\Controller;

use App\Repository\DestinationRepository;
use App\Repository\HebergementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        $allHebergements = $hebergementRepository->findBy([], ['id_hebergement' => 'DESC']);
        $destinationOptionsMap = [];

        foreach ($allHebergements as $item) {
            $destination = $item->getDestination();
            if ($destination && $destination->getIdDestination()) {
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
            'search' => $search,
            'selected_type' => $typeFilter,
            'selected_sort' => $sort,
            'selected_destination' => $selectedDestination,
            'selected_destination_id' => $destinationId,
            'destination_options' => $destinationOptions,
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
