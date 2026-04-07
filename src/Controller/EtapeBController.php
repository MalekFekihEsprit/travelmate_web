<?php

namespace App\Controller;

use App\Entity\Etape;
use App\Repository\EtapeRepository;
use App\Repository\ItineraireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/etapes', name: 'app_admin_etapes_')]
#[IsGranted('ROLE_ADMIN')]
final class EtapeBController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        EtapeRepository $etapeRepository,
        ItineraireRepository $itineraireRepository
    ): Response {
        $itineraireId = $request->query->get('itineraireId');
        $itineraire = null;
        $etapes = [];

        $tousLesBlocs = [];

        if ($itineraireId) {
            $itineraire = $itineraireRepository->find($itineraireId);
            if (!$itineraire) {
                throw $this->createNotFoundException('Itinéraire non trouvé');
            }
            $etapes = $etapeRepository->findBy(
                ['itineraire' => $itineraire],
                ['numero_jour' => 'ASC', 'heure' => 'ASC']
            );
        } else {
            $etapes = $etapeRepository->createQueryBuilder('e')
                ->leftJoin('e.itineraire', 'i')->addSelect('i')
                ->leftJoin('i.voyage', 'v')->addSelect('v')
                ->leftJoin('e.activite', 'a')->addSelect('a')
                ->orderBy('i.nom_itineraire', 'ASC')
                ->addOrderBy('e.numero_jour', 'ASC')
                ->addOrderBy('e.heure', 'ASC')
                ->getQuery()
                ->getResult();

            $parItineraireId = [];
            foreach ($etapes as $etape) {
                $it = $etape->getItineraire();
                if ($it === null) {
                    continue;
                }
                $id = $it->getId_itineraire();
                if (!isset($parItineraireId[$id])) {
                    $parItineraireId[$id] = [];
                }
                $parItineraireId[$id][] = $etape;
            }
            foreach ($parItineraireId as $liste) {
                $it = $liste[0]->getItineraire();
                $tousLesBlocs[] = [
                    'itineraire' => $it,
                    'etapes_par_jour' => $this->groupEtapesParJour($liste),
                ];
            }
            usort(
                $tousLesBlocs,
                static fn (array $a, array $b): int => strcmp(
                    $a['itineraire']->getNom_itineraire() ?? '',
                    $b['itineraire']->getNom_itineraire() ?? ''
                )
            );
        }

        $itineraires = $itineraireRepository->createQueryBuilder('i')
            ->leftJoin('i.voyage', 'v')
            ->addSelect('v')
            ->orderBy('i.nom_itineraire', 'ASC')
            ->getQuery()
            ->getResult();

        $etapesParJour = $itineraireId ? $this->groupEtapesParJour($etapes) : [];

        return $this->render('admin/EtapeB.html.twig', [
            'itineraires' => $itineraires,
            'itineraire_selectionne' => $itineraire,
            'etapes_par_jour' => $etapesParJour,
            'itineraire_id' => $itineraireId,
            'tous_les_blocs' => $tousLesBlocs,
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
        $itineraire = $itineraireRepository->find($itineraireId);

        if (!$itineraire) {
            throw $this->createNotFoundException('Itinéraire non trouvé');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validation
            if (empty($data['description_etape'])) {
                $errors[] = 'La description est obligatoire.';
            } elseif (strlen($data['description_etape']) < 10) {
                $errors[] = 'La description doit contenir au minimum 10 caractères.';
            }

            if (empty($data['numero_jour'])) {
                $errors[] = 'Le numéro du jour est obligatoire.';
            } elseif (!is_numeric($data['numero_jour']) || (int)$data['numero_jour'] < 1) {
                $errors[] = 'Le numéro du jour doit être un nombre positif.';
            }

            if (empty($data['heure'])) {
                $errors[] = 'L\'heure est obligatoire.';
            }

            // Vérifier l'unicité de l'heure pour ce jour
            if (!empty($data['heure']) && !empty($data['numero_jour'])) {
                $heure = new \DateTime($data['heure']);
                $etapesExistantes = $etapeRepository->findBy([
                    'itineraire' => $itineraire,
                    'numero_jour' => (int)$data['numero_jour']
                ]);
                foreach ($etapesExistantes as $existant) {
                    if ($existant->getHeure() && $existant->getHeure()->format('H:i') === $heure->format('H:i')) {
                        $errors[] = 'Une étape existe déjà à cette heure pour ce jour.';
                        break;
                    }
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_admin_etapes_create', ['itineraireId' => $itineraireId]);
            }

            $etape = new Etape();
            $etape->setItineraire($itineraire);
            $etape->setDescription_etape($data['description_etape']);
            $etape->setNumero_jour((int)$data['numero_jour']);
            $etape->setHeure(new \DateTime($data['heure']));

            $entityManager->persist($etape);
            $entityManager->flush();

            $this->addFlash('success', 'Étape créée avec succès!');
            return $this->redirectToRoute('app_admin_etapes_index', ['itineraireId' => $itineraireId]);
        }

        // Calculer le nombre de jours du voyage pour limiter le champ jour
        $voyage = $itineraire->getVoyage();
        $nbJours = null;
        if ($voyage && $voyage->getDate_debut() && $voyage->getDate_fin()) {
            $nbJours = $voyage->getDate_fin()->diff($voyage->getDate_debut())->days + 1;
        }

        return $this->render('admin/etape_form.html.twig', [
            'etape' => null,
            'itineraire' => $itineraire,
            'nbJours' => $nbJours,
            'title' => 'Créer une étape',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EtapeRepository $etapeRepository,
        ItineraireRepository $itineraireRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $etape = $etapeRepository->find($id);

        if (!$etape) {
            throw $this->createNotFoundException('Étape non trouvée');
        }

        $itineraire = $etape->getItineraire();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = [];

            // Validation
            if (empty($data['description_etape'])) {
                $errors[] = 'La description est obligatoire.';
            } elseif (strlen($data['description_etape']) < 10) {
                $errors[] = 'La description doit contenir au minimum 10 caractères.';
            }

            if (empty($data['numero_jour'])) {
                $errors[] = 'Le numéro du jour est obligatoire.';
            } elseif (!is_numeric($data['numero_jour']) || (int)$data['numero_jour'] < 1) {
                $errors[] = 'Le numéro du jour doit être un nombre positif.';
            }

            if (empty($data['heure'])) {
                $errors[] = 'L\'heure est obligatoire.';
            }

            // Vérifier l'unicité de l'heure pour ce jour (exclure l'étape actuelle)
            if (!empty($data['heure']) && !empty($data['numero_jour'])) {
                $heure = new \DateTime($data['heure']);
                $etapesExistantes = $etapeRepository->findBy([
                    'itineraire' => $itineraire,
                    'numero_jour' => (int)$data['numero_jour']
                ]);
                foreach ($etapesExistantes as $existant) {
                    if ($existant->getId_etape() !== $id && $existant->getHeure() && $existant->getHeure()->format('H:i') === $heure->format('H:i')) {
                        $errors[] = 'Une étape existe déjà à cette heure pour ce jour.';
                        break;
                    }
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_admin_etapes_edit', ['id' => $id]);
            }

            $etape->setDescription_etape($data['description_etape']);
            $etape->setNumero_jour((int)$data['numero_jour']);
            $etape->setHeure(new \DateTime($data['heure']));

            $entityManager->flush();

            $this->addFlash('success', 'Étape modifiée avec succès!');
            return $this->redirectToRoute('app_admin_etapes_index', ['itineraireId' => $itineraire->getId_itineraire()]);
        }

        $voyage = $itineraire->getVoyage();
        $nbJours = null;
        if ($voyage && $voyage->getDate_debut() && $voyage->getDate_fin()) {
            $nbJours = $voyage->getDate_fin()->diff($voyage->getDate_debut())->days + 1;
        }

        return $this->render('admin/etape_form.html.twig', [
            'etape' => $etape,
            'itineraire' => $itineraire,
            'nbJours' => $nbJours,
            'title' => 'Modifier l\'étape',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        EtapeRepository $etapeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $etape = $etapeRepository->find($id);

        if (!$etape) {
            $this->addFlash('error', 'Étape non trouvée');
            return $this->redirectToRoute('app_admin_etapes_index');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_etape_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_etapes_index');
        }

        $itineraireId = $etape->getItineraire()->getId_itineraire();
        $entityManager->remove($etape);
        $entityManager->flush();

        $this->addFlash('success', 'Étape supprimée avec succès!');
        return $this->redirectToRoute('app_admin_etapes_index', ['itineraireId' => $itineraireId]);
    }

    /**
     * @param list<Etape> $etapes
     * @return array<int, list<Etape>>
     */
    private function groupEtapesParJour(array $etapes): array
    {
        $etapesParJour = [];
        foreach ($etapes as $etape) {
            $jour = $etape->getNumero_jour();
            if (!isset($etapesParJour[$jour])) {
                $etapesParJour[$jour] = [];
            }
            $etapesParJour[$jour][] = $etape;
        }
        ksort($etapesParJour, SORT_NUMERIC);

        return $etapesParJour;
    }
}