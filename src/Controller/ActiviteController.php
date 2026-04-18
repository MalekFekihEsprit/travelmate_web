<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Form\ActiviteType;
use App\Repository\ActiviteRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ActiviteController extends AbstractController
{
    // ════════════════════════════════════════════════════════════════════════
    //  FRONT OFFICE
    // ════════════════════════════════════════════════════════════════════════

    #[Route('/activites', name: 'app_activites', methods: ['GET'])]
    public function frontIndex(
        Request             $request,
        ActiviteRepository  $activiteRepository,
        CategorieRepository $categorieRepository
    ): Response {
        // Si l'utilisateur est connecté et n'a pas encore fait le quiz → rediriger
        if ($this->getUser() && !$request->getSession()->get('quiz_completed', false)) {
            return $this->redirectToRoute('app_quiz');
        }

        return $this->render('activite/index.html.twig', [
            'activites'  => $activiteRepository->findAll(),
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