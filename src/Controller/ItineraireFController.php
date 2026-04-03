<?php

namespace App\Controller;

use App\Entity\Itineraire;
use App\Entity\Voyage;
use App\Entity\Destination;
use App\Repository\ItineraireRepository;
use App\Repository\VoyageRepository;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/itineraires', name: 'app_itineraires_')]
class ItineraireFController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ItineraireRepository $itineraireRepository,
        VoyageRepository $voyageRepository
    ): Response {
        $voyageId = $request->query->get('voyageId');

        // Si pas de voyage sélectionné, rediriger vers la page d'accueil ou voyages
        if (!$voyageId) {
            return $this->redirectToRoute('app_voyages');
        }

        $voyageSelectionne = $voyageRepository->find($voyageId);
        
        if (!$voyageSelectionne) {
            throw $this->createNotFoundException('Voyage non trouvé');
        }

        // Récupérer tous les itinéraires liés à ce voyage
        $itineraires = $itineraireRepository->findBy(['voyage' => $voyageSelectionne]);

        // Trier par date du voyage
        usort($itineraires, function($a, $b) {
            return $b->getVoyage()->getDate_debut() <=> $a->getVoyage()->getDate_debut();
        });

        // Compter les stats
        $totalItineraires = count($itineraires);
        $totalJours = 0;
        foreach ($itineraires as $itineraire) {
            if ($itineraire->getVoyage() && $itineraire->getVoyage()->getDate_debut() && $itineraire->getVoyage()->getDate_fin()) {
                $diff = $itineraire->getVoyage()->getDate_fin()->diff($itineraire->getVoyage()->getDate_debut());
                $totalJours += $diff->days + 1;
            }
        }

        return $this->render('home/ItineraireF.html.twig', [
            'itineraires' => $itineraires,
            'totalItineraires' => $totalItineraires,
            'totalJours' => $totalJours,
            'voyageSelectionne' => $voyageSelectionne,
            'voyageId' => $voyageId,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        VoyageRepository $voyageRepository,
        ItineraireRepository $itineraireRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $voyageId = $request->query->get('voyageId');
        $voyageSelectionne = null;

        if ($voyageId) {
            $voyageSelectionne = $voyageRepository->find($voyageId);
            if (!$voyageSelectionne) {
                throw $this->createNotFoundException('Voyage non trouvé');
            }
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validation du nom
            if (empty($data['nom_itineraire'])) {
                $errors[] = 'Le nom de l\'itinéraire est obligatoire.';
            } elseif (strlen($data['nom_itineraire']) < 3) {
                $errors[] = 'Le nom de l\'itinéraire doit contenir au minimum 3 caractères.';
            }

            // Validation de la description
            if (empty($data['description_itineraire'])) {
                $errors[] = 'La description est obligatoire.';
            } elseif (strlen($data['description_itineraire']) < 10) {
                $errors[] = 'La description doit contenir au minimum 10 caractères.';
            }

            // Validation du voyage
            if (empty($data['id_voyage'])) {
                $errors[] = 'Le voyage est obligatoire.';
            }

            // Vérifier l'unicité du nom pour ce voyage
            if (!empty($data['nom_itineraire']) && !empty($data['id_voyage'])) {
                $voyage = $voyageRepository->find($data['id_voyage']);
                if ($voyage) {
                    $itineraireExistant = $itineraireRepository->findOneBy([
                        'voyage' => $voyage,
                        'nom_itineraire' => $data['nom_itineraire']
                    ]);
                    if ($itineraireExistant) {
                        $errors[] = 'Un itinéraire avec ce nom existe déjà pour ce voyage.';
                    }
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                // Retourner au formulaire
                $voyages = $voyageRepository->findAll();
                return $this->render('home/itineraire_form.html.twig', [
                    'itineraire' => null,
                    'voyages' => $voyages,
                    'title' => 'Créer un itinéraire',
                    'voyageSelectionne' => $voyageSelectionne,
                    'voyageId' => $voyageId,
                ]);
            }

            $itineraire = new Itineraire();
            $itineraire->setNom_itineraire($data['nom_itineraire']);
            $itineraire->setDescription_itineraire($data['description_itineraire']);

            // Lier au voyage
            if (isset($data['id_voyage'])) {
                $voyage = $voyageRepository->find($data['id_voyage']);
                if ($voyage) {
                    $itineraire->setVoyage($voyage);
                }
            }

            $entityManager->persist($itineraire);
            $entityManager->flush();

            $this->addFlash('success', 'Itinéraire créé avec succès!');

            // Si voyageId, redirige vers la page du voyage
            if ($voyageId) {
                return $this->redirectToRoute('app_itineraires_index', ['voyageId' => $voyageId]);
            }

            return $this->redirectToRoute('app_itineraires_index');
        }

        $voyages = $voyageRepository->findAll();

        return $this->render('home/itineraire_form.html.twig', [
            'itineraire' => null,
            'voyages' => $voyages,
            'title' => 'Créer un itinéraire',
            'voyageSelectionne' => $voyageSelectionne,
            'voyageId' => $voyageId,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        ItineraireRepository $itineraireRepository,
        VoyageRepository $voyageRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $itineraire = $itineraireRepository->find($id);

        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validation du nom
            if (empty($data['nom_itineraire'])) {
                $errors[] = 'Le nom de l\'itinéraire est obligatoire.';
            } elseif (strlen($data['nom_itineraire']) < 3) {
                $errors[] = 'Le nom de l\'itinéraire doit contenir au minimum 3 caractères.';
            }

            // Validation de la description
            if (empty($data['description_itineraire'])) {
                $errors[] = 'La description est obligatoire.';
            } elseif (strlen($data['description_itineraire']) < 10) {
                $errors[] = 'La description doit contenir au minimum 10 caractères.';
            }

            // Vérifier l'unicité du nom pour ce voyage (en excluant l'itinéraire actuel)
            if (!empty($data['nom_itineraire']) && !empty($data['id_voyage'])) {
                $voyage = $voyageRepository->find($data['id_voyage']);
                if ($voyage) {
                    $itineraireExistant = $itineraireRepository->findOneBy([
                        'voyage' => $voyage,
                        'nom_itineraire' => $data['nom_itineraire']
                    ]);
                    if ($itineraireExistant && $itineraireExistant->getId_itineraire() !== $id) {
                        $errors[] = 'Un itinéraire avec ce nom existe déjà pour ce voyage.';
                    }
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                // Retourner au formulaire
                $voyages = $voyageRepository->findAll();
                $voyageSelectionne = $itineraire->getVoyage();
                $voyageId = $voyageSelectionne ? $voyageSelectionne->getId_voyage() : null;
                return $this->render('home/itineraire_form.html.twig', [
                    'itineraire' => $itineraire,
                    'voyages' => $voyages,
                    'title' => 'Modifier l\'itinéraire',
                    'voyageSelectionne' => $voyageSelectionne,
                    'voyageId' => $voyageId,
                ]);
            }

            $itineraire->setNom_itineraire($data['nom_itineraire']);
            $itineraire->setDescription_itineraire($data['description_itineraire']);

            if (isset($data['id_voyage'])) {
                $voyage = $voyageRepository->find($data['id_voyage']);
                if ($voyage) {
                    $itineraire->setVoyage($voyage);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Itinéraire modifié avec succès!');

            // Si le voyage existe, redirige vers la page du voyage
            if ($itineraire->getVoyage()) {
                return $this->redirectToRoute('app_itineraires_index', ['voyageId' => $itineraire->getVoyage()->getId_voyage()]);
            }

            return $this->redirectToRoute('app_itineraires_index');
        }

        $voyages = $voyageRepository->findAll();
        $voyageSelectionne = $itineraire->getVoyage();
        $voyageId = $voyageSelectionne ? $voyageSelectionne->getId_voyage() : null;

        return $this->render('home/itineraire_form.html.twig', [
            'itineraire' => $itineraire,
            'voyages' => $voyages,
            'title' => 'Modifier l\'itinéraire',
            'voyageSelectionne' => $voyageSelectionne,
            'voyageId' => $voyageId,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        int $id,
        ItineraireRepository $itineraireRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $itineraire = $itineraireRepository->find($id);

        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        // Stocker le voyage avant de supprimer
        $voyage = $itineraire->getVoyage();

        $entityManager->remove($itineraire);
        $entityManager->flush();

        $this->addFlash('success', 'Itinéraire supprimé avec succès!');

        // Si le voyage existe, redirige vers la page du voyage
        if ($voyage) {
            return $this->redirectToRoute('app_itineraires_index', ['voyageId' => $voyage->getId_voyage()]);
        }

        return $this->redirectToRoute('app_itineraires_index');
    }

}
