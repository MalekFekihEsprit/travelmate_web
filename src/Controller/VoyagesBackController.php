<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Entity\Voyage;
use App\Repository\DestinationRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class VoyagesBackController extends AbstractController
{
	#[Route('/admin/voyages', name: 'app_admin_voyages', methods: ['GET'])]
	public function index(
		Request $request,
		VoyageRepository $voyageRepository,
		DestinationRepository $destinationRepository
	): Response {
		$search = trim((string) $request->query->get('search', ''));
		$editingVoyage = null;
		$editId = $request->query->getInt('edit');

		if ($editId > 0) {
			$editingVoyage = $voyageRepository->find($editId);
		}

		return $this->renderAdminVoyagesPage(
			request: $request,
			voyages: $this->findBackOfficeVoyages($voyageRepository, $search),
			destinations: $destinationRepository->findBy([], ['nom_destination' => 'ASC']),
			voyageForm: $editingVoyage instanceof Voyage ? $editingVoyage : new Voyage(),
			editingVoyage: $editingVoyage instanceof Voyage ? $editingVoyage : null,
			formErrors: []
		);
	}

	#[Route('/admin/voyages/creer', name: 'app_admin_voyages_create', methods: ['POST'])]
	public function create(
		Request $request,
		EntityManagerInterface $entityManager,
		DestinationRepository $destinationRepository,
		VoyageRepository $voyageRepository,
		ValidatorInterface $validator
	): Response {
		if (!$this->isCsrfTokenValid('admin_voyage_create', (string) $request->request->get('_token'))) {
			$this->addFlash('error', 'La requete d\'ajout est invalide.');

			return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
		}

		if (!$this->consumeFormNonce($request, 'admin_voyage_create', (string) $request->request->get('_submission_nonce', ''))) {
			if ($this->wasActionHandledRecently($request, 'admin_voyage_create')) {
				return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
			}

			$this->addFlash('warning', 'Cette soumission a deja ete traitee.');

			return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
		}

		$voyage = new Voyage();
		$formErrors = $this->hydrateVoyageFromRequest($request, $voyage, $destinationRepository, $validator);

		if ($formErrors !== []) {
			return $this->renderAdminVoyagesPage(
				request: $request,
				voyages: $this->findBackOfficeVoyages($voyageRepository, $this->extractRedirectSearch($request)),
				destinations: $destinationRepository->findBy([], ['nom_destination' => 'ASC']),
				voyageForm: $voyage,
				editingVoyage: null,
				formErrors: $formErrors
			);
		}

		$entityManager->persist($voyage);
		$entityManager->flush();
		$this->markActionHandled($request, 'admin_voyage_create');

		$this->addFlash('success', 'Le voyage a ete ajoute avec succes.');

		return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
	}

	#[Route('/admin/voyages/{id_voyage}/modifier', name: 'app_admin_voyages_update', requirements: ['id_voyage' => '\\d+'], methods: ['POST'])]
	public function update(
		Request $request,
		EntityManagerInterface $entityManager,
		DestinationRepository $destinationRepository,
		VoyageRepository $voyageRepository,
		ValidatorInterface $validator,
		#[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] ?Voyage $voyage = null
	): Response {
		if (!$voyage instanceof Voyage) {
			$this->addFlash('warning', 'Ce voyage est introuvable ou a deja ete supprime.');

			return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
		}

		if (!$this->isCsrfTokenValid('admin_voyage_update_'.$voyage->getIdVoyage(), (string) $request->request->get('_token'))) {
			$this->addFlash('error', 'La requete de modification est invalide.');

			return $this->redirectToRoute('app_admin_voyages', array_merge($this->buildRedirectQuery($request), ['edit' => $voyage->getIdVoyage()]));
		}

		if (!$this->consumeFormNonce($request, 'admin_voyage_update_'.$voyage->getIdVoyage(), (string) $request->request->get('_submission_nonce', ''))) {
			if ($this->wasActionHandledRecently($request, 'admin_voyage_update_'.$voyage->getIdVoyage())) {
				return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
			}

			$this->addFlash('warning', 'Cette modification a deja ete traitee.');

			return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
		}

		$formErrors = $this->hydrateVoyageFromRequest($request, $voyage, $destinationRepository, $validator);

		if ($formErrors !== []) {
			return $this->renderAdminVoyagesPage(
				request: $request,
				voyages: $this->findBackOfficeVoyages($voyageRepository, $this->extractRedirectSearch($request)),
				destinations: $destinationRepository->findBy([], ['nom_destination' => 'ASC']),
				voyageForm: $voyage,
				editingVoyage: $voyage,
				formErrors: $formErrors
			);
		}

		$entityManager->flush();
		$this->markActionHandled($request, 'admin_voyage_update_'.$voyage->getIdVoyage());

		$this->addFlash('success', 'Le voyage a ete modifie avec succes.');

		return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
	}

	#[Route('/admin/voyages/{id_voyage}/supprimer', name: 'app_admin_voyages_delete', requirements: ['id_voyage' => '\\d+'], methods: ['POST'])]
	public function delete(
		Request $request,
		EntityManagerInterface $entityManager,
		#[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] ?Voyage $voyage = null
	): Response {
		if (!$voyage instanceof Voyage) {
			$this->addFlash('warning', 'Ce voyage est introuvable ou a deja ete supprime.');

			return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
		}

		if (!$this->isCsrfTokenValid('admin_voyage_delete_'.$voyage->getIdVoyage(), (string) $request->request->get('_token'))) {
			$this->addFlash('error', 'La requete de suppression est invalide.');

			return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
		}

		if (!$this->consumeFormNonce($request, 'admin_voyage_delete_'.$voyage->getIdVoyage(), (string) $request->request->get('_submission_nonce', ''))) {
			if ($this->wasActionHandledRecently($request, 'admin_voyage_delete_'.$voyage->getIdVoyage())) {
				return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
			}

			$this->addFlash('warning', 'Cette suppression a deja ete traitee.');

			return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
		}

		foreach ($voyage->getParticipations()->toArray() as $participation) {
			$entityManager->remove($participation);
		}

		foreach ($voyage->getActivites()->toArray() as $activite) {
			$voyage->removeActivite($activite);
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
		$this->markActionHandled($request, 'admin_voyage_delete_'.$voyage->getIdVoyage());

		$this->addFlash('success', 'Le voyage a ete supprime avec succes.');

		return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
	}

	/**
	 * @return Voyage[]
	 */
	private function findBackOfficeVoyages(VoyageRepository $voyageRepository, string $search = ''): array
	{
		$queryBuilder = $voyageRepository->createQueryBuilder('voyage')
			->leftJoin('voyage.destination', 'destination')
			->addSelect('destination')
			->orderBy('voyage.date_debut', 'DESC')
			->addOrderBy('voyage.id_voyage', 'DESC');

		$search = trim($search);

		if ($search !== '') {
			$searchExpression = 'LOWER(voyage.titre_voyage) LIKE :search OR LOWER(voyage.statut) LIKE :search OR LOWER(destination.nom_destination) LIKE :search OR LOWER(destination.pays_destination) LIKE :search';

			if (ctype_digit($search)) {
				$searchExpression .= ' OR voyage.id_voyage = :searchId';
				$queryBuilder->setParameter('searchId', (int) $search);
			}

			$queryBuilder
				->andWhere($searchExpression)
				->setParameter('search', '%'.mb_strtolower($search).'%');
		}

		return $queryBuilder->getQuery()->getResult();
	}

	private function buildRedirectQuery(Request $request): array
	{
		$search = $this->extractRedirectSearch($request);

		return $search === '' ? [] : ['search' => $search];
	}

	private function extractRedirectSearch(Request $request): string
	{
		return trim((string) ($request->request->get('current_search', $request->query->get('search', ''))));
	}

	/**
	 * @return list<string>
	 */
	private function hydrateVoyageFromRequest(
		Request $request,
		Voyage $voyage,
		DestinationRepository $destinationRepository,
		ValidatorInterface $validator
	): array {
		$voyage->setTitreVoyage(trim((string) $request->request->get('titre_voyage', '')));
		$voyage->setStatut(trim((string) $request->request->get('statut', '')));

		$destinationId = $request->request->getInt('destination_id');
		$destination = $destinationId > 0 ? $destinationRepository->find($destinationId) : null;
		$voyage->setDestination($destination instanceof Destination ? $destination : null);

		$dateDebut = $this->parseDate((string) $request->request->get('date_debut', ''));
		$dateFin = $this->parseDate((string) $request->request->get('date_fin', ''));

		$voyage->setDateDebut($dateDebut ?? new \DateTime('today'));
		$voyage->setDateFin($dateFin ?? new \DateTime('today'));

		$errors = [];

		if (!$dateDebut instanceof \DateTimeInterface) {
			$errors[] = 'La date de debut est obligatoire.';
		}

		if (!$dateFin instanceof \DateTimeInterface) {
			$errors[] = 'La date de fin est obligatoire.';
		}

		if ($destinationId > 0 && !$destination instanceof Destination) {
			$errors[] = 'La destination selectionnee est introuvable.';
		}

		foreach ($validator->validate($voyage) as $violation) {
			$errors[] = $violation->getMessage();
		}

		return array_values(array_unique(array_filter($errors)));
	}

	private function parseDate(string $value): ?\DateTime
	{
		$value = trim($value);

		if ($value === '') {
			return null;
		}

		$date = \DateTime::createFromFormat('Y-m-d', $value);

		return $date instanceof \DateTime ? $date : null;
	}

	/**
	 * @param Voyage[] $voyages
	 * @param Destination[] $destinations
	 * @param list<string> $formErrors
	 */
	private function renderAdminVoyagesPage(
		Request $request,
		array $voyages,
		array $destinations,
		Voyage $voyageForm,
		?Voyage $editingVoyage,
		array $formErrors
	): Response {
		$formScope = $editingVoyage instanceof Voyage
			? 'admin_voyage_update_'.$editingVoyage->getIdVoyage()
			: 'admin_voyage_create';

		$deleteNonces = [];
		foreach ($voyages as $voyage) {
			$voyageId = $voyage->getIdVoyage();

			if ($voyageId === null) {
				continue;
			}

			$deleteNonces[$voyageId] = $this->createFormNonce($request, 'admin_voyage_delete_'.$voyageId);
		}

		return $this->render('admin/voyages_back.html.twig', [
			'voyages' => $voyages,
			'destinations' => $destinations,
			'voyage_form' => $voyageForm,
			'editing_voyage' => $editingVoyage,
			'status_options' => Voyage::getAvailableStatuts(),
			'status_stats' => $this->buildStatusStats($voyages),
			'form_errors' => $formErrors,
			'form_nonce' => $this->createFormNonce($request, $formScope),
			'delete_nonces' => $deleteNonces,
		]);
	}

	/**
	 * @param Voyage[] $voyages
	 *
	 * @return array{total:int, chart_background:string, items: array<int, array{label:string,count:int,percentage:float,color:string}>}
	 */
	private function buildStatusStats(array $voyages): array
	{
		$total = count($voyages);
		$counts = [];

		foreach ($voyages as $voyage) {
			$label = trim((string) $voyage->getStatut());

			if ($label === '') {
				$label = 'Sans statut';
			}

			$counts[$label] = ($counts[$label] ?? 0) + 1;
		}

		arsort($counts);

		$palette = [
			'#c46f4b',
			'#2f7f79',
			'#ddbf8c',
			'#bf5b5b',
			'#6d8a96',
			'#8da85c',
		];

		$items = [];
		$segments = [];
		$progress = 0.0;
		$index = 0;
		$lastIndex = count($counts) - 1;

		foreach ($counts as $label => $count) {
			$color = $palette[$index % count($palette)];
			$rawPercentage = $total > 0 ? ($count / $total) * 100 : 0.0;
			$nextProgress = $index === $lastIndex ? 100.0 : $progress + $rawPercentage;

			$items[] = [
				'label' => $label,
				'count' => $count,
				'percentage' => round($rawPercentage, 1),
				'color' => $color,
			];

			$segments[] = sprintf('%s %.3f%% %.3f%%', $color, $progress, $nextProgress);
			$progress = $nextProgress;
			$index++;
		}

		return [
			'total' => $total,
			'chart_background' => $segments === []
				? 'conic-gradient(rgba(231, 220, 205, 0.92) 0 100%)'
				: 'conic-gradient('.implode(', ', $segments).')',
			'items' => $items,
		];
	}

	private function createFormNonce(Request $request, string $scope): string
	{
		$session = $request->getSession();
		$nonces = $session->get('admin_voyage_form_nonces', []);

		if (!is_array($nonces)) {
			$nonces = [];
		}

		$this->pruneExpiredNonces($nonces);

		$nonce = bin2hex(random_bytes(16));
		$nonces[$scope] ??= [];
		$nonces[$scope][$nonce] = time();

		$session->set('admin_voyage_form_nonces', $nonces);

		return $nonce;
	}

	private function consumeFormNonce(Request $request, string $scope, string $nonce): bool
	{
		if ($nonce === '') {
			return false;
		}

		$session = $request->getSession();
		$nonces = $session->get('admin_voyage_form_nonces', []);

		if (!is_array($nonces) || !isset($nonces[$scope][$nonce])) {
			return false;
		}

		unset($nonces[$scope][$nonce]);

		if ($nonces[$scope] === []) {
			unset($nonces[$scope]);
		}

		$session->set('admin_voyage_form_nonces', $nonces);

		return true;
	}

	private function markActionHandled(Request $request, string $scope): void
	{
		$session = $request->getSession();
		$handledActions = $session->get('admin_voyage_recent_actions', []);

		if (!is_array($handledActions)) {
			$handledActions = [];
		}

		$this->pruneExpiredHandledActions($handledActions);
		$handledActions[$scope] = time();

		$session->set('admin_voyage_recent_actions', $handledActions);
	}

	private function wasActionHandledRecently(Request $request, string $scope): bool
	{
		$session = $request->getSession();
		$handledActions = $session->get('admin_voyage_recent_actions', []);

		if (!is_array($handledActions)) {
			return false;
		}

		$this->pruneExpiredHandledActions($handledActions);
		$session->set('admin_voyage_recent_actions', $handledActions);

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
