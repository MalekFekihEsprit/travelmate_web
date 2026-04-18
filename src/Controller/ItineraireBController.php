<?php

namespace App\Controller;

use App\Repository\EtapeRepository;
use App\Repository\ItineraireRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/itineraires')]
final class ItineraireBController extends AbstractController
{
    #[Route('', name: 'app_admin_itineraires', methods: ['GET'])]
    public function index(
        ItineraireRepository $itineraireRepository,
        EtapeRepository $etapeRepository,
        VoyageRepository $voyageRepository
    ): Response {
        $itineraires = $itineraireRepository->createQueryBuilder('i')
            ->leftJoin('i.voyage', 'v')
            ->addSelect('v')
            ->orderBy('i.nom_itineraire', 'ASC')
            ->getQuery()
            ->getResult();

        $totalItineraires = count($itineraires);
        $totalEtapes = $etapeRepository->count([]);
        $totalVoyages = $voyageRepository->count([]);

        $voyageIdsAvecItineraire = [];
        $itinerairesAvecEtapes = 0;

        foreach ($itineraires as $itineraire) {
            $voyage = $itineraire->getVoyage();
            if ($voyage !== null) {
                $voyageIdsAvecItineraire[$voyage->getId_voyage()] = true;
            }

            if ($itineraire->getEtapes()->count() > 0) {
                ++$itinerairesAvecEtapes;
            }
        }

        $voyagesAvecItineraire = count($voyageIdsAvecItineraire);
        $voyagesSansItineraire = max(0, $totalVoyages - $voyagesAvecItineraire);

        $moyenneEtapesParItineraire = $totalItineraires > 0
            ? round($totalEtapes / $totalItineraires, 1)
            : 0.0;

        $tauxItinerairesPlanifies = $totalItineraires > 0
            ? (int) round(100 * $itinerairesAvecEtapes / $totalItineraires)
            : 0;

        // ── Chart data ──────────────────────────────────────────────
        // Pie: itineraries whose name matches the voyage title = "AI accepted"
        $chartPie = ['ai' => 0, 'manual' => 0];
        // Bar: frequency map  days => count of itineraries
        $durationFreq = [];

        foreach ($itineraires as $itineraire) {
            $voyage = $itineraire->getVoyage();

            $nom = strtolower(trim((string) $itineraire->getNom_itineraire()));
            $titreVoyage = $voyage ? strtolower(trim((string) $voyage->getTitre_voyage())) : null;

            if ($titreVoyage !== null && $nom === $titreVoyage) {
                ++$chartPie['ai'];
            } else {
                ++$chartPie['manual'];
            }

            if ($voyage && $voyage->getDate_debut() && $voyage->getDate_fin()) {
                $days = (int) $voyage->getDate_debut()->diff($voyage->getDate_fin())->days;
                if (!isset($durationFreq[$days])) {
                    $durationFreq[$days] = 0;
                }
                ++$durationFreq[$days];
            }
        }

        // Sort by duration ascending
        ksort($durationFreq);
        $barLabels = array_keys($durationFreq);
        $barValues = array_values($durationFreq);

        return $this->render('admin/ItineraireB.html.twig', [
            'itineraires' => $itineraires,
            'stats' => [
                'total_voyages' => $totalVoyages,
                'voyages_avec_itineraire' => $voyagesAvecItineraire,
                'voyages_sans_itineraire' => $voyagesSansItineraire,
                'moyenne_etapes_par_itineraire' => $moyenneEtapesParItineraire,
                'taux_itineraires_planifies_pct' => $tauxItinerairesPlanifies,
            ],
            'chartPie' => $chartPie,
            'chartBarLabels' => $barLabels,
            'chartBarValues' => $barValues,
        ]);
    }

    #[Route('/{id_itineraire}/supprimer', name: 'app_admin_itineraires_delete', requirements: ['id_itineraire' => '\\d+'], methods: ['POST'])]
    public function delete(
        int $id_itineraire,
        Request $request,
        ItineraireRepository $itineraireRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $itineraire = $itineraireRepository->find($id_itineraire);

        if (!$itineraire) {
            $this->addFlash('error', 'Itinéraire introuvable.');

            return $this->redirectToRoute('app_admin_itineraires');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_itineraire_'.$id_itineraire, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('app_admin_itineraires');
        }

        $entityManager->remove($itineraire);
        $entityManager->flush();

        $this->addFlash('success', 'L’itinéraire a été supprimé (les étapes associées ont été retirées).');

        return $this->redirectToRoute('app_admin_itineraires');
    }
}