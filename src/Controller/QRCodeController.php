<?php

namespace App\Controller;

use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/qr')]
class QRCodeController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    #[Route('/display/{id}', name: 'app_qr_display', methods: ['GET'])]
    public function display(Reservation $reservation): Response
    {
        // Générer le QR code s'il n'existe pas
        if (!$reservation->getQrCodePath()) {
            $this->generateQRCode($reservation);
            
            // Sauvegarder en base de données
            $this->entityManager->flush();
        }

        return $this->render('reservation/qr_display.html.twig', [
            'reservation' => $reservation
        ]);
    }
    
    private function generateQRCode(Reservation $reservation): void
    {
        // Générer un QR code unique pour la réservation
        $qrData = json_encode([
            'reservation_id' => $reservation->getId(),
            'email' => $reservation->getEmail(),
            'activite' => $reservation->getActivite()->getNom(),
            'date' => $reservation->getDateReservation()->format('Y-m-d H:i:s')
        ]);

        // En utilisant l'API QR Code Studio (gratuite)
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qrData);
        $qrCodePath = 'qrcodes/reservation_' . $reservation->getId() . '.png';
        
        // Télécharger et sauvegarder l'image
        $qrContent = file_get_contents($qrCodeUrl);
        if ($qrContent) {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/qrcodes/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            file_put_contents($uploadDir . 'reservation_' . $reservation->getId() . '.png', $qrContent);
            $reservation->setQrCodePath($qrCodePath);
        }
    }
}
