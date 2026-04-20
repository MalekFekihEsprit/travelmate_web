<?php

namespace App\Controller;

use App\Entity\Hebergement;
use App\Form\HebergementType;
use App\Repository\DestinationRepository;
use App\Repository\HebergementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/hebergements')]
class HebergementAdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_hebergements', methods: ['GET'])]
    public function index(Request $request, HebergementRepository $hebergementRepository, DestinationRepository $destinationRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $typeFilter = trim((string) $request->query->get('type', ''));
        $destinationFilter = trim((string) $request->query->get('destination', ''));
        $sort = (string) $request->query->get('sort', 'recent');

        $allHebergements = $hebergementRepository->findBy([], ['idHebergement' => 'DESC']);

        $hebergements = array_values(array_filter($allHebergements, static function (Hebergement $hebergement) use ($search, $typeFilter, $destinationFilter): bool {
            if ($search !== '') {
                $destinationName = $hebergement->getDestination() ? $hebergement->getDestination()->getNomDestination() : '';
                $haystack = mb_strtolower(sprintf('%s %s %s %s',
                    $hebergement->getNomHebergement() ?? '',
                    $hebergement->getTypeHebergement() ?? '',
                    $destinationName ?? '',
                    $hebergement->getAdresseHebergement() ?? ''
                ));

                if (mb_strpos($haystack, mb_strtolower($search)) === false) {
                    return false;
                }
            }

            if ($typeFilter !== '' && mb_strtolower((string) $hebergement->getTypeHebergement()) !== mb_strtolower($typeFilter)) {
                return false;
            }

            if ($destinationFilter !== '') {
                $destinationName = $hebergement->getDestination() ? $hebergement->getDestination()->getNomDestination() : '';
                if (mb_strtolower((string) $destinationName) !== mb_strtolower($destinationFilter)) {
                    return false;
                }
            }

            return true;
        }));

        usort($hebergements, static function (Hebergement $left, Hebergement $right) use ($sort): int {
            return match ($sort) {
                'name_asc' => strcmp(mb_strtolower($left->getNomHebergement() ?? ''), mb_strtolower($right->getNomHebergement() ?? '')),
                'name_desc' => strcmp(mb_strtolower($right->getNomHebergement() ?? ''), mb_strtolower($left->getNomHebergement() ?? '')),
                'price_asc' => (float) ($left->getPrixNuitHebergement() ?? 0) <=> (float) ($right->getPrixNuitHebergement() ?? 0),
                'price_desc' => (float) ($right->getPrixNuitHebergement() ?? 0) <=> (float) ($left->getPrixNuitHebergement() ?? 0),
                'note_asc' => (float) ($left->getNoteHebergement() ?? 0) <=> (float) ($right->getNoteHebergement() ?? 0),
                'note_desc' => (float) ($right->getNoteHebergement() ?? 0) <=> (float) ($left->getNoteHebergement() ?? 0),
                default => ($right->getIdHebergement() ?? 0) <=> ($left->getIdHebergement() ?? 0),
            };
        });

        $types = [];
        $destinations = [];
        $prices = [];
        $notes = [];

        foreach ($allHebergements as $hebergement) {
            if ($hebergement->getTypeHebergement()) {
                $types[] = $hebergement->getTypeHebergement();
            }
            if ($hebergement->getDestination() && $hebergement->getDestination()->getNomDestination()) {
                $destinations[] = $hebergement->getDestination()->getNomDestination();
            }
            if ($hebergement->getPrixNuitHebergement() !== null) {
                $prices[] = (float) $hebergement->getPrixNuitHebergement();
            }
            if ($hebergement->getNoteHebergement() !== null) {
                $notes[] = (float) $hebergement->getNoteHebergement();
            }
        }

        $types = array_values(array_unique($types));
        sort($types);
        $destinations = array_values(array_unique($destinations));
        sort($destinations);

        $averagePrice = $prices !== [] ? round(array_sum($prices) / count($prices), 2) : null;
        $averageNote = $notes !== [] ? round(array_sum($notes) / count($notes), 1) : null;
        $totalDestinationsAvailable = $destinationRepository->count([]);

        return $this->render('hebergement_admin/index.html.twig', [
            'hebergements' => $hebergements,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'destinationFilter' => $destinationFilter,
            'sort' => $sort,
            'types' => $types,
            'destinationsList' => $destinations,
            'stats' => [
                'total' => count($allHebergements),
                'types' => count($types),
                'destinations' => count($destinations),
                'destinationsAvailable' => $totalDestinationsAvailable,
                'averagePrice' => $averagePrice,
                'averageNote' => $averageNote,
            ],
        ]);
    }

    #[Route('/new', name: 'app_admin_hebergement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, HebergementRepository $hebergementRepository): Response
    {
        $hebergement = new Hebergement();
        $form = $this->createForm(HebergementType::class, $hebergement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $duplicateHebergement = $hebergementRepository->findDuplicateByName(
                (string) $hebergement->getNomHebergement(),
            );

            if ($duplicateHebergement !== null) {
                $message = 'Un hebergement avec ce nom existe deja.';
                $form->get('nom_hebergement')->addError(new FormError($message));
                $form->addError(new FormError($message));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($hebergement);
            $entityManager->flush();

            $this->addFlash('success', 'Hebergement ajoute avec succes.');

            return $this->redirectToRoute('app_admin_hebergements');
        }

        return $this->render('hebergement_admin/new.html.twig', [
            'hebergement' => $hebergement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_hebergement_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Request $request, Hebergement $hebergement): Response
    {
        if ($request->query->getBoolean('inline')) {
            return $this->render('hebergement_admin/_show_content.html.twig', [
                'hebergement' => $hebergement,
            ]);
        }

        return $this->render('hebergement_admin/show.html.twig', [
            'hebergement' => $hebergement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_hebergement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Request $request, Hebergement $hebergement, EntityManagerInterface $entityManager, HebergementRepository $hebergementRepository): Response
    {
        $form = $this->createForm(HebergementType::class, $hebergement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $duplicateHebergement = $hebergementRepository->findDuplicateByName(
                (string) $hebergement->getNomHebergement(),
                $hebergement->getIdHebergement(),
            );

            if ($duplicateHebergement !== null) {
                $message = 'Un hebergement avec ce nom existe deja.';
                $form->get('nom_hebergement')->addError(new FormError($message));
                $form->addError(new FormError($message));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Hebergement modifie avec succes.');

            return $this->redirectToRoute('app_admin_hebergements');
        }

        return $this->render('hebergement_admin/edit.html.twig', [
            'hebergement' => $hebergement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_hebergement_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Hebergement $hebergement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_hebergement_' . $hebergement->getIdHebergement(), (string) $request->request->get('_token'))) {
            $entityManager->remove($hebergement);
            $entityManager->flush();
            $this->addFlash('success', 'Hebergement supprime avec succes.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_hebergements');
    }

    #[Route('/bulk-delete', name: 'app_admin_hebergements_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, HebergementRepository $repo, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete_hebergements', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_hebergements');
        }

        $ids = array_filter(array_map('intval', (array) $request->request->all('ids')));

        if (empty($ids)) {
            $this->addFlash('error', 'Veuillez selectionner au moins un hebergement.');
            return $this->redirectToRoute('app_admin_hebergements');
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            $hebergement = $repo->find($id);
            if ($hebergement) {
                $entityManager->remove($hebergement);
                ++$deletedCount;
            }
        }

        if ($deletedCount > 0) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('%d hebergement(s) supprime(s) avec succes.', $deletedCount));
        } else {
            $this->addFlash('error', 'Aucun hebergement valide a supprimer.');
        }

        return $this->redirectToRoute('app_admin_hebergements');
    }
}
