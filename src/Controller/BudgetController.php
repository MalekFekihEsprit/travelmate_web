<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\Depense;
use App\Form\BudgetType;
use App\Repository\BudgetRepository;
use App\Repository\VoyageRepository;
use App\Repository\DepenseRepository;
use App\Service\NotificationService;
use App\Service\CurrencyService;
use App\Service\EstimationService;
use App\Service\InflationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/budget')]
class BudgetController extends AbstractController
{
    /* ════════════════════════════════════════════════════════
       BUDGET CRUD
    ════════════════════════════════════════════════════════ */

    #[Route('/', name: 'app_budget_index', methods: ['GET'])]
    public function index(BudgetRepository $budgetRepository): Response
    {
        return $this->render('budget/index.html.twig', [
            'budgets' => $budgetRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_budget_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, VoyageRepository $voyageRepository): Response
    {
        $budget = new Budget();
        if ($this->getUser()) {
            $budget->setUser($this->getUser());
        }
        $form = $this->createForm(BudgetType::class, $budget);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($budget);
            $entityManager->flush();
            $this->addFlash('success', 'Budget créé avec succès !');
            return $this->redirectToRoute('app_budget_index');
        }
        return $this->render('budget/new.html.twig', [
            'budget'  => $budget,
            'form'    => $form,
            'voyages' => $voyageRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_budget_show', methods: ['GET'])]
    public function show(
        Budget $budget,
        #[Autowire('%env(NTFY_TOPIC)%')] string $ntfyTopic
    ): Response {
        return $this->render('budget/show.html.twig', [
            'budget'     => $budget,
            'ntfy_topic' => $ntfyTopic,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_budget_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Budget $budget, EntityManagerInterface $entityManager, VoyageRepository $voyageRepository, CurrencyService $currencyService): Response
    {
        $oldDevise = $budget->getDeviseBudget();
        $oldMontantTotal = $budget->getMontantTotal();
        
        $form = $this->createForm(BudgetType::class, $budget);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $newDevise = $budget->getDeviseBudget();
            
            if ($oldDevise !== $newDevise && $oldDevise && $newDevise) {
                try {
                    if ($oldDevise !== $newDevise) {
                        $converted = $currencyService->convert($oldDevise, $newDevise, $oldMontantTotal);
                        $budget->setMontantTotal($converted['converted']);
                    }
                    
                    $convertedCount = 0;
                    foreach ($budget->getDepenses() as $depense) {
                        $oldDepDevise = $depense->getDeviseDepense() ?? $oldDevise;
                        if ($oldDepDevise !== $newDevise) {
                            try {
                                $convertedDep = $currencyService->convert($oldDepDevise, $newDevise, $depense->getMontantDepense());
                                $depense->setMontantDepense($convertedDep['converted']);
                                $depense->setDeviseDepense($newDevise);
                                $convertedCount++;
                            } catch (\Exception $e) {
                                // Ignorer
                            }
                        }
                    }
                    
                    $this->addFlash('success', sprintf(
                        'Budget modifié et %d dépense(s) convertie(s) de %s vers %s !',
                        $convertedCount,
                        $oldDevise,
                        $newDevise
                    ));
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Budget modifié mais la conversion des devises a échoué.');
                }
            } else {
                $this->addFlash('success', 'Budget modifié avec succès !');
            }
            
            $entityManager->flush();
            return $this->redirectToRoute('app_budget_index');
        }
        
        return $this->render('budget/edit.html.twig', [
            'budget'  => $budget,
            'form'    => $form,
            'voyages' => $voyageRepository->findAll(),
            'currencies' => $currencyService->getAvailableCurrencies(),
        ]);
    }

    #[Route('/{id}/convert-depenses', name: 'app_budget_convert_depenses', methods: ['POST'])]
    public function convertDepenses(
        Budget $budget,
        Request $request,
        CurrencyService $currencyService,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $targetDevise = strtoupper($request->request->get('target_devise'));
        $currentDevise = $budget->getDeviseBudget();
        
        if (!$targetDevise || $targetDevise === $currentDevise) {
            return new JsonResponse(['error' => 'Devise invalide ou identique'], 400);
        }
        
        $convertedCount = 0;
        
        try {
            $convertedTotal = $currencyService->convert($currentDevise, $targetDevise, $budget->getMontantTotal());
            $budget->setMontantTotal($convertedTotal['converted']);
            $budget->setDeviseBudget($targetDevise);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Impossible de convertir le budget: ' . $e->getMessage()], 500);
        }
        
        foreach ($budget->getDepenses() as $depense) {
            $depDevise = $depense->getDeviseDepense() ?? $currentDevise;
            try {
                $conv = $currencyService->convert($depDevise, $targetDevise, $depense->getMontantDepense());
                $depense->setMontantDepense($conv['converted']);
                $depense->setDeviseDepense($targetDevise);
                $convertedCount++;
            } catch (\Exception $e) {
                // Ignorer
            }
        }
        
        $entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => sprintf('%d dépense(s) convertie(s) vers %s', $convertedCount, $targetDevise),
            'new_devise' => $targetDevise,
            'new_budget_total' => $budget->getMontantTotal(),
            'converted_count' => $convertedCount
        ]);
    }

    #[Route('/{id}', name: 'app_budget_delete', methods: ['POST'])]
    public function delete(Request $request, Budget $budget, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $budget->getIdBudget(), $request->request->get('_token'))) {
            $entityManager->remove($budget);
            $entityManager->flush();
            $this->addFlash('success', 'Budget supprimé avec succès !');
        }
        return $this->redirectToRoute('app_budget_index');
    }

    /* ════════════════════════════════════════════════════════
       DÉPENSE CRUD
    ════════════════════════════════════════════════════════ */

    #[Route('/{budgetId}/depense/new', name: 'app_depense_new', methods: ['POST'])]
    public function newDepense(
        int $budgetId,
        Request $request,
        EntityManagerInterface $entityManager,
        BudgetRepository $budgetRepository,
        CurrencyService $currencyService
    ): Response {
        $budget = $budgetRepository->find($budgetId);
        if (!$budget) {
            $this->addFlash('error', 'Budget introuvable.');
            return $this->redirectToRoute('app_budget_index');
        }
        if (!$this->isCsrfTokenValid('depense_new', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_budget_show', ['id' => $budgetId]);
        }
        
        $depense = new Depense();
        $depense->setBudget($budget);
        
        $errors = $this->hydrateDepense($depense, $request);
        if (!empty($errors)) {
            foreach ($errors as $err) { $this->addFlash('error', $err); }
            return $this->redirectToRoute('app_budget_show', ['id' => $budgetId]);
        }
        
        $depenseDevise = $depense->getDeviseDepense();
        $budgetDevise = $budget->getDeviseBudget();
        
        if ($depenseDevise && $depenseDevise !== $budgetDevise) {
            try {
                $converted = $currencyService->convert($depenseDevise, $budgetDevise, $depense->getMontantDepense());
                $depense->setMontantDepense($converted['converted']);
                $depense->setDeviseDepense($budgetDevise);
                $this->addFlash('info', sprintf('Montant converti de %s vers %s', $depenseDevise, $budgetDevise));
            } catch (\Exception $e) {
                $this->addFlash('warning', 'La devise ' . $depenseDevise . ' n\'a pas pu être convertie.');
            }
        }
        
        $entityManager->persist($depense);
        $entityManager->flush();
        
        $this->addFlash('success', 'Dépense ajoutée avec succès !');
        return $this->redirectToRoute('app_budget_show', ['id' => $budgetId]);
    }

    #[Route('/depense/{id}/edit', name: 'app_depense_edit', methods: ['POST'])]
    public function editDepense(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        DepenseRepository $depenseRepository,
        CurrencyService $currencyService
    ): Response {
        $depense = $depenseRepository->find($id);
        if (!$depense) {
            $this->addFlash('error', 'Dépense introuvable.');
            return $this->redirectToRoute('app_budget_index');
        }
        
        $budget = $depense->getBudget();
        $budgetId = $budget?->getIdBudget();
        $oldDevise = $depense->getDeviseDepense() ?? $budget->getDeviseBudget();
        $oldMontant = $depense->getMontantDepense();
        
        $errors = $this->hydrateDepense($depense, $request);
        if (!empty($errors)) {
            foreach ($errors as $err) { $this->addFlash('error', $err); }
            return $this->redirectToRoute('app_budget_show', ['id' => $budgetId]);
        }
        
        $newDevise = $depense->getDeviseDepense();
        $budgetDevise = $budget->getDeviseBudget();
        
        if ($newDevise && $newDevise !== $budgetDevise && $oldMontant > 0) {
            try {
                $converted = $currencyService->convert($newDevise, $budgetDevise, $depense->getMontantDepense());
                $depense->setMontantDepense($converted['converted']);
                $depense->setDeviseDepense($budgetDevise);
                $this->addFlash('info', sprintf('Montant converti de %s vers %s', $newDevise, $budgetDevise));
            } catch (\Exception $e) {
                $this->addFlash('warning', 'La devise ' . $newDevise . ' n\'a pas pu être convertie.');
            }
        } elseif (!$newDevise) {
            $depense->setDeviseDepense($budgetDevise);
        }
        
        $entityManager->flush();
        $this->addFlash('success', 'Dépense modifiée avec succès !');
        return $this->redirectToRoute('app_budget_show', ['id' => $budgetId]);
    }

    #[Route('/depense/{id}/delete', name: 'app_depense_delete', methods: ['POST'])]
    public function deleteDepense(
        int $id,
        EntityManagerInterface $entityManager,
        DepenseRepository $depenseRepository
    ): Response {
        $depense = $depenseRepository->find($id);
        if (!$depense) {
            $this->addFlash('error', 'Dépense introuvable.');
            return $this->redirectToRoute('app_budget_index');
        }
        $budgetId = $depense->getBudget()?->getIdBudget();
        $entityManager->remove($depense);
        $entityManager->flush();
        $this->addFlash('success', 'Dépense supprimée avec succès !');
        return $this->redirectToRoute('app_budget_show', ['id' => $budgetId]);
    }

    /* ════════════════════════════════════════════════════════
       AI / API ENDPOINTS
    ════════════════════════════════════════════════════════ */

    #[Route('/{id}/ai/notification', name: 'app_budget_ai_notification', methods: ['GET'])]
    public function aiNotification(Budget $budget, NotificationService $notif): JsonResponse
    {
        $totalSpent = 0;
        foreach ($budget->getDepenses() as $d) {
            $totalSpent += $d->getMontantDepense();
        }

        $montantTotal = $budget->getMontantTotal();
        $pct          = $montantTotal > 0 ? round(($totalSpent / $montantTotal) * 100, 1) : 0;
        $restant      = $montantTotal - $totalSpent;
        $devise       = $budget->getDeviseBudget() ?? 'TND';
        $isOver       = $restant < 0;

        if ($isOver) {
            $message = '🚨 Budget dépassé de '
                . number_format(abs($restant), 2, ',', ' ') . ' ' . $devise
                . '. Revoyez vos dépenses immédiatement.';
        } else {
            $message = '⚠️ ' . $pct . '% consommé — il reste '
                . number_format($restant, 2, ',', ' ') . ' ' . $devise
                . '. Pensez à surveiller vos dépenses.';
        }

        $sent = $notif->sendBudgetAlert(
            $budget->getLibelleBudget(), $pct, $restant, $devise
        );

        return new JsonResponse(['message' => $message, 'ntfy_sent' => $sent]);
    }

    #[Route('/ai/convert', name: 'app_budget_ai_convert', methods: ['POST'])]
    public function aiConvert(Request $request, CurrencyService $currency): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $from   = strtoupper($data['from']   ?? 'TND');
        $to     = strtoupper($data['to']     ?? 'EUR');
        $montant = (float) ($data['montant'] ?? 0);

        if ($montant <= 0) {
            return new JsonResponse(['result' => '⚠ Montant invalide.'], 400);
        }

        try {
            $result = $currency->convert($from, $to, $montant);
            return new JsonResponse([
                'result' => number_format($result['converted'], 2, ',', ' ') . ' ' . $result['to']
                    . ' (taux : 1 ' . $result['from'] . ' = ' . $result['rate'] . ' ' . $result['to'] . ')',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['result' => '⚠ Devise non supportée ou API indisponible.'], 500);
        }
    }

    #[Route('/ai/inflation-country', name: 'app_budget_ai_inflation_country', methods: ['GET'])]
    public function aiInflationCountry(Request $request, InflationService $inflation): JsonResponse
    {
        $country = strtoupper($request->query->get('country', 'TN'));

        try {
            $latest     = $inflation->getLatestInflation($country);
            $historical = array_values($inflation->getHistoricalInflation($country, 2));

            $current  = $latest['rate'];
            $previous = count($historical) >= 2 ? $historical[1]['value'] : null;
            $delta    = ($current !== null && $previous !== null)
                ? round($current - $previous, 2)
                : null;

            return new JsonResponse([
                'country'  => $latest['country'],
                'year'     => $latest['year'],
                'rate'     => $current,
                'previous' => $previous,
                'delta'    => $delta,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Inflation basée sur la devise du budget
     */
    #[Route('/{id}/ai/inflation-by-currency', name: 'app_budget_ai_inflation_currency', methods: ['GET'])]
    public function aiInflationByCurrency(Budget $budget, InflationService $inflation): JsonResponse
    {
        $currency = $budget->getDeviseBudget() ?? 'TND';
        
        $inflationData = $inflation->getInflationByCurrency($currency);
        
        if (!$inflationData['success']) {
            return new JsonResponse([
                'error' => $inflationData['error'],
                'fallback_rate' => $inflationData['fallback_rate'] ?? 5.0
            ], 400);
        }
        
        $budgetAmount = $budget->getMontantTotal();
        $impact = $this->calculateBudgetImpact($budgetAmount, $inflationData['rate']);
        $recommendations = $this->getBudgetRecommendations($budget, $inflationData['rate']);
        
        return new JsonResponse([
            'success' => true,
            'budget_id' => $budget->getIdBudget(),
            'budget_name' => $budget->getLibelleBudget(),
            'currency' => $inflationData['currency'],
            'currency_name' => $inflationData['currency_name'],
            'country' => $inflationData['country_name'],
            'country_code' => $inflationData['country_code'],
            'inflation_rate' => $inflationData['rate'],
            'year' => $inflationData['year'],
            'source' => $inflationData['source'],
            'is_estimated' => $inflationData['is_estimated'],
            'advice' => $inflationData['advice'],
            'budget_impact' => $impact,
            'recommendations' => $recommendations
        ]);
    }

    #[Route('/ai/estimate', name: 'app_budget_ai_estimate', methods: ['POST'])]
    public function aiEstimate(Request $request, EstimationService $estim): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $result = $estim->estimateCost(
            $data['destination'] ?? '',
            (int) ($data['jours']     ?? 3),
            (int) ($data['personnes'] ?? 1),
            $data['devise'] ?? 'TND'
        );
        return new JsonResponse(['result' => $result]);
    }

    #[Route('/ai/inflation', name: 'app_budget_ai_inflation', methods: ['POST'])]
    public function aiInflation(Request $request, InflationService $infl): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $result = $infl->adjustForInflation(
            (float) ($data['montant'] ?? 0),
            $data['de']     ?? '2020',
            $data['a']      ?? (string) date('Y'),
            $data['devise'] ?? 'TND'
        );
        return new JsonResponse(['result' => $result]);
    }

    /* ════════════════════════════════════════════════════════
       HELPERS
    ════════════════════════════════════════════════════════ */

    private function calculateBudgetImpact(float $budget, float $inflationRate): array
    {
        $purchasingPowerLoss = $budget * ($inflationRate / 100);
        $adjustedBudget = $budget + $purchasingPowerLoss;
        
        return [
            'original_budget' => round($budget, 2),
            'inflation_rate' => $inflationRate,
            'purchasing_power_loss' => round($purchasingPowerLoss, 2),
            'adjusted_budget_needed' => round($adjustedBudget, 2),
            'percentage_increase_needed' => round($inflationRate, 1)
        ];
    }
    
    private function getBudgetRecommendations(Budget $budget, float $inflationRate): array
    {
        $totalDepenses = 0;
        foreach ($budget->getDepenses() as $depense) {
            $totalDepenses += $depense->getMontantDepense();
        }
        
        $remaining = $budget->getMontantTotal() - $totalDepenses;
        $inflationImpact = $remaining * ($inflationRate / 100);
        $recommendations = [];
        
        if ($inflationRate > 10) {
            $recommendations[] = "⚠️ Inflation critique : Réduisez les dépenses non essentielles de 30%";
            $recommendations[] = "💡 Privilégiez les réservations à l'avance pour bloquer les prix";
            $recommendations[] = "🏦 Utilisez des cartes sans frais de conversion";
        } elseif ($inflationRate > 5) {
            $recommendations[] = "📈 Inflation élevée : Augmentez votre budget quotidien de 15-20%";
            $recommendations[] = "💡 Comparez les prix avant chaque achat important";
        } elseif ($inflationRate > 3) {
            $recommendations[] = "📊 Inflation modérée : Prévoyez une marge de 10% sur votre budget";
            $recommendations[] = "💡 Utilisez des applications de suivi des dépenses";
        } else {
            $recommendations[] = "✅ Inflation maîtrisée : Votre budget devrait être suffisant";
            $recommendations[] = "💡 Profitez-en pour épargner sur les extras";
        }
        
        if ($inflationImpact > 0 && $remaining > 0) {
            $recommendations[] = "📉 L'inflation pourrait réduire votre pouvoir d'achat restant de " . round($inflationImpact, 2) . " " . ($budget->getDeviseBudget() ?? 'TND');
        }
        
        return $recommendations;
    }

    private function hydrateDepense(Depense $depense, Request $request): array
    {
        $errors = [];

        $libelle = trim($request->request->get('libelle_depense', ''));
        if ($libelle === '') { $errors[] = 'Le libellé est requis.'; }
        elseif (mb_strlen($libelle) > 60) { $errors[] = 'Le libellé ne doit pas dépasser 60 caractères.'; }
        else { $depense->setLibelleDepense($libelle); }

        $montant = (float) $request->request->get('montant_depense', 0);
        if ($montant <= 0) { $errors[] = 'Le montant doit être supérieur à 0.'; }
        else { $depense->setMontantDepense($montant); }

        $categorie = trim($request->request->get('categorie_depense', ''));
        $validCats = ['Hébergement', 'Transport', 'Restauration', 'Loisirs', 'Achats', 'Santé', 'Autre'];
        if (!in_array($categorie, $validCats, true)) { $errors[] = 'Catégorie invalide.'; }
        else { $depense->setCategorieDepense($categorie); }

        $desc = trim($request->request->get('description_depense', ''));
        if ($desc === '') { $errors[] = 'La description est requise.'; }
        elseif (mb_strlen($desc) > 255) { $errors[] = 'La description ne doit pas dépasser 255 caractères.'; }
        else { $depense->setDescriptionDepense($desc); }

        $devise = trim($request->request->get('devise_depense', ''));
        $depense->setDeviseDepense($devise ?: null);

        $type       = trim($request->request->get('type_paiement', ''));
        $validTypes = ['Espèces', 'Carte bancaire', 'Virement', 'Mobile Pay', 'Autre'];
        if (!in_array($type, $validTypes, true)) { $errors[] = 'Type de paiement invalide.'; }
        else { $depense->setTypePaiement($type); }

        $dateStr = trim($request->request->get('date_creation', ''));
        if ($dateStr === '') { $errors[] = 'La date est requise.'; }
        else {
            $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
            if (!$date) { $errors[] = 'Format de date invalide.'; }
            else { $depense->setDateCreation($date); }
        }

        return $errors;
    }
}