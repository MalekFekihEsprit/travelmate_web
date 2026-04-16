<?php

namespace App\Controller;

use App\Entity\Itineraire;
use App\Entity\Voyage;
use App\Repository\ItineraireRepository;
use App\Repository\VoyageRepository;
use App\Service\GroqCulturalRulesService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route('/itineraires', name: 'app_itineraires_')]
class ItineraireFController extends AbstractController
{
    private static function nombreJoursVoyage(?Voyage $voyage): ?int
    {
        if (!$voyage || !$voyage->getDate_debut() || !$voyage->getDate_fin()) {
            return null;
        }

        return $voyage->getDate_fin()->diff($voyage->getDate_debut())->days + 1;
    }

    private static function formatGeneratedLabelFr(\DateTimeInterface $at): string
    {
        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        $w = (int) $at->format('w');
        $d = (int) $at->format('j');
        $m = (int) $at->format('n');
        $y = (int) $at->format('Y');

        return sprintf(
            'Généré le %s %d %s %d',
            $jours[$w],
            $d,
            $mois[$m - 1],
            $y
        );
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ItineraireRepository $itineraireRepository,
        VoyageRepository $voyageRepository,
        GroqCulturalRulesService $groqCulturalRulesService
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

        $search = mb_strtolower(trim((string) $request->query->get('q', '')));
        $sort = (string) $request->query->get('sort', 'nom_asc');
        if (!in_array($sort, ['nom_asc', 'nom_desc'], true)) {
            $sort = 'nom_asc';
        }

        // Récupérer tous les itinéraires liés à ce voyage
        $itineraires = $itineraireRepository->findBy(['voyage' => $voyageSelectionne]);

        if ($search !== '') {
            $itineraires = array_values(array_filter($itineraires, static function (Itineraire $i) use ($search): bool {
                $nom = mb_strtolower((string) $i->getNom_itineraire());
                $desc = mb_strtolower((string) $i->getDescription_itineraire());

                return str_contains($nom, $search) || str_contains($desc, $search);
            }));
        }

        usort($itineraires, static function (Itineraire $a, Itineraire $b) use ($sort): int {
            $cmp = strcasecmp((string) $a->getNom_itineraire(), (string) $b->getNom_itineraire());

            return $sort === 'nom_desc' ? -$cmp : $cmp;
        });

        $voyageNbJours = self::nombreJoursVoyage($voyageSelectionne);

        $tousItineraires = $itineraireRepository->findBy(['voyage' => $voyageSelectionne]);
        $totalItineraires = count($tousItineraires);
        $totalJours = $voyageNbJours ?? 0;
        $culturalRules = $request->isXmlHttpRequest()
            ? null
            : $groqCulturalRulesService->generateForVoyage($voyageSelectionne);

        return $this->render('home/ItineraireF.html.twig', [
            'itineraires' => $itineraires,
            'totalItineraires' => $totalItineraires,
            'totalJours' => $totalJours,
            'voyageSelectionne' => $voyageSelectionne,
            'voyageId' => $voyageId,
            'voyageNbJours' => $voyageNbJours,
            'search_q' => trim((string) $request->query->get('q', '')),
            'sort' => $sort,
            'culturalRules' => $culturalRules,
        ]);
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(
        Request $request,
        ItineraireRepository $itineraireRepository,
        VoyageRepository $voyageRepository,
        Environment $twig
    ): Response {
        $voyageId = $request->query->get('voyageId');
        if (!$voyageId) {
            throw $this->createNotFoundException('Voyage requis');
        }

        $voyage = $voyageRepository->find($voyageId);
        if (!$voyage) {
            throw $this->createNotFoundException('Voyage non trouvé');
        }

        $itineraires = $itineraireRepository->findBy(['voyage' => $voyage]);
        usort($itineraires, static function (Itineraire $a, Itineraire $b): int {
            return strcasecmp((string) $a->getNom_itineraire(), (string) $b->getNom_itineraire());
        });

        $voyageNbJours = self::nombreJoursVoyage($voyage);

        $totalItineraires = count($itineraires);
        $totalPlanifies = 0;
        foreach ($itineraires as $itin) {
            if ($itin->getEtapes()->count() > 0) {
                ++$totalPlanifies;
            }
        }

        $generatedAt = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
        $generatedLabelFr = self::formatGeneratedLabelFr($generatedAt);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($twig->render('home/itineraires_export_pdf.html.twig', [
            'voyage' => $voyage,
            'itineraires' => $itineraires,
            'voyageNbJours' => $voyageNbJours,
            'total_itineraires' => $totalItineraires,
            'total_planifies' => $totalPlanifies,
            'generated_at' => $generatedAt,
            'generated_label_fr' => $generatedLabelFr,
        ]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Mes Itineraires - TravelMate.pdf';

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename)
        );

        return $response;
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