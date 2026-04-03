<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\VerifyEmailCodeType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        SluggerInterface $slugger,
        MailerInterface $mailer
    ): Response {
        $user = new User();

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $emailValue = mb_strtolower(trim((string) $user->getEmail()));

            if ($userRepository->findOneBy(['email' => $emailValue])) {
                $form->get('email')->addError(new FormError('Cette adresse email est déjà utilisée.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));
            $user->setRole('USER');
            $user->setCreatedAt(new \DateTime());
            $user->setIsVerified(false);

            $verificationCode = $this->generateVerificationCode();
            $user->setVerificationCode($verificationCode);

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                (string) $form->get('plainPassword')->getData()
            );
            $user->setMotDePasse($hashedPassword);

            $photoFile = $form->get('photoFile')->getData();

            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/profiles',
                        $newFilename
                    );
                    $user->setPhotoFileName($newFilename);

                    if (method_exists($user, 'setPhotoUrl')) {
                        $user->setPhotoUrl(null);
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'La photo n’a pas pu être importée.');
                }
            } else {
                if (!$user->getPhotoUrl()) {
                    $user->setPhotoUrl(null);
                }
            }

            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $this->sendVerificationEmail($mailer, $user, $verificationCode);
                $this->addFlash('success', 'Un code de vérification a été envoyé à votre adresse email.');
            } catch (TransportExceptionInterface $e) {
                $this->addFlash('warning', 'Compte créé, mais l’email n’a pas pu être envoyé. Vérifiez votre configuration mailer.');
            }

            return $this->redirectToRoute('app_verify_email', ['id' => $user->getId()]);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify-email/{id}', name: 'app_verify_email', requirements: ['id' => '\d+'])]
    public function verifyEmail(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Votre compte est déjà vérifié.');
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(VerifyEmailCodeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedCode = trim((string) $form->get('code')->getData());

            if ($submittedCode === (string) $user->getVerificationCode()) {
                $user->setIsVerified(true);
                $user->setVerificationCode(null);

                $entityManager->flush();

                $this->addFlash('success', 'Votre compte a été vérifié avec succès. Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_home');
            }

            $form->get('code')->addError(new FormError('Le code saisi est invalide.'));
        }

        return $this->render('registration/verify_email.html.twig', [
            'verifyForm' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/verify-email/{id}/resend', name: 'app_resend_verification_code', requirements: ['id' => '\d+'])]
    public function resendVerificationCode(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $user = $userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Ce compte est déjà vérifié.');
            return $this->redirectToRoute('app_home');
        }

        $newCode = $this->generateVerificationCode();
        $user->setVerificationCode($newCode);
        $entityManager->flush();

        try {
            $this->sendVerificationEmail($mailer, $user, $newCode);
            $this->addFlash('success', 'Un nouveau code vous a été envoyé.');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Impossible de renvoyer l’email pour le moment.');
        }

        return $this->redirectToRoute('app_verify_email', ['id' => $user->getId()]);
    }

    private function generateVerificationCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function sendVerificationEmail(MailerInterface $mailer, User $user, string $verificationCode): void
    {
        $email = (new Email())
            ->from('no-reply@travelmate.local')
            ->to($user->getEmail())
            ->subject('TravelMate - Vérification de votre compte')
            ->html(sprintf(
                '
                <div style="font-family: Arial, sans-serif; line-height:1.6; color:#1f1a17;">
                    <h2 style="color:#c46f4b;">Bienvenue sur TravelMate 🌍</h2>
                    <p>Bonjour %s,</p>
                    <p>Merci pour votre inscription. Voici votre code de vérification :</p>
                    <div style="font-size:32px; font-weight:bold; letter-spacing:6px; color:#2f7f79; margin:24px 0;">
                        %s
                    </div>
                    <p>Saisissez ce code sur la page de vérification pour activer votre compte.</p>
                    <p style="color:#71665c;">Si vous n’êtes pas à l’origine de cette inscription, ignorez simplement ce message.</p>
                </div>
                ',
                htmlspecialchars((string) $user->getPrenom().' '.(string) $user->getNom()),
                htmlspecialchars($verificationCode)
            ));

        $mailer->send($email);
    }
}