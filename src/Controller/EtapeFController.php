<?php

namespace App\Controller;

use App\Entity\Etape;
use App\Entity\Itineraire;
use App\Repository\EtapeRepository;
use App\Repository\ItineraireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etapes', name: 'app_etapes_')]
class EtapeFController extends AbstractController
{
    #[Route('/jour/{itineraireId}/{jour}', name: 'jour', methods: ['GET'])]
    public function afficherJour(
        int $itineraireId,
        int $jour,
        Request $request,
        ItineraireRepository $itineraireRepository,
        EtapeRepository $etapeRepository
    ): Response {
        $itineraire = $itineraireRepository->find($itineraireId);
        
        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        // Vérifier que le jour est valide
        if ($itineraire->getVoyage()) {
            $voyage = $itineraire->getVoyage();
            if ($voyage->getDate_debut() && $voyage->getDate_fin()) {
                $diff = $voyage->getDate_fin()->diff($voyage->getDate_debut());
                $totalDays = $diff->days + 1;
                
                if ($jour < 1 || $jour > $totalDays) {
                    throw $this->createNotFoundException('Jour invalide');
                }
            }
        }

        // Récupérer les étapes du jour
        $etapes = $etapeRepository->findBy([
            'itineraire' => $itineraire,
            'numero_jour' => $jour
        ]);

        // Trier par heure
        usort($etapes, function($a, $b) {
            if (!$a->getHeure() || !$b->getHeure()) {
                return 0;
            }
            return $a->getHeure() <=> $b->getHeure();
        });

        return $this->render('home/EtapeF.html.twig', [
            'itineraire' => $itineraire,
            'jour' => $jour,
            'etapes' => $etapes,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        ItineraireRepository $itineraireRepository,
        EtapeRepository $etapeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $itineraireId = $request->query->get('itineraireId');
        $jour = $request->query->get('jour');

        $itineraire = $itineraireRepository->find($itineraireId);
        
        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $etape = new Etape();
            $etape->setItineraire($itineraire);
            $etape->setNumero_jour((int)($data['numero_jour'] ?? $jour));
            $etape->setDescription_etape($data['description_etape'] ?? '');
            
            if (!empty($data['heure'])) {
                $etape->setHeure(new \DateTime($data['heure']));
            }

            if (!empty($data['id_activite'])) {
                $voyage = $itineraire->getVoyage();
                if ($voyage) {
                    // Récupérer l'activité depuis les activités du voyage
                    foreach ($voyage->getActivites() as $activite) {
                        if ($activite->getId() == $data['id_activite']) {
                            $etape->setActivite($activite);
                            break;
                        }
                    }
                }
            }

            $entityManager->persist($etape);
            $entityManager->flush();

            $this->addFlash('success', 'Étape créée avec succès!');

            return $this->redirectToRoute('app_etapes_jour', [
                'itineraireId' => $itineraire->getId_itineraire(),
                'jour' => $etape->getNumero_jour()
            ]);
        }

        // Récupérer les activités du voyage
        $activites = [];
        if ($itineraire->getVoyage()) {
            $allActivites = $itineraire->getVoyage()->getActivites();
            
            // Récupérer les étapes du jour pour exclure les activités déjà utilisées
            $etapesDuJour = $etapeRepository->findBy([
                'itineraire' => $itineraire,
                'numero_jour' => (int)$jour
            ]);
            
            $activitesUtilisees = [];
            foreach ($etapesDuJour as $etape) {
                if ($etape->getActivite()) {
                    $activitesUtilisees[] = $etape->getActivite()->getId();
                }
            }
            
            // Filtrer les activités pour exclure celles déjà utilisées
            foreach ($allActivites as $activite) {
                if (!in_array($activite->getId(), $activitesUtilisees)) {
                    $activites[] = $activite;
                }
            }
        }

        return $this->render('home/etape_form.html.twig', [
            'etape' => null,
            'itineraire' => $itineraire,
            'jour' => $jour,
            'activites' => $activites,
            'title' => 'Créer une étape'
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EtapeRepository $etapeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $etape = $etapeRepository->find($id);

        if (!$etape) {
            throw $this->createNotFoundException('Étape non trouvée');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $etape->setDescription_etape($data['description_etape'] ?? '');
            $etape->setNumero_jour((int)($data['numero_jour'] ?? $etape->getNumero_jour()));
            
            if (!empty($data['heure'])) {
                $etape->setHeure(new \DateTime($data['heure']));
            }

            if (!empty($data['id_activite'])) {
                $voyage = $etape->getItineraire()->getVoyage();
                if ($voyage) {
                    // Récupérer l'activité depuis les activités du voyage
                    foreach ($voyage->getActivites() as $activite) {
                        if ($activite->getId() == $data['id_activite']) {
                            $etape->setActivite($activite);
                            break;
                        }
                    }
                }
            } else {
                $etape->setActivite(null);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Étape modifiée avec succès!');

            return $this->redirectToRoute('app_etapes_jour', [
                'itineraireId' => $etape->getItineraire()->getId_itineraire(),
                'jour' => $etape->getNumero_jour()
            ]);
        }

        // Récupérer les activités du voyage
        $activites = [];
        if ($etape->getItineraire()->getVoyage()) {
            $allActivites = $etape->getItineraire()->getVoyage()->getActivites();
            
            // Récupérer les étapes du jour pour exclure les activités déjà utilisées
            $etapesDuJour = $etapeRepository->findBy([
                'itineraire' => $etape->getItineraire(),
                'numero_jour' => $etape->getNumero_jour()
            ]);
            
            $activitesUtilisees = [];
            foreach ($etapesDuJour as $autreEtape) {
                // Exclure l'activité de l'étape en cours d'édition
                if ($autreEtape->getId_etape() !== $id && $autreEtape->getActivite()) {
                    $activitesUtilisees[] = $autreEtape->getActivite()->getId();
                }
            }
            
            // Filtrer les activités pour exclure celles déjà utilisées
            foreach ($allActivites as $activite) {
                if (!in_array($activite->getId(), $activitesUtilisees)) {
                    $activites[] = $activite;
                }
            }
        }

        return $this->render('home/etape_form.html.twig', [
            'etape' => $etape,
            'itineraire' => $etape->getItineraire(),
            'jour' => $etape->getNumero_jour(),
            'activites' => $activites,
            'title' => 'Modifier l\'étape'
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        int $id,
        EtapeRepository $etapeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $etape = $etapeRepository->find($id);

        if (!$etape) {
            throw $this->createNotFoundException('Étape non trouvée');
        }

        $itineraireId = $etape->getItineraire()->getId_itineraire();
        $jour = $etape->getNumero_jour();

        $entityManager->remove($etape);
        $entityManager->flush();

        $this->addFlash('success', 'Étape supprimée avec succès!');

        return $this->redirectToRoute('app_etapes_jour', [
            'itineraireId' => $itineraireId,
            'jour' => $jour
        ]);
    }
}
