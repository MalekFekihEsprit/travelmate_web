<?php
namespace App\Controller;

use App\Service\AiRecommendationService;
use App\Repository\ActiviteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class QuizController extends AbstractController
{
    #[Route('/quiz', name: 'app_quiz')]
    public function index(Request $request): Response
    {
        if ($request->getSession()->get('quiz_completed', false)) {
            return $this->redirectToRoute('app_activites');
        }
        return $this->render('quiz/index.html.twig');
    }

    #[Route('/quiz/results', name: 'app_quiz_results', methods: ['POST'])]
    public function results(
        Request $request,
        ActiviteRepository $activiteRepo,
        AiRecommendationService $aiService
    ): Response {
        $userProfile = $this->buildUserProfile($request);

        if (empty(trim($userProfile))) {
            $this->addFlash('error', 'Veuillez sélectionner au moins un tag.');
            return $this->redirectToRoute('app_quiz');
        }

        $activites = $activiteRepo->findAll();
        $activitiesData = array_map(fn($a) => [
            'id'               => $a->getId(),
            'nom'              => $a->getNom() ?? '',
            'description'      => $a->getDescription() ?? '',
            'niveaudifficulte' => $a->getNiveaudifficulte() ?? '',
            'lieu'             => $a->getLieu() ?? '',
            'budget'           => (string)($a->getBudget() ?? ''),
            'imagePath'        => $a->getImagePath() ?? '',
            // Champs de la catégorie — très importants pour le matching
            //'categorie'        => $a->getCategorie()?->getNom() ?? '',
            //'categorie_type'   => $a->getCategorie()?->getType() ?? '',
            //'niveauintensite'  => $a->getCategorie()?->getNiveauintensite() ?? '',
            'publiccible'      => $a->getCategorie()?->getPubliccible() ?? '',
            'saison'           => $a->getCategorie()?->getSaison() ?? '',
            'keywords'         => '',
        ], $activites);

        try {
            $allRecommendations = $aiService->getRecommendations($userProfile, $activitiesData);

            // Garder uniquement score > 0, max 8
            $recommendations = array_filter(
                $allRecommendations,
                fn($r) => $r['score'] > 0
            );
            $recommendations = array_slice(array_values($recommendations), 0, 8);

            // Si aucun résultat, prendre le top 5 sans filtre
            if (empty($recommendations)) {
                $recommendations = array_slice($allRecommendations, 0, 5);
            }

            $request->getSession()->set('quiz_completed', true);
            $request->getSession()->set('user_profile', $userProfile);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Service IA indisponible. Réessayez.');
            return $this->redirectToRoute('app_quiz');
        }

        return $this->render('quiz/profile.html.twig', [
            'recommendations' => $recommendations,
            'userProfile'     => $userProfile,
        ]);
    }

    #[Route('/quiz/reset', name: 'app_quiz_reset')]
    public function reset(Request $request): Response
    {
        $request->getSession()->remove('quiz_completed');
        $request->getSession()->remove('user_profile');
        return $this->redirectToRoute('app_quiz');
    }

    private function buildUserProfile(Request $request): string
    {
        $intensity = $request->request->get('intensity', '');
        $location  = $request->request->get('location', '');
        $budget    = $request->request->get('budget', '');
        $category  = $request->request->get('category', '');

        return trim("$intensity $location $budget $category");
    }
}