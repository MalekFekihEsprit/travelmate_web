<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Activite;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    #[Route('/new/{id}', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Activite $activite): Response
    {
        $reservation = new Reservation();
        $reservation->setActivite($activite);
        
        // Calcul du montant (30% d'acompte par défaut)
        $montantTotal = $activite->getBudget();
        $acompte = $montantTotal * 0.3;
        $reservation->setMontantTotal($montantTotal);
        $reservation->setAcompte($acompte);

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer le code de confirmation si méthode SMS
            if ($reservation->getMethodeConfirmation() === 'sms') {
                $reservation->generateCodeConfirmation();
            }

            $this->entityManager->persist($reservation);
            $this->entityManager->flush();

            // Rediriger vers la page de paiement
            return $this->redirectToRoute('app_reservation_payment', [
                'id' => $reservation->getId()
            ]);
        }

        return $this->render('reservation/new.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
            'acompte' => $acompte,
            'montantTotal' => $montantTotal
        ]);
    }

    #[Route('/payment/{id}', name: 'app_reservation_payment', methods: ['GET'])]
    public function payment(Reservation $reservation): Response
    {
        if ($reservation->getStatutPaiement() === 'confirme') {
            return $this->redirectToRoute('app_reservation_confirmation', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/payment.html.twig', [
            'reservation' => $reservation,
            'paypalClientId' => $_ENV['PAYPAL_CLIENT_ID'] ?? 'test'
        ]);
    }

    #[Route('/payment/process/{id}', name: 'app_reservation_payment_process', methods: ['POST'])]
    public function processPayment(Request $request, Reservation $reservation): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Simuler le traitement PayPal (en production, intégrer l'API PayPal réelle)
        if (isset($data['paymentId']) && isset($data['status']) && $data['status'] === 'completed') {
            $reservation->setStatutPaiement('confirme');
            $reservation->setDatePaiement(new \DateTime());
            $reservation->setTransactionId($data['paymentId']);
            
            $this->entityManager->flush();

            // Le QR code sera généré à la demande dans QRCodeController

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->urlGenerator->generate('app_reservation_confirmation', ['id' => $reservation->getId()])
            ]);
        }

        return new JsonResponse(['success' => false], 400);
    }

    #[Route('/confirmation/{id}', name: 'app_reservation_confirmation', methods: ['GET', 'POST'])]
    public function confirmation(Request $request, Reservation $reservation): Response
    {
        if ($request->isMethod('POST')) {
            // Envoyer la confirmation selon la méthode choisie
            if ($reservation->getMethodeConfirmation() === 'email') {
                $this->sendEmailConfirmation($reservation);
            } elseif ($reservation->getMethodeConfirmation() === 'sms') {
                $this->sendSMSConfirmation($reservation);
            }

            $reservation->setDateConfirmation(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', 'Confirmation envoyée avec succès !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('reservation/confirmation.html.twig', [
            'reservation' => $reservation
        ]);
    }

    
    private function sendEmailConfirmation(Reservation $reservation): void
    {
        // Utiliser Gmail avec mots de passe d'application
        $gmailEmail = $_ENV['GMAIL_EMAIL'] ?? '';
        $appPassword = $_ENV['GMAIL_APP_PASSWORD'] ?? '';
        
        if (empty($gmailEmail) || empty($appPassword)) {
            // Fallback pour le développement : logger l'email et simuler l'envoi
            $this->logger->info('Email de réservation à envoyer', [
                'to' => $reservation->getEmail(),
                'subject' => 'Confirmation de réservation - ' . $reservation->getActivite()->getNom(),
                'reservation_id' => $reservation->getId()
            ]);
            
            $this->addFlash('info', 'Email de confirmation enregistré (mode développement - Gmail non configuré)');
            return;
        }
        
        // Utiliser PHPMailer avec Gmail
        try {
            // Import PHPMailer (vous devrez l'installer avec: composer require phpmailer/phpmailer)
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuration SMTP de Gmail
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $gmailEmail;
            $mail->Password = $appPassword;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            
            // Configuration de l'email
            $mail->setFrom($gmailEmail, 'TravelMate');
            $mail->addAddress($reservation->getEmail(), $reservation->getNomComplet());
            $mail->addReplyTo($gmailEmail, 'TravelMate');
            
            $mail->isHTML(true);
            $mail->Subject = 'Confirmation de réservation - ' . $reservation->getActivite()->getNom();
            $mail->Body = $this->renderEmailTemplate($reservation);
            $mail->AltBody = $this->renderEmailTextTemplate($reservation);
            
            // Configuration charset et encoding
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            $mail->send();
            
            $this->addFlash('success', 'Email de confirmation envoyé avec succès via Gmail');
            $this->logger->info('Email envoyé avec succès via Gmail', [
                'reservation_id' => $reservation->getId(),
                'to' => $reservation->getEmail(),
                'from' => $gmailEmail
            ]);
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage());
            $this->logger->error('Erreur PHPMailer Gmail', [
                'error' => $e->getMessage(),
                'smtp_error' => $e->getErrorInfo(),
                'reservation_id' => $reservation->getId()
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage());
            $this->logger->error('Exception email', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservation->getId()
            ]);
        }
    }
    
    private function renderEmailTemplate(Reservation $reservation): string
    {
        $qrCodeUrl = 'http://127.0.0.1:8000' . $this->urlGenerator->generate('app_qr_display', ['id' => $reservation->getId()]);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmation de réservation - TravelMate</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #c46f4b; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .qr-section { text-align: center; padding: 20px; background: #e8f5e8; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>TravelMate</h1>
                    <h2>Confirmation de réservation</h2>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>{$reservation->getPrenom()} {$reservation->getNom()}</strong>,</p>
                    <p>Votre réservation pour l'activité <strong>{$reservation->getActivite()->getNom()}</strong> a été confirmée avec succès !</p>
                    
                    <div class='details'>
                        <h3>Détails de la réservation :</h3>
                        <p><strong>ID Réservation :</strong> #{$reservation->getId()}</p>
                        <p><strong>Activité :</strong> {$reservation->getActivite()->getNom()}</p>
                        <p><strong>Date :</strong> {$reservation->getDateReservation()->format('d/m/Y H:i')}</p>
                        <p><strong>Montant total :</strong> {$reservation->getMontantTotal()} DT</p>
                        <p><strong>Acompte versé :</strong> {$reservation->getAcompte()} DT</p>
                    </div>
                    
                    <div class='qr-section'>
                        <h3>QR Code de confirmation</h3>
                        <p>Présentez ce QR code le jour de l'activité pour confirmer votre réservation :</p>
                        <img src='{$qrCodeUrl}' alt='QR Code de réservation' style='max-width: 200px; height: auto;'>
                        <p><a href='{$qrCodeUrl}'>Voir le QR code</a></p>
                    </div>
                    
                    <p>Nous vous attendons avec impatience pour cette aventure !</p>
                </div>
                <div class='footer'>
                    <p>Cordialement,<br>L'équipe TravelMate</p>
                    <p>Cet email a été généré automatiquement. Merci de ne pas répondre.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function renderEmailTextTemplate(Reservation $reservation): string
    {
        $qrCodeUrl = 'http://127.0.0.1:8000' . $this->urlGenerator->generate('app_qr_display', ['id' => $reservation->getId()]);
        
        return "
Bonjour {$reservation->getPrenom()} {$reservation->getNom()},

Votre réservation pour l'activité '{$reservation->getActivite()->getNom()}' a été confirmée avec succès !

Détails de la réservation :
- ID Réservation : #{$reservation->getId()}
- Activité : {$reservation->getActivite()->getNom()}
- Date : {$reservation->getDateReservation()->format('d/m/Y H:i')}
- Montant total : {$reservation->getMontantTotal()} DT
- Acompte versé : {$reservation->getAcompte()} DT

QR Code de confirmation : {$qrCodeUrl}

Présentez ce QR code le jour de l'activité pour confirmer votre réservation.

Nous vous attendons avec impatience pour cette aventure !

Cordialement,
L'équipe TravelMate
        ";
    }

    private function sendSMSConfirmation(Reservation $reservation): void
    {
        // Utiliser l'API Twilio (essai gratuit disponible)
        $accountSid = $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
        $authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        $twilioNumber = $_ENV['TWILIO_PHONE_NUMBER'] ?? '';
        
        if (empty($accountSid) || empty($authToken)) {
            // Fallback : logger pour le développement
            error_log("SMS à envoyer à {$reservation->getTelephone()}: Code {$reservation->getCodeConfirmation()}");
            return;
        }

        $message = "
            TravelMate: Réservation confirmée pour {$reservation->getActivite()->getNom()}.
            Code de confirmation: {$reservation->getCodeConfirmation()}
            Date: {$reservation->getDateReservation()->format('d/m/Y')}
        ";

        // En production, intégrer l'API Twilio réelle
        // $client = new Client($accountSid, $authToken);
        // $client->messages->create(
        //     $reservation->getTelephone(),
        //     [
        //         'from' => $twilioNumber,
        //         'body' => $message
        //     ]
        // );
    }
}
