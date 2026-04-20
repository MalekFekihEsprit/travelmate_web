<?php

namespace App\Controller\Admin;

use App\Entity\Budget;
use App\Repository\BudgetRepository;
use App\Repository\VoyageRepository;
use App\Service\BusinessStatisticsService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminBudgetController extends AbstractController
{
    #[Route('/admin/budget/{id}', name: 'admin_budget_show')]
    public function show(Budget $budget, BusinessStatisticsService $statsService): Response
    {
        $depenses = $budget->getDepenses();
        $totalDepenses = 0;
        $parCategorie = [];

        foreach ($depenses as $depense) {
            $montant = $depense->getMontantDepense();
            $totalDepenses += $montant;
            $cat = $depense->getCategorieDepense() ?? 'Autre';
            $parCategorie[$cat] = ($parCategorie[$cat] ?? 0) + $montant;
        }

        $montantTotal = (float) $budget->getMontantTotal();
        $reste = $montantTotal - $totalDepenses;
        $progression = $montantTotal > 0 ? round(($totalDepenses / $montantTotal) * 100, 1) : 0;
        
        // Get budget health status
        $health = $statsService->getBudgetHealth($budget);
        
        // Get statistics for this budget
        $statsByCategory = $statsService->getStatsByCategory($budget);
        $statsByPaymentMethod = $statsService->getStatsByPaymentMethod($budget);
        $statsByPeriod = $statsService->getStatsByPeriod('month', $budget);
        
        // Get all budgets for comparison
        $allBudgets = $statsService->getBudgetUtilization();

        return $this->render('admin/admin_budget/show.html.twig', [
            'budget'              => $budget,
            'depenses'            => $depenses,
            'totalDepenses'       => $totalDepenses,
            'reste'               => $reste,
            'nbDepenses'          => count($depenses),
            'progression'         => $progression,
            'parCategorie'        => $parCategorie,
            'health'              => $health,
            'statsByCategory'     => $statsByCategory,
            'statsByPaymentMethod'=> $statsByPaymentMethod,
            'statsByPeriod'       => $statsByPeriod,
            'allBudgets'          => $allBudgets,
        ]);
    }

    #[Route('/admin/budgets', name: 'admin_budget_index')]
    public function index(
        Request $request, 
        BudgetRepository $budgetRepository, 
        VoyageRepository $voyageRepository,
        PaginatorInterface $paginator,
        BusinessStatisticsService $statsService
    ): Response {
        $voyages = $voyageRepository->findAll();
        $voyageId = $request->query->get('voyage');
        $statut = $request->query->get('statut');
        
        // Build query with filters
        $qb = $budgetRepository->createQueryBuilder('b')
            ->leftJoin('b.voyage', 'v')
            ->leftJoin('b.user', 'u');
        
        if ($voyageId) {
            $qb->andWhere('b.voyage = :voyageId')
               ->setParameter('voyageId', $voyageId);
        }
        
        if ($statut) {
            $qb->andWhere('b.statutBudget = :statut')
               ->setParameter('statut', $statut);
        }
        
        // Order by most recent first
        $qb->orderBy('b.id_budget', 'DESC');
        
        // Paginate results (15 items per page)
        $budgets = $paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),  // Current page number
            $request->query->getInt('limit', 15) // Items per page
        );
        
        // Calculate global stats for KPI cards (only for current page items)
        $totalMontant = 0;
        $totalDepensesCount = 0;
        $nbActifs = 0;
        $nbTermines = 0;
        $voyageLabels = [];
        $voyageMontants = [];
        
        foreach ($budgets as $budget) {
            $totalMontant += $budget->getMontantTotal();
            $totalDepensesCount += $budget->getDepenses()->count();
            if ($budget->getStatutBudget() == 'actif') $nbActifs++;
            if ($budget->getStatutBudget() == 'termine') $nbTermines++;
            
            $voyageLabels[] = $budget->getVoyage() ? $budget->getVoyage()->getTitreVoyage() : 'Sans voyage';
            $voyageMontants[] = $budget->getMontantTotal();
        }
        
        // Get global statistics for insights
        $globalStats = [
            'totalBudgets' => $statsService->getTotalBudgets(),
            'totalExpenses' => $statsService->getTotalExpenses(),
            'averageBudget' => $statsService->getAverageBudget(),
            'topCategories' => $statsService->getTopExpenseCategories(),
            'budgetUtilization' => $statsService->getBudgetUtilization(),
        ];
        
        return $this->render('admin/admin_budget/index.html.twig', [
            'budgets'            => $budgets,
            'voyages'            => $voyages,
            'selectedVoyageId'   => $voyageId,
            'selectedStatut'     => $statut,
            'totalMontant'       => $totalMontant,
            'totalDepensesCount' => $totalDepensesCount,
            'nbActifs'           => $nbActifs,
            'nbTermines'         => $nbTermines,
            'nbInactifs'         => count($budgets) - $nbActifs - $nbTermines,
            'voyageLabels'       => $voyageLabels,
            'voyageMontants'     => $voyageMontants,
            'globalStats'        => $globalStats,
        ]);
    }
}