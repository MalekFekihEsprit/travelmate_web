<?php

namespace App\Controller;

use App\Entity\Voyage;
use App\Form\VoyageType;
use App\Repository\ActiviteRepository;
use App\Repository\DestinationRepository;
use App\Repository\VoyageRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoyagesFrontController extends AbstractController
{
    #[Route('/voyages', name: 'app_voyages', methods: ['GET'])]
    public function index(
        Request $request,
        VoyageRepository $voyageRepository,
        DestinationRepository $destinationRepository
    ): Response
    {
        $filters = [
            'search' => trim((string) $request->query->get('search', '')),
            'statut' => trim((string) $request->query->get('statut', '')),
            'destination' => $request->query->getInt('destination') ?: null,
            'sort' => trim((string) $request->query->get('sort', 'date_asc')),
        ];

        $allowedSorts = ['date_asc', 'date_desc', 'title_asc', 'title_desc', 'status_asc', 'destination_asc'];
        if (!in_array($filters['sort'], $allowedSorts, true)) {
            $filters['sort'] = 'date_asc';
        }

        $voyages = $voyageRepository->findFilteredVoyages($filters);
        $galleryPaths = $this->getVoyageGalleryPaths();
        $allVoyagesCount = $voyageRepository->count([]);

        return $this->render('home/voyages.html.twig', [
            'voyages' => $voyages,
            'voyage_images' => $this->buildVoyageImageMap($voyages, $galleryPaths),
            'hero_image' => $galleryPaths !== [] ? $galleryPaths[array_rand($galleryPaths)] : null,
            'voyage_gallery_count' => count($galleryPaths),
            'filters' => $filters,
            'destinations' => $destinationRepository->findBy([], ['nom_destination' => 'ASC']),
            'status_options' => Voyage::getAvailableStatuts(),
            'sort_options' => [
                'date_asc' => 'Date de debut croissante',
                'date_desc' => 'Date de debut decroissante',
                'title_asc' => 'Titre A a Z',
                'title_desc' => 'Titre Z a A',
                'status_asc' => 'Statut',
                'destination_asc' => 'Destination',
            ],
            'results_count' => count($voyages),
            'all_voyages_count' => $allVoyagesCount,
            'has_active_filters' => $filters['search'] !== '' || $filters['statut'] !== '' || $filters['destination'] !== null || $filters['sort'] !== 'date_asc',
        ]);
    }

    #[Route('/voyages/ajouter', name: 'app_voyages_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        DestinationRepository $destinationRepository,
        ActiviteRepository $activiteRepository
    ): Response {
        $formScope = 'voyage_new';
        $voyage = new Voyage();
        $voyage->setStatut('Planifie');

        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);

        $formNonce = $request->isMethod('POST')
            ? (string) $request->request->get('_voyage_form_nonce', '')
            : $this->createFormNonce($request, $formScope);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->consumeFormNonce($request, $formScope, $formNonce)) {
                $this->addFlash('warning', 'Cette soumission a deja ete traitee.');

                return $this->redirectToRoute('app_voyages');
            }

            $entityManager->persist($voyage);
            $entityManager->flush();

            $this->addFlash('success', 'Le voyage a ete ajoute avec succes.');

            return $this->redirectToRoute('app_voyages');
        }

        return $this->render('home/voyage_form.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Ajouter un voyage',
            'page_description' => 'Creez un voyage avec un formulaire controle et une mise en page coherente avec TravelMate.',
            'submit_label' => 'Enregistrer le voyage',
            'has_destinations' => $destinationRepository->count([]) > 0,
            'has_activites' => $activiteRepository->count([]) > 0,
            'form_nonce' => $formNonce !== '' ? $formNonce : $this->createFormNonce($request, $formScope),
        ]);
    }

    #[Route('/voyages/{id_voyage}/modifier', name: 'app_voyages_edit', requirements: ['id_voyage' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        DestinationRepository $destinationRepository,
        ActiviteRepository $activiteRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage
    ): Response {
        $formScope = 'voyage_edit_'.$voyage->getIdVoyage();
        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);

        $formNonce = $request->isMethod('POST')
            ? (string) $request->request->get('_voyage_form_nonce', '')
            : $this->createFormNonce($request, $formScope);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->consumeFormNonce($request, $formScope, $formNonce)) {
                $this->addFlash('warning', 'Cette soumission a deja ete traitee.');

                return $this->redirectToRoute('app_voyages');
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le voyage a ete modifie avec succes.');

            return $this->redirectToRoute('app_voyages');
        }

        return $this->render('home/voyage_form.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Modifier le voyage',
            'page_description' => 'Mettez a jour les informations du voyage .',
            'submit_label' => 'Mettre a jour',
            'has_destinations' => $destinationRepository->count([]) > 0,
            'has_activites' => $activiteRepository->count([]) > 0,
            'form_nonce' => $formNonce !== '' ? $formNonce : $this->createFormNonce($request, $formScope),
        ]);
    }

    #[Route('/voyages/{id_voyage}/supprimer', name: 'app_voyages_delete', requirements: ['id_voyage' => '\\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage
    ): Response {
        if (!$this->isCsrfTokenValid('delete_voyage_'.$voyage->getIdVoyage(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requete de suppression est invalide.');

            return $this->redirectToRoute('app_voyages');
        }

        try {
            foreach ($voyage->getActivites()->toArray() as $activite) {
                $voyage->removeActivite($activite);
            }

            foreach ($voyage->getUsers()->toArray() as $user) {
                $voyage->removeUser($user);
            }

            foreach ($voyage->getBudgets()->toArray() as $budget) {
                foreach ($budget->getDepenses()->toArray() as $depense) {
                    $entityManager->remove($depense);
                }

                $entityManager->remove($budget);
            }

            foreach ($voyage->getItineraires()->toArray() as $itineraire) {
                foreach ($itineraire->getEtapes()->toArray() as $etape) {
                    $entityManager->remove($etape);
                }

                $entityManager->remove($itineraire);
            }

            foreach ($voyage->getPaiements()->toArray() as $paiement) {
                $entityManager->remove($paiement);
            }

            $entityManager->remove($voyage);
            $entityManager->flush();

            $this->addFlash('success', 'Le voyage a ete supprime avec succes.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'Ce voyage ne peut pas etre supprime car il est lie a d\'autres donnees.');
        }

        return $this->redirectToRoute('app_voyages');
    }

    private function getVoyageGalleryPaths(): array
    {
        $directory = $this->getParameter('kernel.project_dir').DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'imagesVoyage';

        if (!is_dir($directory)) {
            return [];
        }

        $paths = [];

        foreach (scandir($directory) ?: [] as $fileName) {
            if (in_array($fileName, ['.', '..'], true)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }

            $paths[] = 'images/imagesVoyage/'.$fileName;
        }

        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    /**
     * @param Voyage[] $voyages
     * @param string[] $galleryPaths
     *
     * @return array<int, string>
     */
    private function buildVoyageImageMap(array $voyages, array $galleryPaths): array
    {
        if ($galleryPaths === []) {
            return [];
        }

        $shuffledPaths = $galleryPaths;
        shuffle($shuffledPaths);

        $imageMap = [];
        $galleryCount = count($shuffledPaths);

        foreach (array_values($voyages) as $index => $voyage) {
            $voyageId = $voyage->getIdVoyage();

            if ($voyageId === null) {
                continue;
            }

            $imageMap[$voyageId] = $shuffledPaths[$index % $galleryCount];
        }

        return $imageMap;
    }

    private function createFormNonce(Request $request, string $scope): string
    {
        $session = $request->getSession();
        $nonces = $session->get('voyage_form_nonces', []);

        if (!is_array($nonces)) {
            $nonces = [];
        }

        $this->pruneExpiredNonces($nonces);

        $nonce = bin2hex(random_bytes(16));
        $nonces[$scope] ??= [];
        $nonces[$scope][$nonce] = time();

        $session->set('voyage_form_nonces', $nonces);

        return $nonce;
    }

    private function consumeFormNonce(Request $request, string $scope, string $nonce): bool
    {
        if ($nonce === '') {
            return false;
        }

        $session = $request->getSession();
        $nonces = $session->get('voyage_form_nonces', []);

        if (!is_array($nonces) || !isset($nonces[$scope][$nonce])) {
            return false;
        }

        unset($nonces[$scope][$nonce]);

        if ($nonces[$scope] === []) {
            unset($nonces[$scope]);
        }

        $session->set('voyage_form_nonces', $nonces);

        return true;
    }

    /**
     * @param array<string, array<string, int>> $nonces
     */
    private function pruneExpiredNonces(array &$nonces): void
    {
        $threshold = time() - 3600;

        foreach ($nonces as $scope => $scopeNonces) {
            if (!is_array($scopeNonces)) {
                unset($nonces[$scope]);
                continue;
            }

            foreach ($scopeNonces as $nonce => $createdAt) {
                if (!is_int($createdAt) || $createdAt < $threshold) {
                    unset($nonces[$scope][$nonce]);
                }
            }

            if ($nonces[$scope] === []) {
                unset($nonces[$scope]);
            }
        }
    }
}