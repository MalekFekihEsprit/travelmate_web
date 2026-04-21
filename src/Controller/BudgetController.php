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

    /* ════════════════════════════════════════════════════════
       DÉPENSE CRUD  (must be declared BEFORE /{id} wildcard routes)
    ════════════════════════════════════════════════════════ */

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
       BUDGET SHOW / EDIT / DELETE  (wildcard /{id} routes — keep AFTER specific routes)
    ════════════════════════════════════════════════════════ */

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
       DÉPENSE CRUD — new
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

    /* ════════════════════════════════════════════════════════
       AI EXTERNE — OpenRouter (Mistral / Llama)
    ════════════════════════════════════════════════════════ */

    private function callOpenRouter(string $prompt, string $apiKey): string
    {
        // Free models on OpenRouter — tried in order until one responds
        $models = [
            'meta-llama/llama-3.3-70b-instruct:free',
            'google/gemma-3-27b-it:free',
            'google/gemma-3-12b-it:free',
            'meta-llama/llama-3.2-3b-instruct:free',
        ];

        $lastError = 'No model tried';

        foreach ($models as $model) {
            $payload = json_encode([
                'model'    => $model,
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => 'Tu es un assistant de gestion de budget voyage. Réponds en français, max 120 mots, sois précis et concis. ' . $prompt,
                    ],
                ],
                'max_tokens'  => 350,
                'temperature' => 0.5,
            ]);

            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                    'HTTP-Referer: https://travelmate.local',
                    'X-Title: TravelMate',
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) { $lastError = 'cURL: ' . $curlErr; continue; }

            $data = json_decode($response, true);

            // Skip unavailable models
            if ($httpCode === 404) {
                $lastError = 'Model not found: ' . $model;
                continue;
            }

            // Provider error on this model — try next
            if (isset($data['error'])) {
                $lastError = $data['error']['message'] ?? ('Error on ' . $model);
                continue;
            }

            $content = $data['choices'][0]['message']['content'] ?? null;
            if ($content !== null && trim($content) !== '') {
                return trim($content);
            }

            $lastError = 'Empty response from ' . $model;
        }

        throw new \RuntimeException('All models failed. Last: ' . $lastError);
    }

    #[Route('/{id}/ai/estimate', name: 'app_budget_ai_estimate_trip', methods: ['POST'])]
    public function aiEstimateTrip(
        Budget $budget,
        Request $request,
        #[Autowire('%env(OPENROUTER_API_KEY)%')] string $apiKey
    ): JsonResponse {
        $data    = json_decode($request->getContent(), true) ?? [];
        $dest    = htmlspecialchars($data['destination'] ?? 'destination inconnue', ENT_QUOTES);
        $days    = (int)($data['days'] ?? 7);
        $people  = (int)($data['people'] ?? 2);
        $devise  = $budget->getDeviseBudget() ?? 'TND';
        $budgetTotal = $budget->getMontantTotal();

        $prompt = "Voyage à {$dest}, {$days} jours, {$people} personne(s). Budget disponible : {$budgetTotal} {$devise}. "
                . "Donne : 1) coût total estimé 2) coût par jour par personne 3) budget suffisant ou non 4) un conseil pratique.";

        try {
            $result = $this->callOpenRouter($prompt, $apiKey);
            return new JsonResponse(['result' => $result]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/ai/predict-overrun', name: 'app_budget_ai_predict_overrun', methods: ['POST'])]
    public function aiPredictOverrun(
        Budget $budget,
        Request $request,
        #[Autowire('%env(OPENROUTER_API_KEY)%')] string $apiKey
    ): JsonResponse {
        $data      = json_decode($request->getContent(), true) ?? [];
        $daysLeft  = (int)($data['days_left'] ?? 0);
        $devise    = $budget->getDeviseBudget() ?? 'TND';

        $totalSpent = 0;
        $depenses   = [];
        foreach ($budget->getDepenses() as $d) {
            $totalSpent += $d->getMontantDepense();
            $depenses[]  = $d->getCategorieDepense() . ':' . round($d->getMontantDepense(), 0);
        }
        $restant = $budget->getMontantTotal() - $totalSpent;
        $pct     = $budget->getMontantTotal() > 0
            ? round(($totalSpent / $budget->getMontantTotal()) * 100, 1) : 0;

        $prompt = "Budget voyage : {$budget->getMontantTotal()} {$devise}. "
                . "Dépensé : {$totalSpent} {$devise} ({$pct}%). "
                . "Restant : {$restant} {$devise}. "
                . "Jours restants : {$daysLeft}. "
                . "Catégories : " . implode(', ', array_slice($depenses, 0, 5)) . ". "
                . "Risque de dépassement (Faible/Modéré/Élevé/Critique) ? Projection finale ? Un conseil.";

        try {
            $result = $this->callOpenRouter($prompt, $apiKey);
            return new JsonResponse(['result' => $result]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/ai/recommend-trip', name: 'app_budget_ai_recommend_trip', methods: ['POST'])]
    public function aiRecommendTrip(
        Budget $budget,
        Request $request,
        #[Autowire('%env(OPENROUTER_API_KEY)%')] string $apiKey
    ): JsonResponse {
        $data   = json_decode($request->getContent(), true) ?? [];
        $style  = htmlspecialchars($data['style'] ?? 'plage', ENT_QUOTES);
        $devise = $budget->getDeviseBudget() ?? 'TND';

        $totalSpent = 0;
        foreach ($budget->getDepenses() as $d) $totalSpent += $d->getMontantDepense();
        $restant = $budget->getMontantTotal() - $totalSpent;
        $budgetDispo = $restant > 100 ? $restant : $budget->getMontantTotal();

        $prompt = "Avec un budget de {$budgetDispo} {$devise} et un style de voyage '{$style}', "
                . "recommande UN voyage complet. Inclus : destination précise, durée idéale, "
                . "hébergement suggéré, 3 activités incontournables, et estimation du coût total. "
                . "Sois spécifique et pratique.";

        try {
            $result = $this->callOpenRouter($prompt, $apiKey);
            return new JsonResponse(['result' => $result]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /* ════════════════════════════════════════════════════════
       GAMIFICATION — computed from Budget + Depense only
    ════════════════════════════════════════════════════════ */

    #[Route('/{id}/gamification', name: 'app_budget_gamification', methods: ['GET'])]
    public function gamification(Budget $budget, BudgetRepository $budgetRepository): JsonResponse
    {
        $depenses    = $budget->getDepenses()->toArray();
        $totalBudget = (float) $budget->getMontantTotal();
        $devise      = $budget->getDeviseBudget() ?? 'TND';

        // ── Total spent ──────────────────────────────────────────
        $totalSpent = array_sum(array_map(fn($d) => (float)$d->getMontantDepense(), $depenses));
        $restant    = $totalBudget - $totalSpent;
        $pct        = $totalBudget > 0 ? ($totalSpent / $totalBudget) * 100 : 0;

        // ── Daily budget (assume 30-day budget by default) ───────
        // Infer duration from first/last expense date
        $dates = array_filter(array_map(fn($d) => $d->getDateCreation(), $depenses));
        usort($dates, fn($a, $b) => $a <=> $b);
        $firstDate = $dates[0] ?? new \DateTime();
        $lastDate  = $dates[count($dates) - 1] ?? new \DateTime();
        $daySpan   = max(1, (int)$firstDate->diff($lastDate)->days + 1);
        $dailyBudget = $totalBudget / max($daySpan, 1);

        // ── Group spending by day ────────────────────────────────
        $byDay = [];
        foreach ($depenses as $d) {
            $day = $d->getDateCreation()?->format('Y-m-d') ?? date('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + (float)$d->getMontantDepense();
        }
        ksort($byDay);

        // ── Savings streak (consecutive days under daily budget) ─
        $streak = 0;
        $maxStreak = 0;
        foreach ($byDay as $spent) {
            if ($spent <= $dailyBudget) {
                $streak++;
                $maxStreak = max($maxStreak, $streak);
            } else {
                $streak = 0;
            }
        }
        // Current streak = streak at end of sorted days
        $currentStreak = $streak;

        // ── Achievements ─────────────────────────────────────────
        $allBudgets = $budgetRepository->findAll();
        $achievements = [];

        // 1. First budget created
        if (count($allBudgets) >= 1) {
            $achievements[] = [
                'id'     => 'first_budget',
                'title'  => 'Premier budget',
                'desc'   => 'Vous avez créé votre premier budget',
                'icon'   => 'star',
                'color'  => '#f59e0b',
                'earned' => true,
            ];
        }

        // 2. Under budget (budget not exceeded)
        if ($restant >= 0) {
            $achievements[] = [
                'id'     => 'under_budget',
                'title'  => 'Budget maîtrisé',
                'desc'   => 'Vous restez dans les limites du budget',
                'icon'   => 'shield',
                'color'  => '#10b981',
                'earned' => true,
            ];
        }

        // 3. Savings streak >= 3 days
        if ($maxStreak >= 3) {
            $achievements[] = [
                'id'     => 'streak_3',
                'title'  => 'Série de 3 jours',
                'desc'   => "{$maxStreak} jours consécutifs sous le budget journalier",
                'icon'   => 'fire',
                'color'  => '#f97316',
                'earned' => true,
            ];
        } else {
            $achievements[] = [
                'id'     => 'streak_3',
                'title'  => 'Série de 3 jours',
                'desc'   => 'Restez sous le budget 3 jours de suite',
                'icon'   => 'fire',
                'color'  => '#9ca3af',
                'earned' => false,
                'progress' => $maxStreak,
                'target'   => 3,
            ];
        }

        // 4. Saved 20%+ of budget
        $savedPct = $totalBudget > 0 ? ($restant / $totalBudget) * 100 : 0;
        if ($savedPct >= 20) {
            $achievements[] = [
                'id'     => 'saver_20',
                'title'  => 'Économe',
                'desc'   => 'Économisé plus de 20% du budget',
                'icon'   => 'piggy',
                'color'  => '#3b82f6',
                'earned' => true,
            ];
        } else {
            $achievements[] = [
                'id'     => 'saver_20',
                'title'  => 'Économe',
                'desc'   => 'Économisez 20% du budget total',
                'icon'   => 'piggy',
                'color'  => '#9ca3af',
                'earned' => false,
                'progress' => max(0, round($savedPct)),
                'target'   => 20,
            ];
        }

        // 5. 10+ expenses logged
        if (count($depenses) >= 10) {
            $achievements[] = [
                'id'     => 'tracker_10',
                'title'  => 'Suivi rigoureux',
                'desc'   => '10 dépenses enregistrées',
                'icon'   => 'list',
                'color'  => '#8b5cf6',
                'earned' => true,
            ];
        } else {
            $achievements[] = [
                'id'     => 'tracker_10',
                'title'  => 'Suivi rigoureux',
                'desc'   => 'Enregistrez 10 dépenses',
                'icon'   => 'list',
                'color'  => '#9ca3af',
                'earned' => false,
                'progress' => count($depenses),
                'target'   => 10,
            ];
        }

        // 6. Multi-budget user (3+ budgets)
        if (count($allBudgets) >= 3) {
            $achievements[] = [
                'id'     => 'multi_budget',
                'title'  => 'Voyageur expérimenté',
                'desc'   => '3 budgets créés',
                'icon'   => 'globe',
                'color'  => '#2f7f79',
                'earned' => true,
            ];
        } else {
            $achievements[] = [
                'id'     => 'multi_budget',
                'title'  => 'Voyageur expérimenté',
                'desc'   => 'Créez 3 budgets',
                'icon'   => 'globe',
                'color'  => '#9ca3af',
                'earned' => false,
                'progress' => count($allBudgets),
                'target'   => 3,
            ];
        }

        // ── Budget challenge vs previous budget ──────────────────
        $challenge = null;
        $userBudgets = array_filter($allBudgets, fn($b) => $b->getIdBudget() !== $budget->getIdBudget());
        if (!empty($userBudgets)) {
            // Find previous budget with most expenses
            usort($userBudgets, fn($a, $b) => $b->getDepenses()->count() <=> $a->getDepenses()->count());
            $prevBudget = reset($userBudgets);
            $prevSpent  = array_sum(array_map(
                fn($d) => (float)$d->getMontantDepense(),
                $prevBudget->getDepenses()->toArray()
            ));
            $prevTotal  = (float)$prevBudget->getMontantTotal();
            $prevPct    = $prevTotal > 0 ? ($prevSpent / $prevTotal) * 100 : 100;
            $targetPct  = max(0, $prevPct - 20); // challenge: spend 20% less
            $currentPct = $pct;
            $winning    = $currentPct <= $targetPct;

            $challenge = [
                'prev_budget'  => $prevBudget->getLibelleBudget(),
                'prev_pct'     => round($prevPct, 1),
                'target_pct'   => round($targetPct, 1),
                'current_pct'  => round($currentPct, 1),
                'winning'      => $winning,
                'gap'          => round($currentPct - $targetPct, 1),
            ];
        }

        // ── Score (0–100) ─────────────────────────────────────────
        $earnedCount = count(array_filter($achievements, fn($a) => $a['earned']));
        $totalCount  = count($achievements);
        $score = (int) round(
            ($earnedCount / max($totalCount, 1)) * 60   // 60% from badges
            + ($currentStreak * 5)                       // 5pts per streak day
            + ($restant >= 0 ? 20 : 0)                  // 20pts for staying under
        );
        $score = min(100, $score);

        return new JsonResponse([
            'score'          => $score,
            'current_streak' => $currentStreak,
            'max_streak'     => $maxStreak,
            'daily_budget'   => round($dailyBudget, 2),
            'devise'         => $devise,
            'achievements'   => $achievements,
            'challenge'      => $challenge,
            'stats'          => [
                'total_spent'  => round($totalSpent, 2),
                'restant'      => round($restant, 2),
                'pct'          => round($pct, 1),
                'nb_depenses'  => count($depenses),
                'nb_days'      => $daySpan,
            ],
        ]);
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