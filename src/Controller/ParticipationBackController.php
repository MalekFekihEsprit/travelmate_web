<?php

namespace App\Controller;

use App\Entity\Participation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\ParticipationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ParticipationBackController extends AbstractController
{
    #[Route('/admin/voyages/{id_voyage}/participations', name: 'app_admin_participations', requirements: ['id_voyage' => '\\d+'], methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        ParticipationRepository $participationRepository,
        UserRepository $userRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] ?Voyage $voyage = null
    ): Response {
        if (!$voyage instanceof Voyage) {
            $this->addFlash('warning', 'Ce voyage est introuvable ou a deja ete supprime.');

            return $this->redirectToRoute('app_admin_voyages');
        }

        $filters = $this->extractFilters($request);
        $defaultRole = $this->sanitizeRole((string) $request->request->get('role_participation', Participation::DEFAULT_ROLE));
        $lastEmail = trim((string) $request->request->get('email', ''));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_participation_add_'.$voyage->getIdVoyage(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'La requete d\'ajout du participant est invalide.');

                return $this->redirectToRoute('app_admin_participations', ['id_voyage' => $voyage->getIdVoyage()] + $this->buildRedirectQuery($filters));
            }

            if (!$this->consumeFormNonce($request, 'admin_participation_add_'.$voyage->getIdVoyage(), (string) $request->request->get('_submission_nonce', ''))) {
                return $this->redirectToRoute('app_admin_participations', ['id_voyage' => $voyage->getIdVoyage()] + $this->buildRedirectQuery($filters));
            }

            if ($lastEmail === '') {
                $this->addFlash('error', 'Veuillez saisir un email valide.');
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
                        'voyage' => $voyage,
                        'user' => $user,
                    ]);

                    if ($participation instanceof Participation) {
                        $participation->setRoleParticipation($defaultRole);
                        $this->addFlash('success', 'Le participant etait deja present. Son role a ete mis a jour.');
                    } else {
                        $participation = (new Participation())
                            ->setVoyage($voyage)
                            ->setUser($user)
                            ->setRoleParticipation($defaultRole);

                        $entityManager->persist($participation);
                        $this->addFlash('success', 'Le participant a ete ajoute avec succes.');
                    }

                    $entityManager->flush();
                    $this->markActionHandled($request, 'admin_participation_add_'.$voyage->getIdVoyage());

                    return $this->redirectToRoute('app_admin_participations', ['id_voyage' => $voyage->getIdVoyage()] + $this->buildRedirectQuery($filters));
                }
            }
        }

        return $this->renderParticipationPage(
            request: $request,
            voyage: $voyage,
            participations: $participationRepository->findBackOfficeParticipations($voyage, $filters),
            filters: $filters,
            roleOptions: Participation::getAvailableRoles(),
            lastEmail: $lastEmail,
            lastRole: $defaultRole
        );
    }

    #[Route('/admin/voyages/{id_voyage}/participations/{userId}/modifier', name: 'app_admin_participations_update', requirements: ['id_voyage' => '\\d+', 'userId' => '\\d+'], methods: ['POST'])]
    public function update(
        Request $request,
        EntityManagerInterface $entityManager,
        ParticipationRepository $participationRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] ?Voyage $voyage = null,
        #[MapEntity(mapping: ['userId' => 'id'])] ?User $user = null
    ): Response {
        $redirectParameters = ['id_voyage' => $request->attributes->getInt('id_voyage')] + $this->buildRedirectQuery($this->extractFilters($request));

        if (!$voyage instanceof Voyage || !$user instanceof User) {
            $this->addFlash('warning', 'Cette participation est introuvable ou a deja ete supprimee.');

            return $this->redirectToRoute('app_admin_participations', $redirectParameters);
        }

        if (!$this->isCsrfTokenValid('admin_participation_update_'.$voyage->getIdVoyage().'_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requete de modification du role est invalide.');

            return $this->redirectToRoute('app_admin_participations', $redirectParameters);
        }

        $scope = 'admin_participation_update_'.$voyage->getIdVoyage().'_'.$user->getId();
        if (!$this->consumeFormNonce($request, $scope, (string) $request->request->get('_submission_nonce', ''))) {
            return $this->redirectToRoute('app_admin_participations', $redirectParameters);
        }

        $participation = $participationRepository->findOneBy([
            'voyage' => $voyage,
            'user' => $user,
        ]);

        if (!$participation instanceof Participation) {
            $this->addFlash('warning', 'Cette participation est introuvable ou a deja ete supprimee.');

            return $this->redirectToRoute('app_admin_participations', $redirectParameters);
        }

        $participation->setRoleParticipation($this->sanitizeRole((string) $request->request->get('role_participation', Participation::DEFAULT_ROLE)));
        $entityManager->flush();
        $this->markActionHandled($request, $scope);

        $this->addFlash('success', 'Le role du participant a ete mis a jour avec succes.');

        return $this->redirectToRoute('app_admin_participations', $redirectParameters);
    }

    #[Route('/admin/voyages/{id_voyage}/participations/{userId}/supprimer', name: 'app_admin_participations_delete', requirements: ['id_voyage' => '\\d+', 'userId' => '\\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        ParticipationRepository $participationRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] ?Voyage $voyage = null,
        #[MapEntity(mapping: ['userId' => 'id'])] ?User $user = null
    ): Response {
        $redirectParameters = ['id_voyage' => $request->attributes->getInt('id_voyage')] + $this->buildRedirectQuery($this->extractFilters($request));

        if (!$voyage instanceof Voyage || !$user instanceof User) {
            $this->addFlash('warning', 'Cette participation est introuvable ou a deja ete supprimee.');

            return $this->redirectToRoute('app_admin_participations', $redirectParameters);
        }

        if (!$this->isCsrfTokenValid('admin_participation_delete_'.$voyage->getIdVoyage().'_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requete de suppression est invalide.');

            return $this->redirectToRoute('app_admin_participations', $redirectParameters);
        }

        $scope = 'admin_participation_delete_'.$voyage->getIdVoyage().'_'.$user->getId();
        if (!$this->consumeFormNonce($request, $scope, (string) $request->request->get('_submission_nonce', ''))) {
            return $this->redirectToRoute('app_admin_participations', $redirectParameters);
        }

        $participation = $participationRepository->findOneBy([
            'voyage' => $voyage,
            'user' => $user,
        ]);

        if (!$participation instanceof Participation) {
            $this->addFlash('warning', 'Cette participation est introuvable ou a deja ete supprimee.');

            return $this->redirectToRoute('app_admin_participations', $redirectParameters);
        }

        $entityManager->remove($participation);
        $entityManager->flush();
        $this->markActionHandled($request, $scope);

        $this->addFlash('success', 'Le participant a ete supprime avec succes.');

        return $this->redirectToRoute('app_admin_participations', $redirectParameters);
    }

    /**
     * @param Participation[] $participations
     * @param array{name:string,sort:string} $filters
     * @param string[] $roleOptions
     */
    private function renderParticipationPage(
        Request $request,
        Voyage $voyage,
        array $participations,
        array $filters,
        array $roleOptions,
        string $lastEmail,
        string $lastRole
    ): Response {
        $updateNonces = [];
        $deleteNonces = [];

        foreach ($participations as $participation) {
            $user = $participation->getUser();

            if (!$user instanceof User || $user->getId() === null) {
                continue;
            }

            $userId = $user->getId();
            $updateNonces[$userId] = $this->createFormNonce($request, 'admin_participation_update_'.$voyage->getIdVoyage().'_'.$userId);
            $deleteNonces[$userId] = $this->createFormNonce($request, 'admin_participation_delete_'.$voyage->getIdVoyage().'_'.$userId);
        }

        return $this->render('admin/participation_back.html.twig', [
            'voyage' => $voyage,
            'participations' => $participations,
            'filters' => $filters,
            'role_options' => $roleOptions,
            'last_email' => $lastEmail,
            'last_role' => $lastRole,
            'add_nonce' => $this->createFormNonce($request, 'admin_participation_add_'.$voyage->getIdVoyage()),
            'update_nonces' => $updateNonces,
            'delete_nonces' => $deleteNonces,
        ]);
    }

    /**
     * @return array{name:string,sort:string}
     */
    private function extractFilters(Request $request): array
    {
        $sort = trim((string) $request->query->get('sort', 'name_asc'));
        $allowedSorts = ['name_asc', 'name_desc', 'role_asc', 'role_desc'];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'name_asc';
        }

        return [
            'name' => trim((string) $request->query->get('name', '')),
            'sort' => $sort,
        ];
    }

    private function sanitizeRole(string $role): string
    {
        return in_array($role, Participation::getAvailableRoles(), true) ? $role : Participation::DEFAULT_ROLE;
    }

    /**
     * @param array{name:string,sort:string} $filters
     *
     * @return array<string, string>
     */
    private function buildRedirectQuery(array $filters): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if ($value !== '' && !($key === 'sort' && $value === 'name_asc')) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    private function createFormNonce(Request $request, string $scope): string
    {
        $session = $request->getSession();
        $nonces = $session->get('admin_participation_form_nonces', []);

        if (!is_array($nonces)) {
            $nonces = [];
        }

        $this->pruneExpiredNonces($nonces);

        $nonce = bin2hex(random_bytes(16));
        $nonces[$scope] ??= [];
        $nonces[$scope][$nonce] = time();

        $session->set('admin_participation_form_nonces', $nonces);

        return $nonce;
    }

    private function consumeFormNonce(Request $request, string $scope, string $nonce): bool
    {
        if ($nonce === '') {
            return false;
        }

        $session = $request->getSession();
        $nonces = $session->get('admin_participation_form_nonces', []);

        if (!is_array($nonces) || !isset($nonces[$scope][$nonce])) {
            return false;
        }

        unset($nonces[$scope][$nonce]);

        if ($nonces[$scope] === []) {
            unset($nonces[$scope]);
        }

        $session->set('admin_participation_form_nonces', $nonces);

        return true;
    }

    private function markActionHandled(Request $request, string $scope): void
    {
        $session = $request->getSession();
        $handledActions = $session->get('admin_participation_recent_actions', []);

        if (!is_array($handledActions)) {
            $handledActions = [];
        }

        $this->pruneExpiredHandledActions($handledActions);
        $handledActions[$scope] = time();

        $session->set('admin_participation_recent_actions', $handledActions);
    }

    private function wasActionHandledRecently(Request $request, string $scope): bool
    {
        $session = $request->getSession();
        $handledActions = $session->get('admin_participation_recent_actions', []);

        if (!is_array($handledActions)) {
            return false;
        }

        $this->pruneExpiredHandledActions($handledActions);
        $session->set('admin_participation_recent_actions', $handledActions);

        return isset($handledActions[$scope]);
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

    /**
     * @param array<string, int> $handledActions
     */
    private function pruneExpiredHandledActions(array &$handledActions): void
    {
        $threshold = time() - 10;

        foreach ($handledActions as $scope => $handledAt) {
            if (!is_int($handledAt) || $handledAt < $threshold) {
                unset($handledActions[$scope]);
            }
        }
    }
}