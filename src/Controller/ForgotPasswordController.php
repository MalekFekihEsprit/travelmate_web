<?php

namespace App\Controller;

use App\Form\ForgotPasswordRequestType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function request(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $form = $this->createForm(ForgotPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $emailValue = mb_strtolower(trim((string) $form->get('email')->getData()));
            $user = $userRepository->findOneBy(['email' => $emailValue]);

            if (!$user) {
                $this->addFlash('success', 'Si un compte existe avec cette adresse email, un code de réinitialisation a été envoyé.');
                return $this->redirectToRoute('app_reset_password', ['email' => $emailValue]);
            }

            $resetCode = $this->generateCode();
            $user->setVerificationCode($resetCode);
            $entityManager->flush();

            try {
                $this->sendResetCodeEmail($mailer, $user->getEmail(), (string) $user->getPrenom(), $resetCode);
            } catch (TransportExceptionInterface $e) {
                $this->addFlash('error', 'Impossible d’envoyer l’email pour le moment. Vérifiez votre configuration Mailer.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $this->addFlash('success', 'Si un compte existe avec cette adresse email, un code de réinitialisation a été envoyé.');

            return $this->redirectToRoute('app_reset_password', [
                'email' => $emailValue,
            ]);
        }

        return $this->render('security/forgot_password.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function reset(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer
    ): Response {
        $emailValue = mb_strtolower(trim((string) $request->query->get('email', '')));
        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedCode = trim((string) $form->get('code')->getData());

            $user = $userRepository->findOneBy(['email' => $emailValue]);

            if (!$user) {
                $form->addError(new FormError('Aucun utilisateur correspondant n’a été trouvé.'));
            } elseif ((string) $user->getVerificationCode() !== $submittedCode) {
                $form->get('code')->addError(new FormError('Le code de réinitialisation est invalide.'));
            } else {
                $newHashedPassword = $passwordHasher->hashPassword(
                    $user,
                    (string) $form->get('plainPassword')->getData()
                );

                $user->setMotDePasse($newHashedPassword);
                $user->setVerificationCode(null);

                $entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form,
            'email' => $emailValue,
        ]);
    }

    #[Route('/reset-password/resend', name: 'app_reset_password_resend')]
    public function resend(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $emailValue = mb_strtolower(trim((string) $request->query->get('email', '')));
        $user = $userRepository->findOneBy(['email' => $emailValue]);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $newCode = $this->generateCode();
        $user->setVerificationCode($newCode);
        $entityManager->flush();

        try {
            $this->sendResetCodeEmail($mailer, $user->getEmail(), (string) $user->getPrenom(), $newCode);
            $this->addFlash('success', 'Un nouveau code de réinitialisation vous a été envoyé.');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Impossible de renvoyer l’email pour le moment.');
        }

        return $this->redirectToRoute('app_reset_password', [
            'email' => $emailValue,
        ]);
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function sendResetCodeEmail(
        MailerInterface $mailer,
        string $emailAddress,
        string $firstName,
        string $code
    ): void {
        $email = (new Email())
            ->from('no-reply@travelmate.local')
            ->to($emailAddress)
            ->subject('TravelMate - Réinitialisation du mot de passe')
            ->html(sprintf(
                '
                <div style="font-family: Arial, sans-serif; line-height:1.6; color:#1f1a17;">
                    <h2 style="color:#c46f4b;">Réinitialisation du mot de passe 🔐</h2>
                    <p>Bonjour %s,</p>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
                    <p>Voici votre code de réinitialisation :</p>
                    <div style="font-size:32px; font-weight:bold; letter-spacing:6px; color:#2f7f79; margin:24px 0;">
                        %s
                    </div>
                    <p>Saisissez ce code sur la page de réinitialisation pour choisir un nouveau mot de passe.</p>
                    <p style="color:#71665c;">Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet email.</p>
                </div>
                ',
                htmlspecialchars($firstName),
                htmlspecialchars($code)
            ));

        $mailer->send($email);
    }
}