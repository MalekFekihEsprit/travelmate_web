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
            'stripePublishableKey' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? 'pk_test_placeholder'
        ]);
    }

    #[Route('/payment/create-checkout-session/{id}', name: 'app_reservation_create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(Reservation $reservation): JsonResponse
    {
        try {
            // Configurer Stripe avec la clé secrète
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            // Créer une session de paiement Stripe Checkout
            $checkoutSession = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur', // ou 'tnd' si supporté
                        'product_data' => [
                            'name' => 'Acompte - ' . $reservation->getActivite()->getNom(),
                            'description' => 'Réservation #' . $reservation->getId(),
                        ],
                        'unit_amount' => (int)($reservation->getAcompte() * 100), // Stripe utilise les centimes
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $this->urlGenerator->generate('app_reservation_payment_success', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->urlGenerator->generate('app_reservation_payment_cancel', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
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
                'error' => 'Impossible de créer la session de paiement'
            ], 500);
        }
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

    
    #[Route('/payment/success/{id}', name: 'app_reservation_payment_success', methods: ['GET'])]
    public function paymentSuccess(Reservation $reservation): Response
    {
        // Marquer le paiement comme confirmé
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

    #[Route('/webhook/stripe', name: 'app_stripe_webhook', methods: ['POST'])]
    public function stripeWebhook(Request $request): Response
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->headers->get('stripe-signature');
            
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
            
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $_ENV['STRIPE_WEBHOOK_SECRET']
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
                        
                        $this->logger->info('Paiement Stripe confirmé', [
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

    private function sendWhatsAppConfirmation(Reservation $reservation): void
    {
        // Utiliser l'API WhatsApp Business (gratuite pour commencer)
        $accessToken = $_ENV['WHATSAPP_ACCESS_TOKEN'] ?? '';
        $phoneNumberId = $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? '';
        $version = $_ENV['WHATSAPP_API_VERSION'] ?? 'v18.0';
        
        if (empty($accessToken) || empty($phoneNumberId)) {
            // Fallback : logger pour le développement et afficher le code
            $this->logger->info('WhatsApp à envoyer', [
                'to' => $reservation->getTelephone(),
                'code' => $reservation->getCodeConfirmation(),
                'activite' => $reservation->getActivite()->getNom()
            ]);
            
            // Afficher le code à l'utilisateur pour le développement
            $this->addFlash('info', "Code WhatsApp (développement): {$reservation->getCodeConfirmation()}");
            return;
        }
        
        // Préparer le message WhatsApp avec formatage
        $message = $this->createWhatsAppMessage($reservation);
        
        try {
            // Utiliser WhatsApp Business API
            $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
            
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhoneNumberForWhatsApp($reservation->getTelephone()),
                'type' => 'template',
                'template' => [
                    'name' => 'travelmate_confirmation',
                    'language' => [
                        'code' => 'fr'
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $reservation->getActivite()->getNom()
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $reservation->getCodeConfirmation()
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $reservation->getDateReservation()->format('d/m/Y H:i')
                                ]
                            ]
                        ]
                    ]
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
                    'code' => $reservation->getCodeConfirmation(),
                    'response' => $response
                ]);
            } else {
                $this->addFlash('warning', 'Erreur lors de l\'envoi du message WhatsApp');
                $this->logger->error('Erreur WhatsApp API', [
                    'http_code' => $httpCode,
                    'response' => $response,
                    'to' => $reservation->getTelephone()
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
    
    private function createWhatsAppMessage(Reservation $reservation): string
    {
        return "🎉 *TravelMate - Confirmation de Réservation* 🎉

📋 *Détails de la réservation :*
• Activité : {$reservation->getActivite()->getNom()}
• Code de confirmation : *{$reservation->getCodeConfirmation()}*
• Date : {$reservation->getDateReservation()->format('d/m/Y à H:i')}

🔐 *Conservez précieusement ce code de 5 chiffres !*

📞 Pour toute question, contactez notre support.

*Bon voyage avec TravelMate !* ✈️";
    }
    
    private function formatPhoneNumberForWhatsApp(string $phone): string
    {
        // Formater le numéro pour WhatsApp (format international)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Si le numéro commence par 0, remplacer par +216 pour la Tunisie
        if (strlen($phone) === 8 && str_starts_with($phone, '0')) {
            return '216' . substr($phone, 1);
        }
        
        // Si le numéro n'a pas de préfixe international, ajouter +216
        if (strlen($phone) === 8) {
            return '216' . $phone;
        }
        
        return $phone;
    }
}
