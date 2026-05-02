<?php

namespace App\Service;

use App\Entity\Participation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\ParticipationRepository;

/**
 * Service métier pour la gestion des participations.
 *
 * Règles métier :
 *  1. L'utilisateur (User) est obligatoire.
 *  2. Le voyage (Voyage) est obligatoire.
 *  3. Le rôle doit être parmi les valeurs autorisées (Participant ou Observateur).
 *  4. Un utilisateur ne peut pas être ajouté deux fois au même voyage
 *     (vérification d'unicité – à implémenter si nécessaire dans le repository).
 */
class ParticipationManager
{
    /**
     * Valide une participation (sans vérifier l'unicité en base).
     *
     * @throws \InvalidArgumentException
     */
    public function validate(Participation $participation): bool
    {
        $user = $participation->getUser();
        if ($user === null) {
            throw new \InvalidArgumentException('L\'utilisateur est obligatoire pour une participation.');
        }

        $voyage = $participation->getVoyage();
        if ($voyage === null) {
            throw new \InvalidArgumentException('Le voyage est obligatoire pour une participation.');
        }

        $role = $participation->getRoleParticipation();
        if (!Participation::isSelectableRole($role)) {
            throw new \InvalidArgumentException(
                sprintf('Le rôle "%s" n\'est pas autorisé. Rôles acceptés : %s.', $role, implode(', ', Participation::getSelectableRoles()))
            );
        }

        return true;
    }

    /**
     * Vérifie qu'une participation n'existe pas déjà pour le même utilisateur et le même voyage.
     * Cette méthode suppose que vous avez un repository pour faire la vérification.
     * Ici, nous simulons la logique ; en réalité, vous injecteriez ParticipationRepository.
     *
     * @param ParticipationRepository|null $repository
     */
    public function isUnique(Participation $participation, ?ParticipationRepository $repository = null): bool
    {
        if ($repository === null) {
            // Si pas de repository, on ne vérifie que les champs (pour les tests unitaires)
            return true;
        }

        $existing = $repository->findOneBy([
            'user' => $participation->getUser(),
            'voyage' => $participation->getVoyage(),
        ]);

        return $existing === null || $existing === $participation;
    }
}