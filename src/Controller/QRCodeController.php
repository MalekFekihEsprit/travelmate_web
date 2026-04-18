<?php

namespace App\Controller;

use App\Entity\Reservation;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
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
        if (!$reservation->getQrCodePath()) {
            $this->generateQRCode($reservation);
            $this->entityManager->flush();
        }

        return $this->render('reservation/qr_display.html.twig', [
            'reservation' => $reservation
        ]);
    }

    private function generateQRCode(Reservation $reservation): void
    {
        $qrData = json_encode([
            'reservation_id' => $reservation->getId(),
            'email'          => $reservation->getEmail(),
            'activite'       => $reservation->getActivite()->getNom(),
            'date'           => $reservation->getDateReservation()->format('Y-m-d H:i:s')
        ]);

        $qrCode = new QrCode(
            data: $qrData,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 10,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/qrcodes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = 'reservation_' . $reservation->getId() . '.png';
        $result->saveToFile($uploadDir . $filename);

        $reservation->setQrCodePath('qrcodes/' . $filename);
    }
}