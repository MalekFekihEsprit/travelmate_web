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
    //  HELPERS PRIVÉS — IA RECOMMANDATION
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Appelle le script Python ai_recommender.py et retourne
     * un tableau [ 'activites' => [...], 'scoreMap' => [...] ]
     * En cas d'échec du script, retourne l'ordre original sans scoreMap.
     */
    private function getAiRankedActivities(array $activites): array
    {
        // ── 1. Construire le JSON d'entrée pour Python ─────────────────────
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

        // ── 2. Appel du script Python via fichier temporaire ────────────────
        //    On écrit le JSON dans un fichier temp pour éviter les conflits
        //    de guillemets sur Windows (escapeshellarg + JSON = problème).
        $scriptPath = 'C:\\Users\\Admin\\Desktop\\projet sym\\ai_recommender\\ai_recommender.py';

        $tmpFile = tempnam(sys_get_temp_dir(), 'ai_') . '.json';
        file_put_contents($tmpFile, json_encode($data, JSON_UNESCAPED_UNICODE));

        $cmd = sprintf(
            'python -X utf8 "%s" --file "%s" 2>NUL',
            $scriptPath,
            $tmpFile
        );

        $output = shell_exec($cmd);

        // Nettoyage du fichier temporaire dans tous les cas
        @unlink($tmpFile);

        if (!$output) {
            // Fallback silencieux : ordre original, pas de badges IA
            return ['activites' => $activites, 'scoreMap' => []];
        }

        // ── 3. Parser la dernière ligne non-vide ────────────────────────────
        $lines    = array_filter(array_map('trim', explode("\n", trim($output))));
        $jsonLine = end($lines);
        $scores   = json_decode($jsonLine, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($scores)) {
            return ['activites' => $activites, 'scoreMap' => []];
        }

        // ── 4. Construire scoreMap indexé par activite_id ──────────────────
        $scoreMap = [];
        foreach ($scores as $s) {
            $scoreMap[$s['activite_id']] = $s;
        }

        // ── 5. Trier les entités Activite selon l'ordre retourné par l'IA ──
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
    //  PRICE ADVISOR — DOIT être avant /new pour éviter conflit de route
    // ──────────────────────────────────────────────────────────────────────
    #[Route('/admin/activite/price-advisor', name: 'app_activite_price_advisor', methods: ['GET'])]
    public function priceAdvisor(Request $request): JsonResponse
    {
        $activity = trim($request->query->get('activity', ''));
        $location = trim($request->query->get('location', 'Tunis'));

        if (mb_strlen($activity) < 3) {
            return new JsonResponse(['error' => 'Activité trop courte'], 400);
        }

        $scriptPath = 'C:\\Users\\Admin\\Desktop\\projet sym\\price_scraper\\price_scraper.py';

        $cmd = sprintf(
            'python -X utf8 "%s" %s %s 2>NUL',
            $scriptPath,
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

    #[Route('/admin/activite/{id}/delete', name: 'app_activite_delete', methods: ['POST'])]
    public function delete(Request $request, Activite $activite, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $activite->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($activite);
            $entityManager->flush();
            $this->addFlash('success', 'Activité supprimée.');
        }
        return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
    }
}