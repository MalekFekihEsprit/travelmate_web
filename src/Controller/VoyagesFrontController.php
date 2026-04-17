<?php

namespace App\Controller;

use App\Entity\Etape;
use App\Entity\Itineraire;
use App\Entity\Participation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Form\VoyageType;
use App\Repository\ActiviteRepository;
use App\Repository\DestinationRepository;
use App\Repository\ItineraireRepository;
use App\Repository\ParticipationRepository;
use App\Repository\UserRepository;
use App\Repository\VoyageRepository;
use App\Service\CerebrasItinerarySuggestionService;
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
        ActiviteRepository $activiteRepository,
        CerebrasItinerarySuggestionService $cerebrasItinerarySuggestionService
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

            $proposal = $cerebrasItinerarySuggestionService->generateForVoyage($voyage);
            $request->getSession()->set($this->getPendingAiItinerarySessionKey($voyage), $proposal);

            $this->addFlash('success', 'Le voyage a ete ajoute.');

            if (($proposal['status'] ?? null) !== 'ready') {
                $this->addFlash('warning', (string) ($proposal['summary'] ?? 'La proposition IA n\'a pas pu etre generee.'));
            }

            return $this->redirectToRoute('app_itineraires_index', [
                'voyageId' => $voyage->getIdVoyage(),
                'ai_proposal' => 1,
            ]);
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


    #[Route('/voyages/{id_voyage}/itineraire-ia/accepter', name: 'app_voyages_ai_itinerary_accept', requirements: ['id_voyage' => '\\d+'], methods: ['POST'])]
    public function acceptAiItinerary(
        Request $request,
        EntityManagerInterface $entityManager,
        ItineraireRepository $itineraireRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage
    ): Response {
        if (!$this->isCsrfTokenValid('accept_ai_itinerary_' . $voyage->getIdVoyage(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requete de validation de l\'itineraire IA est invalide.');

            return $this->redirectToRoute('app_itineraires_index', ['voyageId' => $voyage->getIdVoyage()]);
        }

        $proposal = $this->getPendingAiItineraryProposal($request, $voyage);
        if ($proposal === null || ($proposal['status'] ?? null) !== 'ready') {
            $this->addFlash('warning', 'Aucune proposition IA exploitable n\'est disponible pour ce voyage.');

            return $this->redirectToRoute('app_itineraires_index', ['voyageId' => $voyage->getIdVoyage()]);
        }

        $itineraire = new Itineraire();
        $itineraire->setVoyage($voyage);
        $itineraire->setNom_itineraire($this->buildUniqueItineraryName(
            $itineraireRepository,
            $voyage,
            (string) ($proposal['nom_itineraire'] ?? '')
        ));
        $itineraire->setDescription_itineraire((string) $proposal['description_itineraire']);

        $entityManager->persist($itineraire);
        $entityManager->flush();

        foreach (($proposal['etapes'] ?? []) as $etapeData) {
            if (!is_array($etapeData)) {
                continue;
            }

            $etape = new Etape();
            $etape->setItineraire($itineraire);
            $etape->setNumero_jour((int) ($etapeData['numero_jour'] ?? 1));
            $etape->setDescription_etape((string) ($etapeData['description_etape'] ?? ''));

            $heure = trim((string) ($etapeData['heure'] ?? '09:00'));
            $heureDateTime = \DateTime::createFromFormat('H:i', $heure);
            if (!$heureDateTime) {
                $heureDateTime = new \DateTime('09:00');
            }
            $etape->setHeure($heureDateTime);

            $entityManager->persist($etape);
        }

        $entityManager->flush();

        $this->clearPendingAiItineraryProposal($request, $voyage);
        $this->addFlash('success', 'L\'itineraire IA et ses etapes ont ete enregistres avec succes.');

        return $this->redirectToRoute('app_itineraires_index', ['voyageId' => $voyage->getIdVoyage()]);
    }

    #[Route('/voyages/{id_voyage}/itineraire-ia/refuser', name: 'app_voyages_ai_itinerary_decline', requirements: ['id_voyage' => '\\d+'], methods: ['POST'])]
    public function declineAiItinerary(
        Request $request,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage
    ): Response {
        if (!$this->isCsrfTokenValid('decline_ai_itinerary_' . $voyage->getIdVoyage(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requete de refus de l\'itineraire IA est invalide.');

            return $this->redirectToRoute('app_itineraires_index', ['voyageId' => $voyage->getIdVoyage()]);
        }

        $this->clearPendingAiItineraryProposal($request, $voyage);
        $this->addFlash('warning', 'La proposition IA a ete ignoree. Le voyage a bien ete conserve.');

        return $this->redirectToRoute('app_itineraires_index', ['voyageId' => $voyage->getIdVoyage()]);
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

            foreach ($voyage->getParticipations()->toArray() as $participation) {
                $entityManager->remove($participation);
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

    #[Route('/voyages/{id_voyage}/participants', name: 'app_voyages_participants', requirements: ['id_voyage' => '\\d+'], methods: ['GET', 'POST'])]
    public function participants(
        Request $request,
        EntityManagerInterface $entityManager,
        ParticipationRepository $participationRepository,
        UserRepository $userRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage
    ): Response {
        $lastEmail = trim((string) $request->request->get('email', ''));
        $lastRole = trim((string) $request->request->get('role_participation', Participation::DEFAULT_ROLE));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('add_participant_'.$voyage->getIdVoyage(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'La requete d\'ajout du participant est invalide.');

                return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
            }

            if ($lastEmail === '') {
                $this->addFlash('error', 'Veuillez saisir un email valide.');
            } elseif (!in_array($lastRole, Participation::getAvailableRoles(), true)) {
                $this->addFlash('error', 'Le role selectionne est invalide.');
            } else {
                $user = $userRepository->createQueryBuilder('user')
                    ->andWhere('LOWER(user.email) = :email')
                    ->setParameter('email', mb_strtolower($lastEmail))
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (!$user instanceof User) {
                    $this->addFlash('error', 'Aucun utilisateur n\'a ete trouve avec cet email.');
                } else {
                    $participation = $participationRepository->findOneBy([
                        'user' => $user,
                        'voyage' => $voyage,
                    ]);

                    if ($participation instanceof Participation) {
                        $participation->setRoleParticipation($lastRole);
                        $this->addFlash('success', 'Le participant etait deja present. Son role a ete mis a jour.');
                    } else {
                        $participation = (new Participation())
                            ->setUser($user)
                            ->setVoyage($voyage)
                            ->setRoleParticipation($lastRole);

                        $entityManager->persist($participation);
                        $this->addFlash('success', 'Le participant a ete ajoute au voyage avec succes.');
                    }

                    $entityManager->flush();

                    return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
                }
            }
        }

        return $this->render('home/voyage_participants.html.twig', [
            'voyage' => $voyage,
            'participants' => $participationRepository->findByVoyageOrdered($voyage),
            'role_options' => Participation::getAvailableRoles(),
            'last_email' => $lastEmail,
            'last_role' => in_array($lastRole, Participation::getAvailableRoles(), true) ? $lastRole : Participation::DEFAULT_ROLE,
        ]);
    }

    #[Route('/voyages/{id_voyage}/participants/{userId}/modifier', name: 'app_voyages_participants_update', requirements: ['id_voyage' => '\\d+', 'userId' => '\\d+'], methods: ['POST'])]
    public function updateParticipant(
        Request $request,
        EntityManagerInterface $entityManager,
        ParticipationRepository $participationRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage,
        #[MapEntity(mapping: ['userId' => 'id'])] User $user
    ): Response {
        if (!$this->isCsrfTokenValid('update_participant_'.$voyage->getIdVoyage().'_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requete de modification du participant est invalide.');

            return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
        }

        $role = trim((string) $request->request->get('role_participation', Participation::DEFAULT_ROLE));

        if (!in_array($role, Participation::getAvailableRoles(), true)) {
            $this->addFlash('error', 'Le role selectionne est invalide.');

            return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
        }

        $participation = $participationRepository->findOneBy([
            'user' => $user,
            'voyage' => $voyage,
        ]);

        if (!$participation instanceof Participation) {
            $this->addFlash('error', 'Ce participant n\'est pas associe a ce voyage.');

            return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
        }

        $participation->setRoleParticipation($role);
        $entityManager->flush();

        $this->addFlash('success', 'Le role du participant a ete mis a jour avec succes.');

        return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
    }

    #[Route('/voyages/{id_voyage}/participants/{userId}/supprimer', name: 'app_voyages_participants_delete', requirements: ['id_voyage' => '\\d+', 'userId' => '\\d+'], methods: ['POST'])]
    public function deleteParticipant(
        Request $request,
        EntityManagerInterface $entityManager,
        ParticipationRepository $participationRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage,
        #[MapEntity(mapping: ['userId' => 'id'])] User $user
    ): Response {
        if (!$this->isCsrfTokenValid('delete_participant_'.$voyage->getIdVoyage().'_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requete de suppression du participant est invalide.');

            return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
        }

        $participation = $participationRepository->findOneBy([
            'user' => $user,
            'voyage' => $voyage,
        ]);

        if (!$participation instanceof Participation) {
            $this->addFlash('error', 'Ce participant n\'est pas associe a ce voyage.');

            return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
        }

        $entityManager->remove($participation);
        $entityManager->flush();

        $this->addFlash('success', 'Le participant a ete retire du voyage avec succes.');

        return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
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

    private function buildUniqueItineraryName(ItineraireRepository $itineraireRepository, Voyage $voyage, string $proposedName): string
    {
        $baseName = trim($proposedName);
        if ($baseName === '') {
            $baseName = 'Itineraire IA';
        }

        $candidate = mb_substr($baseName, 0, 120);
        $index = 2;

        while ($itineraireRepository->findOneBy([
            'voyage' => $voyage,
            'nom_itineraire' => $candidate,
        ]) instanceof Itineraire) {
            $suffix = sprintf(' (%d)', $index);
            $candidate = mb_substr($baseName, 0, 120 - mb_strlen($suffix)) . $suffix;
            ++$index;
        }

        return $candidate;
    }

    private function getPendingAiItinerarySessionKey(Voyage $voyage): string
    {
        return 'voyage_ai_itinerary_' . $voyage->getIdVoyage();
    }

    private function getPendingAiItineraryProposal(Request $request, Voyage $voyage): ?array
    {
        $proposal = $request->getSession()->get($this->getPendingAiItinerarySessionKey($voyage));

        return is_array($proposal) ? $proposal : null;
    }

    private function clearPendingAiItineraryProposal(Request $request, Voyage $voyage): void
    {
        $request->getSession()->remove($this->getPendingAiItinerarySessionKey($voyage));
    }
}