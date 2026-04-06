<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Form\DestinationType;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/destinations')]
class DestinationAdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_destinations', methods: ['GET'])]
    public function index(Request $request, DestinationRepository $repo): Response
    {
        $search = $request->query->get('q');

        if ($search) {
            $destinations = $repo->createQueryBuilder('d')
                ->where('d.nom LIKE :q OR d.pays LIKE :q')
                ->setParameter('q', '%' . $search . '%')
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $destinations = $repo->findBy([], ['id' => 'DESC']);
        }

        return $this->render('destination_admin/index.html.twig', [
            'destinations' => $destinations,
            'search' => $search
        ]);
    }

    #[Route('/new', name: 'app_admin_destinations_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $destination = new Destination();
        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($destination);
            $em->flush();

            $this->addFlash('success', 'Destination ajoutée avec succès ✅');

            return $this->redirectToRoute('app_admin_destinations');
        }

        return $this->render('destination_admin/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_destinations_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Destination $destination, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Destination modifiée avec succès ✏️');

            return $this->redirectToRoute('app_admin_destinations');
        }

        return $this->render('destination_admin/edit.html.twig', [
            'form' => $form->createView(),
            'destination' => $destination
        ]);
    }

    #[Route('/{id}', name: 'app_admin_destinations_delete', methods: ['POST'])]
    public function delete(Request $request, Destination $destination, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_destination_'.$destination->getId(), $request->request->get('_token'))) {
            $em->remove($destination);
            $em->flush();

            $this->addFlash('success', 'Destination supprimée 🗑️');
        }

        return $this->redirectToRoute('app_admin_destinations');
    }
}