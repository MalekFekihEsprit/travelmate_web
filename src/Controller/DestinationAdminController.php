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
                ->where('d.nom_destination LIKE :q OR d.pays_destination LIKE :q')
                ->setParameter('q', '%' . $search . '%')
                ->orderBy('d.id_destination', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $destinations = $repo->findBy([], ['id_destination' => 'DESC']);
        }

        return $this->render('destination_admin/index.html.twig', [
            'destinations' => $destinations,
            'search' => $search
        ]);
    }

    #[Route('/{id}', name: 'app_admin_destinations_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Request $request, Destination $destination): Response
    {
        if ($request->query->getBoolean('inline')) {
            return $this->render('destination_admin/_show_content.html.twig', [
                'destination' => $destination,
            ]);
        }

        return $this->render('destination_admin/show.html.twig', [
            'destination' => $destination,
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

    #[Route('/{id}/edit', name: 'app_admin_destinations_edit', methods: ['GET','POST'], requirements: ['id' => '\\d+'])]
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

    #[Route('/{id}', name: 'app_admin_destinations_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Destination $destination, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_destination_'.$destination->getIdDestination(), $request->request->get('_token'))) {
            $em->remove($destination);
            $em->flush();

            $this->addFlash('success', 'Destination supprimée 🗑️');
        }

        return $this->redirectToRoute('app_admin_destinations');
    }

    #[Route('/bulk-delete', name: 'app_admin_destinations_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, DestinationRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete_destinations', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_destinations');
        }

        $ids = array_filter(array_map('intval', (array) $request->request->all('ids')));

        if (empty($ids)) {
            $this->addFlash('error', 'Veuillez sélectionner au moins une destination.');
            return $this->redirectToRoute('app_admin_destinations');
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            $destination = $repo->find($id);
            if ($destination) {
                $em->remove($destination);
                ++$deletedCount;
            }
        }

        if ($deletedCount > 0) {
            $em->flush();
            $this->addFlash('success', sprintf('%d destination(s) supprimée(s) avec succès 🗑️', $deletedCount));
        } else {
            $this->addFlash('error', 'Aucune destination valide à supprimer.');
        }

        return $this->redirectToRoute('app_admin_destinations');
    }
}