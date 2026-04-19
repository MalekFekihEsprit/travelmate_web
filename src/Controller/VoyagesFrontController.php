<?php

namespace App\Controller;

use App\Entity\Participation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Form\VoyageType;
use App\Repository\ActiviteRepository;
use App\Repository\BudgetRepository;
use App\Repository\DestinationRepository;
use App\Repository\ParticipationRepository;
use App\Repository\UserRepository;
use App\Repository\VoyageRepository;
use App\Service\VoyageQrCodeFactory;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class VoyagesFrontController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
        #[Autowire('%env(SMTP_EMAIL)%')]
        private readonly string $mailerFromEmail,
        #[Autowire('%env(SMTP_FROM_NAME)%')]
        private readonly string $mailerFromName,
    ) {
    }

    #[Route('/voyages', name: 'app_voyages', methods: ['GET'])]
    public function index(
        Request $request,
        VoyageRepository $voyageRepository,
        DestinationRepository $destinationRepository
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 6;
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

        $pagination = $voyageRepository->paginateFilteredVoyages($filters, $page, $perPage);

        if ($page !== $pagination['current_page']) {
            return $this->redirectToRoute('app_voyages', array_filter([
                'search' => $filters['search'],
                'statut' => $filters['statut'],
                'destination' => $filters['destination'],
                'sort' => $filters['sort'],
                'page' => $pagination['current_page'],
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        }

        $voyages = $pagination['items'];
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
            'results_count' => $pagination['total'],
            'current_page' => $pagination['current_page'],
            'per_page' => $pagination['per_page'],
            'total_pages' => $pagination['total_pages'],
            'page_items_count' => count($voyages),
            'all_voyages_count' => $allVoyagesCount,
            'has_active_filters' => $filters['search'] !== '' || $filters['statut'] !== '' || $filters['destination'] !== null || $filters['sort'] !== 'date_asc',
        ]);
    }

    #[Route('/voyages/{id_voyage}', name: 'app_voyages_show', requirements: ['id_voyage' => '\\d+'], methods: ['GET'])]
    public function show(
        BudgetRepository $budgetRepository,
        ParticipationRepository $participationRepository,
        VoyageQrCodeFactory $voyageQrCodeFactory,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage
    ): Response {
        $galleryPaths = $this->getVoyageGalleryPaths();
        $voyageId = $voyage->getIdVoyage() ?? 0;
        $voyageImages = $this->buildVoyageImageMap([$voyage], $galleryPaths);
        $budgetSummary = $budgetRepository->findVoyageBudgetSummaries([$voyage])[$voyageId] ?? null;
        $participants = $participationRepository->findByVoyageOrdered($voyage);
        $budgetTotalLabel = $this->formatBudgetSummary($budgetSummary);

        return $this->render('home/voyage_show.html.twig', [
            'voyage' => $voyage,
            'voyage_image' => $voyageImages[$voyageId] ?? ($galleryPaths[0] ?? null),
            'participants' => $participants,
            'budget_summary' => $budgetSummary,
            'budget_total_label' => $budgetTotalLabel,
            'qr_data_uri' => $voyageQrCodeFactory->createDataUri($voyage, $budgetTotalLabel, count($participants)),
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
            } elseif (!Participation::isSelectableRole($lastRole)) {
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

                    $isExistingParticipation = $participation instanceof Participation;

                    if ($participation instanceof Participation) {
                        $participation->setRoleParticipation($lastRole);
                    } else {
                        $participation = (new Participation())
                            ->setUser($user)
                            ->setVoyage($voyage)
                            ->setRoleParticipation($lastRole);

                        $entityManager->persist($participation);
                    }

                    $entityManager->flush();

                    try {
                        $this->sendParticipationAddedEmail($user, $voyage, $participation, $isExistingParticipation);

                        $this->addFlash(
                            'success',
                            $isExistingParticipation
                                ? 'Le role du participant a ete mis a jour et un email de notification a ete envoye.'
                                : 'Le participant a ete ajoute au voyage et un email de notification a ete envoye.'
                        );
                    } catch (\Throwable) {
                        $this->addFlash(
                            'warning',
                            $isExistingParticipation
                                ? 'Le role du participant a ete mis a jour, mais l\'email de notification n\'a pas pu etre envoye.'
                                : 'Le participant a ete ajoute au voyage, mais l\'email de notification n\'a pas pu etre envoye.'
                        );
                    }

                    return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
                }
            }
        }

        return $this->render('home/voyage_participants.html.twig', [
            'voyage' => $voyage,
            'participants' => $participationRepository->findByVoyageOrdered($voyage),
            'role_options' => Participation::getSelectableRoles(),
            'last_email' => $lastEmail,
            'last_role' => Participation::isSelectableRole($lastRole) ? $lastRole : Participation::DEFAULT_ROLE,
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

        $participation = $participationRepository->findOneBy([
            'user' => $user,
            'voyage' => $voyage,
        ]);

        if (!$participation instanceof Participation) {
            $this->addFlash('error', 'Ce participant n\'est pas associe a ce voyage.');

            return $this->redirectToRoute('app_voyages_participants', ['id_voyage' => $voyage->getIdVoyage()]);
        }

        if (!Participation::isSelectableRole($role) && $role !== $participation->getRoleParticipation()) {
            $this->addFlash('error', 'Le role selectionne est invalide.');

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

    /**
     * @param array{totalAmount: float, currency: string|null, currencyCount: int}|null $budgetSummary
     */
    private function formatBudgetSummary(?array $budgetSummary): string
    {
        if ($budgetSummary === null) {
            return '-';
        }

        $formattedAmount = number_format((float) $budgetSummary['totalAmount'], 2, ',', ' ');

        if (($budgetSummary['currencyCount'] ?? 0) > 1) {
            return $formattedAmount.' multi-devise';
        }

        $currency = $budgetSummary['currency'] ?? null;

        return is_string($currency) && $currency !== ''
            ? $formattedAmount.' '.$currency
            : $formattedAmount;
    }

    private function sendParticipationAddedEmail(User $user, Voyage $voyage, Participation $participation, bool $isExistingParticipation): void
    {
        if ($this->mailerDsn === '' || str_starts_with($this->mailerDsn, 'null://')) {
            throw new \RuntimeException('Mailer transport is disabled.');
        }

        $voyageUrl = $this->generateUrl(
            'app_voyages_show',
            ['id_voyage' => $voyage->getIdVoyage()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $voyageTitle = $voyage->getTitreVoyage() ?? 'votre voyage';
        $recipientName = trim((string) ($user->getPrenom() ?? '')).' '.trim((string) ($user->getNom() ?? ''));
        $recipientName = trim($recipientName) !== '' ? trim($recipientName) : (string) $user->getEmail();
        $destinationName = $voyage->getDestination()?->getNomDestination();
        $emailTitle = $isExistingParticipation ? 'Votre participation a ete mise a jour' : 'Vous etes maintenant participant au voyage';
        $emailIntro = $isExistingParticipation
            ? 'Votre role sur ce voyage vient d\'etre mis a jour. Voici le recapitulatif.'
            : 'Vous avez ete ajoute a un voyage sur TravelMate. Voici les informations utiles.';
        $buttonLabel = $isExistingParticipation ? 'Voir les details du voyage' : 'Decouvrir le voyage';

        $html = $this->renderView('emails/participant_notification.html.twig', [
            'recipient_name' => $recipientName,
            'email_title' => $emailTitle,
            'email_intro' => $emailIntro,
            'voyage' => $voyage,
            'destination_name' => $destinationName,
            'role_label' => $participation->getRoleParticipation(),
            'voyage_url' => $voyageUrl,
            'button_label' => $buttonLabel,
            'is_role_update' => $isExistingParticipation,
        ]);

        $text = sprintf(
            "%s\n\nBonjour %s,\n\n%s\n\nVoyage: %s\nRole: %s\n%s\nLien: %s\n",
            $emailTitle,
            $recipientName,
            $emailIntro,
            $voyageTitle,
            $participation->getRoleParticipation(),
            $destinationName !== null ? 'Destination: '.$destinationName : 'Destination: non precisee',
            $voyageUrl
        );

        (new Mailer(Transport::fromDsn($this->mailerDsn)))->send(
            (new Email())
                ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
                ->to((string) $user->getEmail())
                ->subject(sprintf('%s | %s', $emailTitle, $voyageTitle))
                ->text($text)
                ->html($html)
        );
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