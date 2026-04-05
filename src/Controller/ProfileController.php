<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\DeleteAccountFormType;
use App\Form\ProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Filesystem\Filesystem;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $baseTemplate = $user && in_array('ROLE_ADMIN', $user->getRoles()) ? 'base_admin.html.twig' : 'base.html.twig';
        // Store old photo info before form handling
        $oldPhotoFileName = $user->getPhotoFileName();
        $oldPhotoUrl = $user->getPhotoUrl();

        $profileForm = $this->createForm(ProfileFormType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $photoFile = $profileForm->get('photoFile')->getData();
            $photoUrl = $profileForm->get('photoUrl')->getData();
            // Enforce only one method
            if ($photoFile && $photoUrl) {
                $this->addFlash('error', 'Veuillez choisir UNE SEULE méthode : soit uploader un fichier, soit entrer une URL, mais pas les deux.');
                return $this->redirectToRoute('app_profile');
            }
            // Case 1: User uploaded a new file (priority over URL)
            if ($photoFile) {
                // Delete old uploaded file if exists
                if ($oldPhotoFileName) {
                    $this->deleteOldProfilePhoto($oldPhotoFileName);
                }
                // Upload new file
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();
                try {
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/profiles',
                        $newFilename
                    );
                    $user->setPhotoFileName($newFilename);
                    $user->setPhotoUrl(null); // Clear URL when file is uploaded
                    
                    $this->addFlash('success', 'Photo de profil mise à jour avec succès.');
                } catch (FileException $e) {
                    $this->addFlash('error', 'La photo n’a pas pu être importée.');
                    return $this->redirectToRoute('app_profile');
                }
            } 
            // Case 2: User provided a URL (and no file uploaded)
            elseif ($photoUrl) {
                // If URL is different from old URL or if there was a file before
                if ($photoUrl !== $oldPhotoUrl || $oldPhotoFileName) {
                    // Delete old uploaded file if exists (since URL takes precedence)
                    if ($oldPhotoFileName) {
                        $this->deleteOldProfilePhoto($oldPhotoFileName);
                    }
                    $user->setPhotoUrl($photoUrl);
                    $user->setPhotoFileName(null); // Clear filename when URL is used
                    $this->addFlash('success', 'URL de la photo de profil mise à jour avec succès.');
                }
            }
            // Case 3: Both fields are empty - user wants to remove photo
            else {
                // Delete old uploaded file if exists
                if ($oldPhotoFileName) {
                    $this->deleteOldProfilePhoto($oldPhotoFileName);
                }
                $user->setPhotoFileName(null);
                $user->setPhotoUrl(null);
                $this->addFlash('success', 'Photo de profil supprimée avec succès.');
            }
            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        }
        $changePasswordForm = $this->createForm(ChangePasswordFormType::class, null, [
            'action' => $this->generateUrl('app_profile_change_password'),
            'method' => 'POST',
        ]);
        $deleteAccountForm = $this->createForm(DeleteAccountFormType::class, null, [
            'action' => $this->generateUrl('app_profile_delete'),
            'method' => 'POST',
        ]);
        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'base_template' => $baseTemplate,
            'profileForm' => $profileForm,
            'changePasswordForm' => $changePasswordForm,
            'deleteAccountForm' => $deleteAccountForm
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
        $oldFilePath = $this->getParameter('kernel.project_dir').'/public/uploads/profiles/'.$fileName;
        if ($filesystem->exists($oldFilePath)) {
            try {
                $filesystem->remove($oldFilePath);
            } catch (\Exception $e) {
                // Log error but don't stop the process
                // You can add logging here if needed
            }
        }
    }


    #[Route('/profile/change-password', name: 'app_profile_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = (string) $form->get('currentPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_profile');
            }

            $newHashedPassword = $passwordHasher->hashPassword(
                $user,
                (string) $form->get('newPassword')->getData()
            );

            $user->setMotDePasse($newHashedPassword);
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        $this->addFlash('error', 'Impossible de modifier le mot de passe. Vérifiez les champs saisis.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage 
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(DeleteAccountFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = (string) $form->get('password')->getData();

            if (!$passwordHasher->isPasswordValid($user, $password)) {
                $this->addFlash('error', 'Mot de passe incorrect. Suppression annulée.');
                return $this->redirectToRoute('app_profile');
            }
            // Delete profile photo before removing user
            if ($user->getPhotoFileName()) {
                $this->deleteOldProfilePhoto($user->getPhotoFileName());
            }
            // Clear the security token BEFORE deleting
            $tokenStorage->setToken(null);
            // Invalidate session
            $request->getSession()->invalidate();

            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte a été supprimé avec succès.');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('error', 'Impossible de supprimer le compte. Vérifiez les informations fournies.');
        return $this->redirectToRoute('app_profile');
    }
}