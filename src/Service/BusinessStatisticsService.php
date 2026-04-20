<?php

namespace App\Service;

use App\Entity\Budget;
use App\Entity\Depense;
use Doctrine\ORM\EntityManagerInterface;

class BusinessStatisticsService
{
    public function __construct(private EntityManagerInterface $em) {}
    
    // Get budget health status
    public function getBudgetHealth(Budget $budget): array
    {
        $totalSpent = 0;
        foreach ($budget->getDepenses() as $depense) {
            $totalSpent += $depense->getMontantDepense();
        }
        
        $percentage = $budget->getMontantTotal() > 0 
            ? ($totalSpent / $budget->getMontantTotal()) * 100 
            : 0;
        
        $status = 'healthy';
        if ($percentage >= 100) {
            $status = 'exceeded';
        } elseif ($percentage >= 90) {
            $status = 'critical';
        } elseif ($percentage >= 75) {
            $status = 'warning';
        }
        
        return [
            'total_budget' => $budget->getMontantTotal(),
            'total_spent' => $totalSpent,
            'remaining' => $budget->getMontantTotal() - $totalSpent,
            'percentage' => round($percentage, 2),
            'status' => $status,
            'needs_alert' => $percentage >= 80
        ];
    }
    
    // Statistics by budget status
    public function getStatsByStatus(): array
    {
        return $this->em->createQueryBuilder()
            ->select('b.statut_budget, COUNT(b.id_budget) as count, SUM(b.montant_total) as total')
            ->from(Budget::class, 'b')
            ->groupBy('b.statut_budget')
            ->getQuery()
            ->getResult();
    }
    
    // Statistics by voyage
    public function getStatsByVoyage(): array
    {
        return $this->em->createQueryBuilder()
            ->select('v.titre_voyage, COUNT(b.id_budget) as budget_count, SUM(b.montant_total) as total_budget')
            ->from(Budget::class, 'b')
            ->leftJoin('b.voyage', 'v')
            ->groupBy('v.id_voyage')
            ->getQuery()
            ->getResult();
    }
    
    // Statistics by expense category
    public function getStatsByCategory(Budget $budget = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('d.categorie_depense, SUM(d.montant_depense) as total, COUNT(d.id_depense) as count')
            ->from(Depense::class, 'd');
        
        if ($budget) {
            $qb->where('d.budget = :budget')
               ->setParameter('budget', $budget);
        }
        
        return $qb->groupBy('d.categorie_depense')
                  ->getQuery()
                  ->getResult();
    }
    
    // Statistics by payment method
    public function getStatsByPaymentMethod(Budget $budget = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('d.type_paiement, SUM(d.montant_depense) as total, COUNT(d.id_depense) as count')
            ->from(Depense::class, 'd');
        
        if ($budget) {
            $qb->where('d.budget = :budget')
               ->setParameter('budget', $budget);
        }
        
        return $qb->groupBy('d.type_paiement')
                  ->getQuery()
                  ->getResult();
    }
    
    // Statistics by time period (monthly)
    public function getStatsByPeriod(string $period = 'month', Budget $budget = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('SUBSTRING(d.date_creation, 1, 7) as period, SUM(d.montant_depense) as total')
            ->from(Depense::class, 'd');
        
        if ($budget) {
            $qb->where('d.budget = :budget')
               ->setParameter('budget', $budget);
        }
        
        return $qb->groupBy('period')
                  ->orderBy('period', 'DESC')
                  ->setMaxResults(12)
                  ->getQuery()
                  ->getResult();
    }
    
    // Top expenses by amount
    public function getTopExpenses(int $limit = 10): array
    {
        return $this->em->createQueryBuilder()
            ->select('d.libelle_depense, d.montant_depense, d.categorie_depense, b.libelle_budget')
            ->from(Depense::class, 'd')
            ->leftJoin('d.budget', 'b')
            ->orderBy('d.montant_depense', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    
    // Budget utilization rate (sorted by highest usage)
    public function getBudgetUtilization(): array
    {
        $budgets = $this->em->getRepository(Budget::class)->findAll();
        $utilization = [];
        
        foreach ($budgets as $budget) {
            $totalSpent = 0;
            foreach ($budget->getDepenses() as $depense) {
                $totalSpent += $depense->getMontantDepense();
            }
            
            $percentage = $budget->getMontantTotal() > 0 
                ? ($totalSpent / $budget->getMontantTotal()) * 100 
                : 0;
            
            $utilization[] = [
                'id' => $budget->getIdBudget(),
                'budget' => $budget->getLibelleBudget(),
                'total' => $budget->getMontantTotal(),
                'spent' => $totalSpent,
                'remaining' => $budget->getMontantTotal() - $totalSpent,
                'percentage' => round($percentage, 2),
                'status' => $percentage >= 90 ? 'critical' : ($percentage >= 75 ? 'warning' : 'good')
            ];
        }
        
        // Sort by percentage (highest first)
        usort($utilization, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
        
        return $utilization;
    }
    
    // Get total number of budgets
    public function getTotalBudgets(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id_budget)')
            ->from(Budget::class, 'b')
            ->getQuery()
            ->getSingleScalarResult();
    }
    
    // Get total expenses amount across all budgets
    public function getTotalExpenses(): float
    {
        return (float) $this->em->createQueryBuilder()
            ->select('SUM(d.montant_depense)')
            ->from(Depense::class, 'd')
            ->getQuery()
            ->getSingleScalarResult();
    }
    
    // Get average budget amount
    public function getAverageBudget(): float
    {
        return (float) $this->em->createQueryBuilder()
            ->select('AVG(b.montant_total)')
            ->from(Budget::class, 'b')
            ->getQuery()
            ->getSingleScalarResult();
    }
    
    // Get top expense categories across all budgets
    public function getTopExpenseCategories(int $limit = 5): array
    {
        return $this->em->createQueryBuilder()
            ->select('d.categorie_depense, SUM(d.montant_depense) as total')
            ->from(Depense::class, 'd')
            ->groupBy('d.categorie_depense')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    
    // Get monthly spending trend
    public function getMonthlySpendingTrend(int $months = 6): array
    {
        return $this->em->createQueryBuilder()
            ->select('SUBSTRING(d.date_creation, 1, 7) as month, SUM(d.montant_depense) as total')
            ->from(Depense::class, 'd')
            ->groupBy('month')
            ->orderBy('month', 'DESC')
            ->setMaxResults($months)
            ->getQuery()
            ->getResult();
    }
}