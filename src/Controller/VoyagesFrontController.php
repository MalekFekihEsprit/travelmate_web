<?php

namespace App\Controller;

use App\Entity\Voyage;
use App\Form\VoyageType;
use App\Repository\DestinationRepository;
use App\Repository\VoyageRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoyagesFrontController extends AbstractController
{
    #[Route('/voyages', name: 'app_voyages', methods: ['GET'])]
    public function index(VoyageRepository $voyageRepository): Response
    {
        $voyages = $voyageRepository->findBy([], ['date_debut' => 'ASC', 'titre_voyage' => 'ASC']);
        $galleryPaths = $this->getVoyageGalleryPaths();

        return $this->render('home/voyages.html.twig', [
            'voyages' => $voyages,
            'voyage_images' => $this->buildVoyageImageMap($voyages, $galleryPaths),
            'hero_image' => $galleryPaths !== [] ? $galleryPaths[array_rand($galleryPaths)] : null,
            'voyage_gallery_count' => count($galleryPaths),
        ]);
    }

    #[Route('/voyages/ajouter', name: 'app_voyages_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        DestinationRepository $destinationRepository
    ): Response {
        $voyage = new Voyage();
        $voyage->setStatut('Planifie');

        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($voyage);
            $entityManager->flush();

            $this->addFlash('success', 'Le voyage a ete ajoute avec succes.');

            return $this->redirectToRoute('app_voyages');
        }

        return $this->render('home/voyage_form.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Ajouter un voyage',
            'page_description' => 'Creez un voyage avec un formulaire controle et une mise en page coherente avec TravelMate.',
            'submit_label' => 'Enregistrer le voyage',
            'has_destinations' => $destinationRepository->count([]) > 0,
        ]);
    }

    #[Route('/voyages/{id_voyage}/modifier', name: 'app_voyages_edit', requirements: ['id_voyage' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        DestinationRepository $destinationRepository,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage
    ): Response {
        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le voyage a ete modifie avec succes.');

            return $this->redirectToRoute('app_voyages');
        }

        return $this->render('home/voyage_form.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Modifier le voyage',
            'page_description' => 'Mettez a jour les informations du voyage .',
            'submit_label' => 'Mettre a jour',
            'has_destinations' => $destinationRepository->count([]) > 0,
        ]);
    }

    #[Route('/voyages/{id_voyage}/supprimer', name: 'app_voyages_delete', requirements: ['id_voyage' => '\\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['id_voyage' => 'id_voyage'])] Voyage $voyage
    ): Response {
        if (!$this->isCsrfTokenValid('delete_voyage_'.$voyage->getIdVoyage(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requete de suppression est invalide.');

            return $this->redirectToRoute('app_voyages');
        }

        try {
            $entityManager->remove($voyage);
            $entityManager->flush();

            $this->addFlash('success', 'Le voyage a ete supprime avec succes.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'Ce voyage ne peut pas etre supprime car il est lie a d\'autres donnees.');
        }

        return $this->redirectToRoute('app_voyages');
    }

    private function getVoyageGalleryPaths(): array
    {
        $directory = $this->getParameter('kernel.project_dir').DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'imagesVoyage';

        if (!is_dir($directory)) {
            return [];
        }

        $paths = [];

        foreach (scandir($directory) ?: [] as $fileName) {
            if (in_array($fileName, ['.', '..'], true)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }

            $paths[] = 'images/imagesVoyage/'.$fileName;
        }

        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    /**
     * @param Voyage[] $voyages
     * @param string[] $galleryPaths
     *
     * @return array<int, string>
     */
    private function buildVoyageImageMap(array $voyages, array $galleryPaths): array
    {
        if ($galleryPaths === []) {
            return [];
        }

        $shuffledPaths = $galleryPaths;
        shuffle($shuffledPaths);

        $imageMap = [];
        $galleryCount = count($shuffledPaths);

        foreach (array_values($voyages) as $index => $voyage) {
            $voyageId = $voyage->getIdVoyage();

            if ($voyageId === null) {
                continue;
            }

            $imageMap[$voyageId] = $shuffledPaths[$index % $galleryCount];
        }

        return $imageMap;
    }
}