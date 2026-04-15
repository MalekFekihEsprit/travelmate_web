<?php

namespace App\Service;

use App\Entity\Voyage;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

final class VoyageQrCodeFactory
{
    public function createDataUri(Voyage $voyage, string $budgetTotalLabel, int $participantsCount): string
    {
        $destinationLabel = 'Non definie';
        if ($voyage->getDestination() !== null) {
            $destinationLabel = (string) $voyage->getDestination()->getNomDestination();

            $country = $voyage->getDestination()->getPaysDestination();
            if (is_string($country) && $country !== '') {
                $destinationLabel .= ', '.$country;
            }
        }

        $payload = implode("\n", [
            'TravelMate - Informations du voyage',
            'Voyage: '.((string) ($voyage->getTitreVoyage() ?? '-')),
            'Statut: '.((string) ($voyage->getStatut() ?? 'Sans statut')),
            'Destination: '.$destinationLabel,
            'Date de debut: '.($voyage->getDateDebut()?->format('d/m/Y') ?? '-'),
            'Date de fin: '.($voyage->getDateFin()?->format('d/m/Y') ?? '-'),
            'Budget cumule: '.$budgetTotalLabel,
            'Participants: '.$participantsCount,
        ]);

        $qrCode = new QrCode(
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 220,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(36, 27, 23),
            backgroundColor: new Color(255, 253, 250)
        );

        return (new SvgWriter())->write($qrCode)->getDataUri();
    }
}