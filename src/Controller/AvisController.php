<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Repository\ActiviteRepository;
use App\Service\ProfanityCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AvisController extends AbstractController
{
    /**
     * Soumettre un avis depuis la page détail activité
     * URL : POST /activites/{id}/avis
     */
    #[Route('/activites/{id}/avis', name: 'app_avis_new', methods: ['POST'])]
    public function new(
        int                     $id,
        Request                 $request,
        ActiviteRepository      $activiteRepository,
        EntityManagerInterface  $entityManager,
        ProfanityCheckerService $profanityChecker        // ← injection du service
    ): Response {
        $activite = $activiteRepository->find($id);

        if (!$activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }

        // Vérifie que l'utilisateur est connecté
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour laisser un avis.');
            return $this->redirectToRoute('app_activite_show', ['id' => $id]);
        }

        $note        = (int) $request->request->get('note', 5);
        $commentaire = trim($request->request->get('commentaire', ''));
        $note        = max(1, min(5, $note));

        // ── Détection de gros mots ─────────────────────────────────────────
        $isFlagged = false;
        if ($commentaire !== '') {
            $isFlagged = $profanityChecker->containsProfanity($commentaire);
        }
        // ──────────────────────────────────────────────────────────────────

        $avis = new Avis();
        $avis->setNote($note);
        $avis->setCommentaire($commentaire ?: null);
        $avis->setUser($user);
        $avis->setActivite($activite);
        $avis->setCreatedAt(new \DateTime());
        $avis->setIsFlagged($isFlagged);   // ← on enregistre le flag

        $entityManager->persist($avis);
        $entityManager->flush();

        // Message différent selon si le commentaire a été flagué
        if ($isFlagged) {
            $this->addFlash(
                'warning',
                '⚠️ Votre avis a été publié, mais votre commentaire a été masqué car il contient un langage inapproprié.'
            );
        } else {
            $this->addFlash('success', '✅ Votre avis a été publié, merci !');
        }

        return $this->redirect(
            $this->generateUrl('app_activite_show', ['id' => $id]) . '#tab-avis'
        );
    }
}