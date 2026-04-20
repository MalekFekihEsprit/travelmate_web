<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Form\ActiviteType;
use App\Repository\ActiviteRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ActiviteController extends AbstractController
{
    // ════════════════════════════════════════════════════════════════════════
    //  CHEMINS — centralisés ici pour faciliter la maintenance
    // ════════════════════════════════════════════════════════════════════════

    /** Python du venv ai_service (contient openai, etc.) */
    private const PYTHON_VENV = 'C:/Users/Admin/Desktop/projet sym/ai_service/venv/Scripts/python.exe';

    /** Scripts Python */
    private const SCRIPT_AVIS_ANALYSER       = 'C:/Users/Admin/Desktop/projet sym/ai_service/avis_analyser.py';
    private const SCRIPT_AI_RECOMMENDER      = 'C:/Users/Admin/Desktop/projet sym/ai_recommender/ai_recommender.py';
    private const SCRIPT_SIMILAR_RECOMMENDER = 'C:/Users/Admin/Desktop/projet sym/ai_recommender/activity_recommender.py';
    private const SCRIPT_PRICE_SCRAPER       = 'C:/Users/Admin/Desktop/projet sym/price_scraper/price_scraper.py';

    // ════════════════════════════════════════════════════════════════════════
    //  HELPER PRIVÉ — IA RECOMMANDATION (quiz / scoring)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Appelle ai_recommender.py pour le classement IA de la page liste.
     */
    private function getAiRankedActivities(array $activites): array
    {
        $data = [];
        foreach ($activites as $activite) {
            $avisData = [];
            foreach ($activite->getAvis() as $avis) {
                $avisData[] = [
                    'note'        => $avis->getNote(),
                    'commentaire' => $avis->getCommentaire(),
                ];
            }
            $data[] = [
                'id'   => $activite->getId(),
                'avis' => $avisData,
            ];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ai_') . '.json';
        file_put_contents($tmpFile, json_encode($data, JSON_UNESCAPED_UNICODE));

        $cmd = sprintf(
            '"%s" -X utf8 "%s" --file "%s" 2>&1',
            self::PYTHON_VENV,
            self::SCRIPT_AI_RECOMMENDER,
            $tmpFile
        );

        $output = shell_exec($cmd);
        @unlink($tmpFile);

        if (!$output) {
            return ['activites' => $activites, 'scoreMap' => []];
        }

        $lines    = array_filter(array_map('trim', explode("\n", trim($output))));
        $jsonLine = end($lines);
        $scores   = json_decode($jsonLine, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($scores)) {
            return ['activites' => $activites, 'scoreMap' => []];
        }

        $scoreMap = [];
        foreach ($scores as $s) {
            $scoreMap[$s['activite_id']] = $s;
        }

        $scoreOrder = array_column($scores, 'activite_id');
        usort($activites, function ($a, $b) use ($scoreOrder) {
            $posA = array_search($a->getId(), $scoreOrder);
            $posB = array_search($b->getId(), $scoreOrder);
            $posA = ($posA === false) ? 9999 : $posA;
            $posB = ($posB === false) ? 9999 : $posB;
            return $posA <=> $posB;
        });

        return ['activites' => $activites, 'scoreMap' => $scoreMap];
    }

    // ════════════════════════════════════════════════════════════════════════
    //  HELPER PRIVÉ — IA ANALYSE DES AVIS (backoffice)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Appelle avis_analyser.py (Grok) pour générer un feedback IA des avis
     * d'une activité, à destination de l'admin dans le backoffice.
     *
     * Retourne toujours un tableau. En cas d'erreur, la clé '_error' est
     * présente pour affichage dans le twig de diagnostic.
     */
    private function getAvisFeedback(Activite $activite): array
    {
        // ── 1. Collecter les avis non flagués ─────────────────────────────
        $avisData = [];
        foreach ($activite->getAvis() as $avis) {
            if ($avis->isFlagged()) {
                continue;
            }
            $avisData[] = [
                'note'        => $avis->getNote(),
                'commentaire' => $avis->getCommentaire(),
            ];
        }

        // ── 2. Vérifications préalables ───────────────────────────────────
        if (!file_exists(self::PYTHON_VENV)) {
            return [
                '_error' => "Python venv introuvable :\n" . self::PYTHON_VENV
                          . "\nVérifiez que le venv existe dans ai_service/venv/Scripts/",
            ];
        }

        if (!file_exists(self::SCRIPT_AVIS_ANALYSER)) {
            return [
                '_error' => "Script introuvable :\n" . self::SCRIPT_AVIS_ANALYSER,
            ];
        }

        // ── 3. Écrire le payload JSON dans un fichier temporaire ──────────
        $payload = [
            'activite' => $activite->getNom(),
            'avis'     => $avisData,
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'avis_') . '.json';
        if (file_put_contents($tmpFile, json_encode($payload, JSON_UNESCAPED_UNICODE)) === false) {
            return ['_error' => "Impossible d'écrire le fichier temporaire JSON."];
        }

        // ── 4. Exécuter le script via le Python du venv ───────────────────
        $cmd = sprintf(
            '"%s" -X utf8 "%s" --file "%s" 2>&1',
            self::PYTHON_VENV,
            self::SCRIPT_AVIS_ANALYSER,
            $tmpFile
        );

        $output = shell_exec($cmd);
        @unlink($tmpFile);

        // ── 5. Vérifier la sortie ─────────────────────────────────────────
        if ($output === null) {
            return [
                '_error' => "shell_exec() a retourné null.\n"
                          . "Vérifiez que shell_exec est activé dans php.ini (pas dans disable_functions).",
            ];
        }

        if (trim($output) === '') {
            return [
                '_error' => "Le script Python n'a produit aucune sortie.\n"
                          . "Commande : $cmd",
            ];
        }

        // ── 6. Chercher la dernière ligne JSON valide (objet) ─────────────
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));

        foreach (array_reverse($lines) as $line) {
            if (str_starts_with($line, '{')) {
                $result = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                    return $result; // ✅ Succès
                }
            }
        }

        // ── 7. Aucun JSON trouvé → retourner la sortie brute pour debug ───
        return [
            '_error' => 'Aucun JSON valide trouvé dans la sortie du script.',
            '_raw'   => implode("\n", array_slice($lines, 0, 30)),
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    //  FRONT OFFICE
    // ════════════════════════════════════════════════════════════════════════

    #[Route('/activites', name: 'app_activites', methods: ['GET'])]
    public function frontIndex(
        Request             $request,
        ActiviteRepository  $activiteRepository,
        CategorieRepository $categorieRepository
    ): Response {
        if ($this->getUser() && !$request->getSession()->get('quiz_completed', false)) {
            return $this->redirectToRoute('app_quiz');
        }

        $activites = $activiteRepository->findAll();
        $aiResult  = $this->getAiRankedActivities($activites);

        return $this->render('activite/index.html.twig', [
            'activites'  => $aiResult['activites'],
            'scoreMap'   => $aiResult['scoreMap'],
            'categories' => $categorieRepository->findAll(),
        ]);
    }

    #[Route('/activites/{id}', name: 'app_activite_show', methods: ['GET'])]
    public function frontShow(Activite $activite): Response
    {
        return $this->render('activite/show.html.twig', [
            'activite' => $activite,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  RECOMMANDATIONS SIMILAIRES — endpoint AJAX appelé depuis show.html.twig
    // ════════════════════════════════════════════════════════════════════════

    #[Route('/activites/{id}/recommendations', name: 'app_activite_recommendations', methods: ['GET'])]
    public function getSimilarRecommendations(
        Activite           $activite,
        ActiviteRepository $activiteRepository
    ): JsonResponse {
        // ── 1. Toutes les activités sauf la courante ───────────────────────
        $allActivites = $activiteRepository->findAll();
        $others = array_values(array_filter(
            $allActivites,
            fn($a) => $a->getId() !== $activite->getId()
        ));

        if (empty($others)) {
            return new JsonResponse([]);
        }

        // ── 2. Construire le payload JSON pour Python ──────────────────────
        $currentData = [
            'id'          => $activite->getId(),
            'nom'         => $activite->getNom(),
            'description' => $activite->getDescription(),
            'categorie'   => $activite->getCategorie()?->getNom() ?? '',
        ];

        $othersData = [];
        foreach ($others as $other) {
            $othersData[] = [
                'id'          => $other->getId(),
                'nom'         => $other->getNom(),
                'description' => $other->getDescription(),
                'categorie'   => $other->getCategorie()?->getNom() ?? '',
            ];
        }

        $inputPayload = [
            'current' => $currentData,
            'others'  => $othersData,
        ];

        // ── 3. Écrire dans un fichier temp et appeler activity_recommender.py
        $tmpFile = tempnam(sys_get_temp_dir(), 'rec_') . '.json';
        file_put_contents($tmpFile, json_encode($inputPayload, JSON_UNESCAPED_UNICODE));

        $cmd = sprintf(
            '"%s" -X utf8 "%s" --file "%s" 2>&1',
            self::PYTHON_VENV,
            self::SCRIPT_SIMILAR_RECOMMENDER,
            $tmpFile
        );

        $output = shell_exec($cmd);
        @unlink($tmpFile);

        if (!$output) {
            return new JsonResponse(['error' => 'Script Python inaccessible'], 503);
        }

        // ── 4. Parser la dernière ligne non-vide (seule ligne JSON valide) ─
        $lines    = array_filter(array_map('trim', explode("\n", trim($output))));
        $jsonLine = '';
        foreach (array_reverse(array_values($lines)) as $line) {
            if (str_starts_with($line, '[') || str_starts_with($line, '{')) {
                $jsonLine = $line;
                break;
            }
        }

        if (!$jsonLine) {
            return new JsonResponse(['error' => 'Pas de JSON dans la réponse', 'raw' => $output], 500);
        }

        $scores = json_decode($jsonLine, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($scores)) {
            return new JsonResponse(['error' => 'Réponse invalide du script', 'raw' => $jsonLine], 500);
        }

        // ── 5. Hydrater avec les entités Activite ──────────────────────────
        $activiteMap = [];
        foreach ($others as $other) {
            $activiteMap[$other->getId()] = $other;
        }

        $recommendations = [];
        foreach ($scores as $score) {
            $id = $score['activite_id'] ?? null;
            if (!$id || !isset($activiteMap[$id])) {
                continue;
            }
            $act = $activiteMap[$id];

            $desc = $act->getDescription() ?? '';
            if (mb_strlen($desc) > 130) {
                $desc = mb_substr($desc, 0, 130) . '…';
            }

            $recommendations[] = [
                'id'          => $act->getId(),
                'nom'         => $act->getNom(),
                'description' => $desc,
                'categorie'   => $act->getCategorie()?->getNom() ?? '',
                'imagePath'   => $act->getImagePath(),
                'budget'      => $act->getBudget(),
                'lieu'        => $act->getLieu(),
                'duree'       => $act->getDuree(),
                'score'       => $score['score'],
                'reason'      => $score['reason'],
                'url'         => $this->generateUrl('app_activite_show', ['id' => $act->getId()]),
            ];
        }

        return new JsonResponse($recommendations);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  BACK OFFICE
    // ════════════════════════════════════════════════════════════════════════

    #[Route('/admin/activite', name: 'app_activite_index', methods: ['GET'])]
    public function index(ActiviteRepository $activiteRepository): Response
    {
        return $this->render('admin/activite/index.html.twig', [
            'activites' => $activiteRepository->findAll(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  PRICE ADVISOR — doit être avant /{id} pour éviter conflit de route
    // ──────────────────────────────────────────────────────────────────────
    #[Route('/admin/activite/price-advisor', name: 'app_activite_price_advisor', methods: ['GET'])]
    public function priceAdvisor(Request $request): JsonResponse
    {
        $activity = trim($request->query->get('activity', ''));
        $location = trim($request->query->get('location', 'Tunis'));

        if (mb_strlen($activity) < 3) {
            return new JsonResponse(['error' => 'Activité trop courte'], 400);
        }

        if (!file_exists(self::SCRIPT_PRICE_SCRAPER)) {
            return new JsonResponse(['error' => 'Script price_scraper introuvable'], 503);
        }

        $cmd = sprintf(
            '"%s" -X utf8 "%s" %s %s 2>&1',
            self::PYTHON_VENV,
            self::SCRIPT_PRICE_SCRAPER,
            escapeshellarg($activity),
            escapeshellarg($location)
        );

        $output = shell_exec($cmd);

        if (!$output) {
            return new JsonResponse(['error' => 'Script Python inaccessible'], 503);
        }

        $lines    = array_filter(array_map('trim', explode("\n", trim($output))));
        $jsonLine = end($lines);
        $data     = json_decode($jsonLine, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonResponse(['error' => 'Réponse invalide du scraper'], 500);
        }

        return new JsonResponse($data);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  NEW — ⚠️  Doit être AVANT /{id} pour éviter le conflit de route
    // ──────────────────────────────────────────────────────────────────────
    #[Route('/admin/activite/new', name: 'app_activite_new', methods: ['GET', 'POST'])]
    public function new(
        Request                $request,
        EntityManagerInterface $entityManager,
        SluggerInterface       $slugger
    ): Response {
        $activite = new Activite();
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('activites_images_directory'),
                    $newFilename
                );
                $activite->setImagePath('uploads/activites/' . $newFilename);
            }

            $entityManager->persist($activite);
            $entityManager->flush();
            $this->addFlash('success', 'Activité créée avec succès !');
            return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/activite/new.html.twig', [
            'activite' => $activite,
            'form'     => $form,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  SHOW ADMIN — Détail activité + feedback IA des avis (Grok)
    //  ⚠️  Doit rester APRÈS /new et /price-advisor pour éviter conflit /{id}
    // ──────────────────────────────────────────────────────────────────────
    #[Route('/admin/activite/{id}', name: 'app_activite_show_admin', methods: ['GET'])]
    public function showAdmin(Activite $activite): Response
    {
        $feedback    = $this->getAvisFeedback($activite);
        $totalAvis   = count($activite->getAvis());
        $flaggedAvis = array_filter(
            $activite->getAvis()->toArray(),
            fn($a) => $a->isFlagged()
        );

        return $this->render('admin/activite/show.html.twig', [
            'activite'     => $activite,
            'feedback'     => $feedback,
            'totalAvis'    => $totalAvis,
            'flaggedCount' => count($flaggedAvis),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  EDIT
    // ──────────────────────────────────────────────────────────────────────
    #[Route('/admin/activite/{id}/edit', name: 'app_activite_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request                $request,
        Activite               $activite,
        EntityManagerInterface $entityManager,
        SluggerInterface       $slugger
    ): Response {
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('activites_images_directory'),
                    $newFilename
                );
                $activite->setImagePath('uploads/activites/' . $newFilename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Activité modifiée avec succès !');
            return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/activite/edit.html.twig', [
            'activite' => $activite,
            'form'     => $form,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  DELETE
    // ──────────────────────────────────────────────────────────────────────
    #[Route('/admin/activite/{id}/delete', name: 'app_activite_delete', methods: ['POST'])]
    public function delete(
        Request                $request,
        Activite               $activite,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $activite->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($activite);
            $entityManager->flush();
            $this->addFlash('success', 'Activité supprimée.');
        }
        return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
    }
}
