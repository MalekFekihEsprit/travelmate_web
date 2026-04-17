<?php

namespace App\Controller;

use App\Entity\ParticipationEvenement;
use App\Repository\EvenementRepository;
use App\Repository\ParticipationEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ParticipationEvenementController extends AbstractController
{
    /**
     * Rejoindre un événement
     * URL : GET /evenements/{id}/rejoindre
     */
    #[Route('/evenements/{id}/rejoindre', name: 'app_participation_join', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function join(
        int                              $id,
        EvenementRepository              $evenementRepository,
        ParticipationEvenementRepository $participationRepo,
        EntityManagerInterface           $entityManager
    ): Response {
        $evenement = $evenementRepository->find($id);

        if (!$evenement) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        if ($evenement->isComplet()) {
            $this->addFlash('error', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $id]);
        }

        // Récupère l'utilisateur connecté
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour rejoindre un événement.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $id]);
        }

        // Vérifie si déjà inscrit
        $dejaInscrit = $participationRepo->findOneBy([
            'user'      => $user,
            'evenement' => $evenement,
        ]);

        if ($dejaInscrit) {
            $this->addFlash('warning', '⚠️ Vous participez déjà à cet événement !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $id]);
        }

        $participation = new ParticipationEvenement();
        $participation->setUser($user);
        $participation->setEvenement($evenement);
        $participation->setCreatedAt(new \DateTime());

        $entityManager->persist($participation);
        $entityManager->flush();

        $this->addFlash('success', '🎉 Vous avez rejoint l\'événement "' . $evenement->getTitre() . '" !');

        return $this->redirectToRoute('app_evenement_show', ['id' => $id]);
    }

    /**
     * Quitter un événement
     * URL : POST /evenements/{id}/quitter
     */
    #[Route('/evenements/{id}/quitter', name: 'app_participation_leave', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function leave(
        int                              $id,
        EvenementRepository              $evenementRepository,
        ParticipationEvenementRepository $participationRepo,
        EntityManagerInterface           $entityManager
    ): Response {
        $evenement = $evenementRepository->find($id);

        if (!$evenement) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        // Récupère l'utilisateur connecté
        $user = $this->getUser();

        if ($user) {
            $participation = $participationRepo->findOneBy([
                'user'      => $user,
                'evenement' => $evenement,
            ]);

            if ($participation) {
                $entityManager->remove($participation);
                $entityManager->flush();
                $this->addFlash('success', 'Vous avez quitté l\'événement.');
            }
        }

        return $this->redirectToRoute('app_evenement_show', ['id' => $id]);
    }
}
