<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminUserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Filesystem;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserAdminController extends AbstractController
{
    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $role = trim((string) $request->query->get('role', ''));
        $sort = trim((string) $request->query->get('sort', 'newest'));

        $allowedSorts = ['newest', 'oldest', 'name_asc', 'name_desc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'newest';
        }

        if (!in_array($role, ['', 'ADMIN', 'USER'], true)) {
            $role = '';
        }

        $users = $userRepository->searchForAdmin($search, $role ?: null, $sort);

        return $this->render('user_admin/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'selectedRole' => $role,
            'selectedSort' => $sort,
        ]);
    }

    #[Route('/new', name: 'app_admin_users_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        $user = new User();
        $user->setRole('USER');
        $user->setCreatedAt(new \DateTime());
        $user->setIsVerified(true);

        $form = $this->createForm(AdminUserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $emailValue = mb_strtolower(trim((string) $user->getEmail()));
            $existingUser = $userRepository->findOneBy(['email' => $emailValue]);
            if ($existingUser) {
                $form->get('email')->addError(new FormError('Cette adresse email est déjà utilisée.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));
            $user->setVerificationCode(null);

            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setMotDePasse($passwordHasher->hashPassword($user, $plainPassword));

            // === PHOTO HANDLING (same as ProfileController) ===
            $photoFile = $form->get('photoFile')->getData();
            $photoUrl = $form->get('photoUrl')->getData();

            if ($photoFile && $photoUrl) {
                $this->addFlash('error', 'Veuillez choisir UNE SEULE méthode : soit uploader un fichier, soit entrer une URL, mais pas les deux.');
                return $this->redirectToRoute('app_admin_users_new');
            }

            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/profiles',
                        $newFilename
                    );
                    $user->setPhotoFileName($newFilename);
                    $user->setPhotoUrl(null);
                } catch (FileException $e) {
                    $this->addFlash('error', 'La photo n’a pas pu être importée.');
                    return $this->redirectToRoute('app_admin_users_new');
                }
            } elseif ($photoUrl) {
                $user->setPhotoUrl($photoUrl);
                $user->setPhotoFileName(null);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'L’utilisateur a été créé avec succès.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('user_admin/form.html.twig', [
            'pageTitle' => 'Ajouter un utilisateur',
            'pageSubtitle' => 'Créez un nouveau compte utilisateur ou administrateur.',
            'form' => $form,
            'userEntity' => $user,
            'isEdit' => false,
            'profileImage' => $user->getProfileImage(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_users_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        $originalEmail = $user->getEmail();
        
        // Store old photo info
        $oldPhotoFileName = $user->getPhotoFileName();
        $oldPhotoUrl = $user->getPhotoUrl();

        $form = $this->createForm(AdminUserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $emailValue = mb_strtolower(trim((string) $user->getEmail()));
            if ($emailValue !== mb_strtolower((string) $originalEmail)) {
                $existingUser = $userRepository->findOneBy(['email' => $emailValue]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $form->get('email')->addError(new FormError('Cette adresse email est déjà utilisée.'));
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));

            $plainPassword = trim((string) $form->get('plainPassword')->getData());
            if ($plainPassword !== '') {
                $user->setMotDePasse($passwordHasher->hashPassword($user, $plainPassword));
            }

            // === PHOTO HANDLING (exactly like ProfileController) ===
            $photoFile = $form->get('photoFile')->getData();
            $photoUrl = $form->get('photoUrl')->getData();

            if ($photoFile && $photoUrl) {
                $this->addFlash('error', 'Veuillez choisir UNE SEULE méthode : soit uploader un fichier, soit entrer une URL, mais pas les deux.');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            if ($photoFile) {
                if ($oldPhotoFileName) {
                    $this->deleteOldProfilePhoto($oldPhotoFileName);
                }

                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/profiles',
                        $newFilename
                    );
                    $user->setPhotoFileName($newFilename);
                    $user->setPhotoUrl(null);
                    $this->addFlash('success', 'Photo de profil mise à jour.');
                } catch (FileException $e) {
                    $this->addFlash('error', 'La photo n’a pas pu être importée.');
                    return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
                }
            } elseif ($photoUrl) {
                if ($photoUrl !== $oldPhotoUrl || $oldPhotoFileName) {
                    if ($oldPhotoFileName) {
                        $this->deleteOldProfilePhoto($oldPhotoFileName);
                    }
                    $user->setPhotoUrl($photoUrl);
                    $user->setPhotoFileName(null);
                    $this->addFlash('success', 'URL de la photo mise à jour.');
                }
            } else {
                if ($oldPhotoFileName) {
                    $this->deleteOldProfilePhoto($oldPhotoFileName);
                }
                $user->setPhotoFileName(null);
                $user->setPhotoUrl(null);
                $this->addFlash('success', 'Photo de profil supprimée.');
            }

            $entityManager->flush();
            $this->addFlash('success', 'L’utilisateur a été modifié avec succès.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('user_admin/form.html.twig', [
            'pageTitle' => 'Modifier un utilisateur',
            'pageSubtitle' => 'Mettez à jour les informations du compte sélectionné.',
            'form' => $form,
            'userEntity' => $user,
            'isEdit' => true,
            'profileImage' => $user->getProfileImage(),
        ]);
    }

    /**
     * Delete old profile photo from filesystem
     */
    private function deleteOldProfilePhoto(?string $fileName): void
    {
        if (!$fileName) {
            return;
        }
        $filesystem = new Filesystem();
        $oldFilePath = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $fileName;
        if ($filesystem->exists($oldFilePath)) {
            try {
                $filesystem->remove($oldFilePath);
            } catch (\Exception $e) {
                // Log error if needed
            }
        }
    }

    #[Route('/{id}/delete', name: 'app_admin_users_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($this->getUser() instanceof User && $this->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte administrateur depuis cette page.');
            return $this->redirectToRoute('app_admin_users');
        }

        // Delete profile photo before removing user
        if ($user->getPhotoFileName()) {
            $this->deleteOldProfilePhoto($user->getPhotoFileName());
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'L’utilisateur a été supprimé avec succès.');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/stats', name: 'app_admin_users_stats', methods: ['GET'])]
    public function stats(UserRepository $userRepository): Response
    {
        $totalUsers = $userRepository->countAllUsers();
        $adminCount = $userRepository->countByRole('ADMIN');
        $userCount = $userRepository->countByRole('USER');

        $adminPercentage = $totalUsers > 0 ? round(($adminCount / $totalUsers) * 100, 1) : 0;
        $userPercentage = $totalUsers > 0 ? round(($userCount / $totalUsers) * 100, 1) : 0;

        $registrationsByDay = $userRepository->getRegistrationsByDay(7);
        $maxRegistrations = !empty($registrationsByDay) ? max($registrationsByDay) : 0;

        return $this->render('user_admin/stats.html.twig', [
            'totalUsers' => $totalUsers,
            'adminCount' => $adminCount,
            'userCount' => $userCount,
            'adminPercentage' => $adminPercentage,
            'userPercentage' => $userPercentage,
            'registrationsByDay' => $registrationsByDay,
            'maxRegistrations' => $maxRegistrations,
        ]);
    }
}