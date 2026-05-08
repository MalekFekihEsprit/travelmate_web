<?php

namespace App\Controller;

use App\Form\VoyageType;
use App\Repository\ActiviteRepository;
use App\Entity\Destination;
use App\Entity\Voyage;
use App\Repository\BudgetRepository;
use App\Repository\DestinationRepository;
use App\Repository\ParticipationRepository;
use App\Repository\VoyageRepository;
use App\Service\VoyageQrCodeFactory;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;
use Symfony\Component\Form\FormView;

final class VoyagesBackController extends AbstractController
{
	#[Route('/admin/voyages', name: 'app_admin_voyages', methods: ['GET'])]
	public function index(
		Request $request,
		BudgetRepository $budgetRepository,
		VoyageRepository $voyageRepository,
		DestinationRepository $destinationRepository
	): Response {
		$search = trim((string) $request->query->get('search', ''));
		$editId = $request->query->getInt('edit');
		
		$editingVoyage = null;
		$editForm = null;
		
		if ($editId > 0) {
			$editingVoyage = $voyageRepository->find($editId);
			if ($editingVoyage) {
				$editForm = $this->createForm(VoyageType::class, $editingVoyage)->createView();
			}
		}

		return $this->renderAdminVoyagesPage(
			request: $request,
			voyages: $this->findBackOfficeVoyages($voyageRepository, $search),
			destinations: $destinationRepository->findBy([], ['nom_destination' => 'ASC']),
			editingVoyage: $editingVoyage,
			editForm: $editForm,          // pass the form view
			budgetRepository: $budgetRepository
		);
	}

	#[Route('/admin/voyages/ajouter', name: 'app_admin_voyages_new', methods: ['GET', 'POST'])]
	public function new(
		Request $request,
		EntityManagerInterface $entityManager,
		DestinationRepository $destinationRepository,
		ActiviteRepository $activiteRepository
	): Response {
		$voyage = new Voyage();
		$voyage->setStatut('Planifie');

		$formScope = 'admin_voyage_form_new';
		$form = $this->createForm(VoyageType::class, $voyage);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {

			$entityManager->persist($voyage);
			$entityManager->flush();

			$this->addFlash('success', 'Le voyage a ete ajoute avec succes.');

			return $this->redirectToRoute('app_admin_voyages');
		}

		return $this->render('admin/voyage_form.html.twig', [
			'form' => $form->createView(),
			'has_destinations' => $destinationRepository->count([]) > 0,
			'has_activites' => $activiteRepository->count([]) > 0,
			'page_title' => 'Ajouter un voyage',
			'page_text' => 'Remplissez le formulaire existant pour ajouter un nouveau voyage depuis l administration.',
			'submit_label' => 'Ajouter le voyage',
		]);
	}

	#[Route('/admin/voyages/{id_voyage}', name: 'app_admin_voyages_show', requirements: ['id_voyage' => '\\d+'], methods: ['GET'])]
	public function show(
		Request $request,
		BudgetRepository $budgetRepository,
		ParticipationRepository $participationRepository,
		VoyageQrCodeFactory $voyageQrCodeFactory,
		#[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] ?Voyage $voyage = null
	): Response {
		if (!$voyage instanceof Voyage) {
			$this->addFlash('warning', 'Ce voyage est introuvable ou a deja ete supprime.');

			return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
		}

		$voyageId = $voyage->getIdVoyage() ?? 0;
		$budgetSummary = $budgetRepository->findVoyageBudgetSummaries([$voyage])[$voyageId] ?? null;
		$participants = $participationRepository->findByVoyageOrdered($voyage);
		$budgetTotalLabel = $this->formatBudgetSummary($budgetSummary);

		return $this->render('admin/voyage_show.html.twig', [
			'voyage' => $voyage,
			'participants' => $participants,
			'budget_summary' => $budgetSummary,
			'budget_total_label' => $budgetTotalLabel,
			'qr_data_uri' => $voyageQrCodeFactory->createDataUri($voyage, $budgetTotalLabel, count($participants)),
		]);
	}

	#[Route('/admin/voyages/export/pdf', name: 'app_admin_voyages_export_pdf', methods: ['GET'])]
	public function exportPdf(
		Request $request,
		BudgetRepository $budgetRepository,
		VoyageRepository $voyageRepository,
		Environment $twig
	): Response {
		$search = trim((string) $request->query->get('search', ''));
		$voyages = $this->findBackOfficeVoyages($voyageRepository, $search);
		$budgetSummaries = $budgetRepository->findVoyageBudgetSummaries($voyages);

		$options = new Options();
		$options->set('defaultFont', 'DejaVu Sans');
		$options->set('isRemoteEnabled', false);

		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($twig->render('admin/voyages_export_pdf.html.twig', [
			'budget_summaries' => $budgetSummaries,
			'voyages' => $voyages,
			'search' => $search,
			'generated_at' => new \DateTimeImmutable(),
		]));
		$dompdf->setPaper('A4', 'landscape');
		$dompdf->render();

		$response = new Response($dompdf->output());
		$disposition = $response->headers->makeDisposition(
			ResponseHeaderBag::DISPOSITION_ATTACHMENT,
			'voyages-export.pdf'
		);

		$response->headers->set('Content-Type', 'application/pdf');
		$response->headers->set('Content-Disposition', $disposition);

		return $response;
	}

	#[Route('/admin/voyages/export/excel', name: 'app_admin_voyages_export_excel', methods: ['GET'])]
	public function exportExcel(
		Request $request,
		BudgetRepository $budgetRepository,
		VoyageRepository $voyageRepository
	): StreamedResponse {
		$search = trim((string) $request->query->get('search', ''));
		$voyages = $this->findBackOfficeVoyages($voyageRepository, $search);
		$budgetSummaries = $budgetRepository->findVoyageBudgetSummaries($voyages);

		$response = new StreamedResponse(function () use ($budgetSummaries, $voyages): void {
			$handle = fopen('php://output', 'wb');

			if ($handle === false) {
				return;
			}

			fwrite($handle, "\xEF\xBB\xBF");
			fputcsv($handle, ['Titre', 'Date debut', 'Date fin', 'Statut', 'Montant total', 'Destination', 'Pays'], ';');
			foreach ($voyages as $voyage) {
				$budgetSummary = $budgetSummaries[$voyage->getIdVoyage() ?? 0] ?? null;
				fputcsv($handle, $this->buildVoyageExportRow($voyage, $budgetSummary), ';');
			}
			fclose($handle);
		});

		$response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
		$response->headers->set('Content-Disposition', 'attachment; filename="voyages-export.csv"');

		return $response;
	}

    #[Route('/admin/voyages/creer', name: 'app_admin_voyages_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        DestinationRepository $destinationRepository
    ): Response {
        $voyage = new Voyage();
        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($voyage);
            $entityManager->flush();
            $this->addFlash('success', 'Le voyage a été ajouté avec succès.');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_admin_voyages', $this->buildRedirectQuery($request));
    }

	#[Route('/admin/voyages/{id_voyage}/modifier', name: 'app_admin_voyages_update', methods: ['POST'])]
	public function update(Request $request, EntityManagerInterface $entityManager, #[MapEntity] ?Voyage $voyage = null): Response
	{
		if (!$voyage) {
			$this->addFlash('warning', 'Voyage introuvable.');
			return $this->redirectToRoute('app_admin_voyages');
		}

		$form = $this->createForm(VoyageType::class, $voyage);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$entityManager->flush();
			$this->addFlash('success', 'Voyage modifié avec succès.');
			return $this->redirectToRoute('app_admin_voyages');
		}

		// On errors, redirect back with edit parameter to reopen modal
		foreach ($form->getErrors(true) as $error) {
			$this->addFlash('error', $error->getMessage());
		}
		return $this->redirectToRoute('app_admin_voyages', ['edit' => $voyage->getIdVoyage()]);
	}

	
    #[Route('/admin/voyages/{id_voyage}/supprimer', name: 'app_admin_voyages_delete', requirements: ['id_voyage' => '\\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] ?Voyage $voyage = null
    ): Response {
        if (!$voyage) {
            $this->addFlash('warning', 'Voyage introuvable.');
            return $this->redirectToRoute('app_admin_voyages');
        }

        if (!$this->isCsrfTokenValid('delete' . $voyage->getIdVoyage(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_voyages');
        }

        // Remove related entities (same as your original delete logic)
        foreach ($voyage->getParticipations() as $participation) {
            $entityManager->remove($participation);
        }
        foreach ($voyage->getActivites() as $activite) {
            $voyage->removeActivite($activite);
        }
        foreach ($voyage->getBudgets() as $budget) {
            foreach ($budget->getDepenses() as $depense) {
                $entityManager->remove($depense);
            }
            $entityManager->remove($budget);
        }
        foreach ($voyage->getItineraires() as $itineraire) {
            foreach ($itineraire->getEtapes() as $etape) {
                $entityManager->remove($etape);
            }
            $entityManager->remove($itineraire);
        }
        foreach ($voyage->getPaiements() as $paiement) {
            $entityManager->remove($paiement);
        }

        $entityManager->remove($voyage);
        $entityManager->flush();

        $this->addFlash('success', 'Voyage supprimé.');
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
	private function buildVoyageExportRow(Voyage $voyage, ?array $budgetSummary = null): array
	{
		$destination = $voyage->getDestination();

		return [
			(string) ($voyage->getTitreVoyage() ?? ''),
			$voyage->getDateDebut()?->format('Y-m-d') ?? '-',
			$voyage->getDateFin()?->format('Y-m-d') ?? '-',
			(string) ($voyage->getStatut() ?? ''),
			$this->formatBudgetSummary($budgetSummary),
			(string) ($destination?->getNomDestination() ?? ''),
			(string) ($destination?->getPaysDestination() ?? ''),
		];
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

	/**
	 * @param Voyage[] $voyages
	 * @param Destination[] $destinations
	 */
	private function renderAdminVoyagesPage(
		Request $request,
		array $voyages,
		array $destinations,
		?Voyage $editingVoyage,
		?FormView $editForm,
		BudgetRepository $budgetRepository
	): Response {
		return $this->render('admin/voyages_back.html.twig', [
			'voyages' => $voyages,
			'budget_summaries' => $budgetRepository->findVoyageBudgetSummaries($voyages),
			'destinations' => $destinations,
			'editing_voyage' => $editingVoyage,
			'form' => $editForm,    
			'edit_form' => $editForm,                     // ← the template uses 'form'
			'status_options' => Voyage::getAvailableStatuts(),
			'status_stats' => $this->buildStatusStats($voyages),
			'form_errors' => []
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

}
