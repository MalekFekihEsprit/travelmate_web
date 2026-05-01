<?php

namespace App\Service;

use App\Entity\User;

class UserManager
{
    /**
     * Valide les règles métier d'un User.
     */
    public function validate(User $user): bool
    {
        if (empty(trim((string) $user->getNom()))) {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }

        if (empty(trim((string) $user->getPrenom()))) {
            throw new \InvalidArgumentException('Le prénom est obligatoire.');
        }

        if (empty(trim((string) $user->getEmail()))) {
            throw new \InvalidArgumentException('L\'email est obligatoire.');
        }

        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'email n\'est pas valide.');
        }

        if ($user->getDate_naissance() === null) {
            throw new \InvalidArgumentException('La date de naissance est obligatoire.');
        }

        $today = new \DateTime('today');
        if ($user->getDate_naissance() >= $today) {
            throw new \InvalidArgumentException('La date de naissance doit être dans le passé.');
        }

        $age = $today->diff($user->getDate_naissance())->y;
        if ($age < 13) {
            throw new \InvalidArgumentException('L\'utilisateur doit avoir au moins 13 ans.');
        }

        if (empty(trim((string) $user->getMot_de_passe()))) {
            throw new \InvalidArgumentException('Le mot de passe est obligatoire.');
        }

        // FIX 1: cast to string before mb_strlen to avoid null error
        if (mb_strlen((string) $user->getMot_de_passe()) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        $rolesValides = ['USER', 'ADMIN'];
        if (!in_array($user->getRole(), $rolesValides, true)) {
            throw new \InvalidArgumentException('Le rôle doit être USER ou ADMIN.');
        }

        return true;
    }

    /**
     * Crée un User.
     * @param array<string, mixed> $data
     */
    public function create(array $data): User
    {
        $user = new User();
        $user->setNom($data['nom']);
        $user->setPrenom($data['prenom']);
        $user->setEmail($data['email']);
        $user->setDate_naissance($data['date_naissance']);
        $user->setMot_de_passe($data['mot_de_passe']);
        $user->setRole($data['role'] ?? 'USER');
        $user->setCreated_at(new \DateTime());

        $this->validate($user);

        return $user;
    }

    /**
     * Met à jour un User.
     * @param array<string, mixed> $data
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['nom']))            $user->setNom($data['nom']);
        if (isset($data['prenom']))         $user->setPrenom($data['prenom']);
        if (isset($data['email']))          $user->setEmail($data['email']);
        if (isset($data['date_naissance'])) $user->setDate_naissance($data['date_naissance']);
        if (isset($data['mot_de_passe']))   $user->setMot_de_passe($data['mot_de_passe']);
        if (isset($data['role']))           $user->setRole($data['role']);

        $this->validate($user);

        return $user;
    }

    /**
     * Retourne l'image de profil.
     */
    public function getProfileImage(User $user): string
    {
        return $user->getProfileImage();
    }

    /**
     * Vérifie si le téléphone est correctement formaté.
     */
    public function validateTelephone(string $telephone): bool
    {
        $clean = preg_replace('/\s+/', '', $telephone);
        // FIX 2: cast to string because preg_replace can return null
        if (!preg_match('/^\+?[0-9]{8,15}$/', (string) $clean)) {
            throw new \InvalidArgumentException('Le numéro de téléphone n\'est pas valide.');
        }
        return true;
    }
}