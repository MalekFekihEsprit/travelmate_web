<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpKernel\KernelInterface;

class SecurityAlertService
{
    public function __construct(
        private MailerInterface $mailer,
        private KernelInterface $kernel
    ) {
    }

    public function saveBase64Photo(?string $base64Image, string $prefix = 'security-alert'): ?string
    {
        if (!$base64Image || !str_starts_with($base64Image, 'data:image/')) {
            return null;
        }

        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
            return null;
        }

        $extension = strtolower($matches[1]);
        $data = substr($base64Image, strpos($base64Image, ',') + 1);
        $decoded = base64_decode($data);

        if ($decoded === false) {
            return null;
        }

        $directory = $this->kernel->getProjectDir() . '/public/uploads/security-alerts';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filename = $prefix . '-' . uniqid() . '.' . $extension;
        $path = $directory . '/' . $filename;

        file_put_contents($path, $decoded);

        return $filename;
    }

    public function sendFailedLoginAlert(User $user, ?string $photoFilename = null): void
    {
        $email = (new Email())
            ->from('travelmate@example.com')
            ->to($user->getEmail())
            ->subject('Alerte sécurité - Tentatives de connexion suspectes')
            ->html("
                <h2>Alerte sécurité</h2>
                <p>Nous avons détecté au moins 3 tentatives de connexion échouées sur votre compte TravelMate.</p>
                <p>Si ce n’était pas vous, nous vous conseillons de changer votre mot de passe immédiatement.</p>
            ");

        if ($photoFilename && file_exists($this->getPhotoPath($photoFilename))) {
            $email->attach(Swift_Attachment::fromPath($this->getPhotoPath($photoFilename)));
            $fullPath = $this->kernel->getProjectDir() . '/public/uploads/security-alerts/' . $photoFilename;

            if (is_file($fullPath)) {
                $email->attachFromPath($fullPath, 'security-alert-photo.jpg');
            }
        }

        $this->mailer->send($email);
    }

    public function sendSuspiciousLoginAlert(User $user, string $currentIp, string $currentCountry): void
    {
        $email = (new Email())
            ->from('travelmate@example.com')
            ->to($user->getEmail())
            ->subject('Alerte sécurité - Connexion inhabituelle')
            ->html("
                <h2>Connexion inhabituelle détectée</h2>
                <p>Une nouvelle connexion a été détectée sur votre compte.</p>
                <p><strong>IP :</strong> {$currentIp}</p>
                <p><strong>Pays :</strong> {$currentCountry}</p>
                <p>Si ce n’était pas vous, changez votre mot de passe immédiatement.</p>
            ");

        $this->mailer->send($email);
    }
}