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
use Stripe\Stripe;
use Stripe\Checkout\Session;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;
    private string $stripeSecretKey;
    private string $stripePublishableKey;
    private string $stripeWebhookSecret;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
        string $stripeSecretKey,
        string $stripePublishableKey,
        string $stripeWebhookSecret
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->stripeSecretKey = $stripeSecretKey;
        $this->stripePublishableKey = $stripePublishableKey;
        $this->stripeWebhookSecret = $stripeWebhookSecret;
    }

    #[Route('/new/{id}', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Activite $activite): Response
    {
        $reservation = new Reservation();
        $reservation->setActivite($activite);

        $montantTotal = $activite->getBudget();
        $acompte = $montantTotal * 0.3;
        $reservation->setMontantTotal($montantTotal);
        $reservation->setAcompte($acompte);

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($reservation->getMethodeConfirmation() === 'sms') {
                $reservation->generateCodeConfirmation();
            }

            $this->entityManager->persist($reservation);
            $this->entityManager->flush();

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

    // Routes with fixed segments MUST be declared BEFORE the generic /payment/{id} route
    #[Route('/payment/create-checkout-session/{id}', name: 'app_reservation_create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(Reservation $reservation): JsonResponse
    {
        try {
            Stripe::setApiKey($this->stripeSecretKey);

            $checkoutSession = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => 'Acompte - ' . $reservation->getActivite()->getNom(),
                            'description' => 'Réservation #' . $reservation->getId(),
                        ],
                        'unit_amount' => (int)($reservation->getAcompte() * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $this->urlGenerator->generate(
                    'app_reservation_payment_success',
                    ['id' => $reservation->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'cancel_url' => $this->urlGenerator->generate(
                    'app_reservation_payment_cancel',
                    ['id' => $reservation->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'metadata' => [
                    'reservation_id' => $reservation->getId(),
                ],
            ]);

            return new JsonResponse([
                'success' => true,
                'sessionId' => $checkoutSession->id
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur Stripe Checkout Session', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservation->getId()
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'Impossible de créer la session de paiement : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/payment/success/{id}', name: 'app_reservation_payment_success', methods: ['GET'])]
    public function paymentSuccess(Reservation $reservation): Response
    {
        $reservation->setStatutPaiement('confirme');
        $reservation->setDatePaiement(new \DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', 'Paiement effectué avec succès !');

        return $this->redirectToRoute('app_reservation_confirmation', ['id' => $reservation->getId()]);
    }

    #[Route('/payment/cancel/{id}', name: 'app_reservation_payment_cancel', methods: ['GET'])]
    public function paymentCancel(Reservation $reservation): Response
    {
        $this->addFlash('error', 'Le paiement a été annulé. Vous pouvez réessayer.');

        return $this->redirectToRoute('app_reservation_payment', ['id' => $reservation->getId()]);
    }

    // Generic route LAST so it doesn't intercept the routes above
    #[Route('/payment/{id}', name: 'app_reservation_payment', methods: ['GET'])]
    public function payment(Reservation $reservation): Response
    {
        if ($reservation->getStatutPaiement() === 'confirme') {
            return $this->redirectToRoute('app_reservation_confirmation', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/payment.html.twig', [
            'reservation' => $reservation,
            'stripePublishableKey' => $this->stripePublishableKey
        ]);
    }

    #[Route('/confirmation/{id}', name: 'app_reservation_confirmation', methods: ['GET', 'POST'])]
    public function confirmation(Request $request, Reservation $reservation): Response
    {
        if ($request->isMethod('POST')) {
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

    #[Route('/webhook/stripe', name: 'app_stripe_webhook', methods: ['POST'])]
    public function stripeWebhook(Request $request): Response
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->headers->get('stripe-signature');

            Stripe::setApiKey($this->stripeSecretKey);

            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $this->stripeWebhookSecret
            );

            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;
                    $reservationId = $session->metadata->reservation_id;

                    $reservation = $this->entityManager->find(Reservation::class, $reservationId);
                    if ($reservation) {
                        $reservation->setStatutPaiement('confirme');
                        $reservation->setDatePaiement(new \DateTime());
                        $reservation->setTransactionId($session->payment_intent);
                        $this->entityManager->flush();

                        $this->logger->info('Paiement Stripe confirmé via webhook', [
                            'reservation_id' => $reservationId,
                            'session_id' => $session->id
                        ]);
                    }
                    break;
            }

            return new Response('Webhook traité', 200);

        } catch (\Exception $e) {
            $this->logger->error('Erreur webhook Stripe', [
                'error' => $e->getMessage()
            ]);
            return new Response('Erreur webhook', 400);
        }
    }

    private function sendEmailConfirmation(Reservation $reservation): void
    {
        $gmailEmail = $_ENV['GMAIL_EMAIL'] ?? '';
        $appPassword = $_ENV['GMAIL_APP_PASSWORD'] ?? '';

        if (empty($gmailEmail) || empty($appPassword)) {
            $this->logger->info('Email de réservation à envoyer', [
                'to' => $reservation->getEmail(),
                'subject' => 'Confirmation de réservation - ' . $reservation->getActivite()->getNom(),
                'reservation_id' => $reservation->getId()
            ]);
            $this->addFlash('info', 'Email de confirmation enregistré (mode développement - Gmail non configuré)');
            return;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $gmailEmail;
            $mail->Password = $appPassword;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom($gmailEmail, 'TravelMate');
            $mail->addAddress($reservation->getEmail(), $reservation->getNomComplet());
            $mail->addReplyTo($gmailEmail, 'TravelMate');

            $mail->isHTML(true);
            $mail->Subject = 'Confirmation de réservation - ' . $reservation->getActivite()->getNom();
            $mail->Body = $this->renderEmailTemplate($reservation);
            $mail->AltBody = $this->renderEmailTextTemplate($reservation);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->send();

            $this->addFlash('success', 'Email de confirmation envoyé avec succès via Gmail');
            $this->logger->info('Email envoyé avec succès via Gmail', [
                'reservation_id' => $reservation->getId(),
                'to' => $reservation->getEmail()
            ]);

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage());
            $this->logger->error('Erreur PHPMailer Gmail', [
                'error' => $e->getMessage(),
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
                        <p>Présentez ce QR code le jour de l'activité :</p>
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

Nous vous attendons avec impatience pour cette aventure !

Cordialement,
L'équipe TravelMate
        ";
    }

    private function sendSMSConfirmation(Reservation $reservation): void
    {
        $apiKey = $_ENV['SMS_API_KEY'] ?? '';

        if (empty($apiKey)) {
            $this->logger->info('SMS à envoyer', [
                'to' => $reservation->getTelephone(),
                'code' => $reservation->getCodeConfirmation(),
                'activite' => $reservation->getActivite()->getNom()
            ]);
            $this->addFlash('info', "SMS de confirmation (développement): Code {$reservation->getCodeConfirmation()}");
            return;
        }

        $message = "TravelMate: Votre code de confirmation pour {$reservation->getActivite()->getNom()} est {$reservation->getCodeConfirmation()}";

        try {
            $url = "https://api.sms-api.com/sms/send";
            $data = [
                'to' => $this->formatPhoneNumberForSMS($reservation->getTelephone()),
                'message' => $message,
                'api_key' => $apiKey
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $this->addFlash('success', 'SMS de confirmation envoyé avec succès');
                $this->logger->info('SMS envoyé avec succès', [
                    'to' => $reservation->getTelephone(),
                    'code' => $reservation->getCodeConfirmation()
                ]);
            } else {
                $this->addFlash('warning', 'Erreur lors de l\'envoi du SMS');
                $this->logger->error('Erreur SMS API', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi du SMS: ' . $e->getMessage());
            $this->logger->error('Exception SMS', [
                'error' => $e->getMessage(),
                'to' => $reservation->getTelephone()
            ]);
        }
    }

    private function formatPhoneNumberForSMS(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 8) {
            return '+216' . $phone;
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return '+216' . substr($phone, 1);
        }

        return $phone;
    }

    private function sendWhatsAppConfirmation(Reservation $reservation): void
    {
        $accessToken = $_ENV['WHATSAPP_ACCESS_TOKEN'] ?? '';
        $phoneNumberId = $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? '';
        $version = $_ENV['WHATSAPP_API_VERSION'] ?? 'v18.0';

        if (empty($accessToken) || empty($phoneNumberId)) {
            $this->logger->info('WhatsApp à envoyer', [
                'to' => $reservation->getTelephone(),
                'code' => $reservation->getCodeConfirmation(),
                'activite' => $reservation->getActivite()->getNom()
            ]);
            $this->addFlash('info', "Code WhatsApp (développement): {$reservation->getCodeConfirmation()}");
            return;
        }

        try {
            $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhoneNumberForWhatsApp($reservation->getTelephone()),
                'type' => 'template',
                'template' => [
                    'name' => 'travelmate_confirmation',
                    'language' => ['code' => 'fr'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $reservation->getActivite()->getNom()],
                            ['type' => 'text', 'text' => $reservation->getCodeConfirmation()],
                            ['type' => 'text', 'text' => $reservation->getDateReservation()->format('d/m/Y H:i')]
                        ]
                    ]]
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $this->addFlash('success', 'Message WhatsApp envoyé avec succès');
                $this->logger->info('WhatsApp envoyé avec succès', [
                    'to' => $reservation->getTelephone(),
                    'code' => $reservation->getCodeConfirmation()
                ]);
            } else {
                $this->addFlash('warning', 'Erreur lors de l\'envoi du message WhatsApp');
                $this->logger->error('Erreur WhatsApp API', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi du message WhatsApp: ' . $e->getMessage());
            $this->logger->error('Exception WhatsApp', [
                'error' => $e->getMessage(),
                'to' => $reservation->getTelephone()
            ]);
        }
    }

    private function formatPhoneNumberForWhatsApp(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 8 && str_starts_with($phone, '0')) {
            return '216' . substr($phone, 1);
        }

        if (strlen($phone) === 8) {
            return '216' . $phone;
        }

        return $phone;
    }
}
