<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Service\TicketmasterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class EvenementController extends AbstractController
{
    // ════════════════════════════════════════════════════════════════════════
    //  FRONT OFFICE
    // ════════════════════════════════════════════════════════════════════════

    #[Route('/evenements', name: 'app_evenements', methods: ['GET'])]
    public function frontIndex(
        EvenementRepository $evenementRepository,
        TicketmasterService $ticketmaster,
        Request             $request
    ): Response {
        $destination    = trim($request->query->get('destination', ''));
        $externalEvents = [];

        if ($destination !== '') {
            $externalEvents = $ticketmaster->fetchEventsByDestination($destination, 12);
        }

        return $this->render('evenement/index.html.twig', [
            'evenements'     => $evenementRepository->findAll(),
            'externalEvents' => $externalEvents,
            'destination'    => $destination,
        ]);
    }

    #[Route('/evenements/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function frontShow(Evenement $evenement): Response
    {
        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  BACK OFFICE
    // ════════════════════════════════════════════════════════════════════════

    #[Route('/admin/evenement', name: 'app_evenement_index', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        return $this->render('admin/evenement/index.html.twig', [
            'evenements' => $evenementRepository->findAll(),
        ]);
    }

    #[Route('/admin/evenement/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(
        Request                $request,
        EntityManagerInterface $entityManager,
        SluggerInterface       $slugger
    ): Response {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('evenements_images_directory'),
                    $newFilename
                );
                $evenement->setImagePath($newFilename);
            }

            $entityManager->persist($evenement);
            $entityManager->flush();
            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/evenement/new.html.twig', [
            'evenement' => $evenement,
            'form'      => $form,
        ]);
    }

    #[Route('/admin/evenement/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request                $request,
        Evenement              $evenement,
        EntityManagerInterface $entityManager,
        SluggerInterface       $slugger
    ): Response {
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('evenements_images_directory'),
                    $newFilename
                );
                $evenement->setImagePath($newFilename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Événement modifié avec succès !');
            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form'      => $form,
        ]);
    }

    #[Route('/admin/evenement/{id}/delete', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
            $this->addFlash('success', 'Événement supprimé.');
        }
        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }
}