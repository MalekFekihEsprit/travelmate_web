<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/voyages', name: 'app_voyages')]
    public function voyage(): Response
    {
        // Données des voyages (à remplacer par vos vraies données plus tard)
        $voyages = [
            [
                'id' => 1,
                'destination' => 'Paris',
                'pays' => 'France',
                'continent' => 'Europe',
                'prix' => 1200,
                'duree' => 7,
                'inclus' => 'Vol + Hôtel',
                'description' => 'Découvrez la ville lumière, ses monuments emblématiques et sa gastronomie.',
                'emoji' => '🗼',
                'etoiles' => '⭐⭐⭐⭐⭐',
                'gradient_start' => '#FF6B6B',
                'gradient_end' => '#EE5A24'
            ],
            [
                'id' => 2,
                'destination' => 'Tokyo',
                'pays' => 'Japon',
                'continent' => 'Asie',
                'prix' => 2500,
                'duree' => 10,
                'inclus' => 'Vol + Hôtel + Petit déj',
                'description' => 'Vivez une expérience unique au Japon entre tradition et modernité.',
                'emoji' => '🗻',
                'etoiles' => '⭐⭐⭐⭐⭐',
                'gradient_start' => '#4ECDC4',
                'gradient_end' => '#2C3E50'
            ],
            [
                'id' => 3,
                'destination' => 'New York',
                'pays' => 'USA',
                'continent' => 'Amérique',
                'prix' => 1800,
                'duree' => 8,
                'inclus' => 'Vol + Hôtel',
                'description' => 'Explorez la ville qui ne dort jamais, ses gratte-ciels et sa culture.',
                'emoji' => '🗽',
                'etoiles' => '⭐⭐⭐⭐',
                'gradient_start' => '#F7B731',
                'gradient_end' => '#F39C12'
            ],
            [
                'id' => 4,
                'destination' => 'Rome',
                'pays' => 'Italie',
                'continent' => 'Europe',
                'prix' => 950,
                'duree' => 6,
                'inclus' => 'Vol + Hôtel',
                'description' => 'Plongez dans l\'histoire de la Rome antique et sa cuisine italienne.',
                'emoji' => '🏛️',
                'etoiles' => '⭐⭐⭐⭐⭐',
                'gradient_start' => '#A55D35',
                'gradient_end' => '#8B4513'
            ],
            [
                'id' => 5,
                'destination' => 'Bali',
                'pays' => 'Indonésie',
                'continent' => 'Asie',
                'prix' => 1500,
                'duree' => 9,
                'inclus' => 'Vol + Hôtel + Transferts',
                'description' => 'Détendez-vous sur les plus belles plages et découvrez la culture balinaise.',
                'emoji' => '🏝️',
                'etoiles' => '⭐⭐⭐⭐⭐',
                'gradient_start' => '#6AB04C',
                'gradient_end' => '#2ECC71'
            ],
            [
                'id' => 6,
                'destination' => 'Marrakech',
                'pays' => 'Maroc',
                'continent' => 'Afrique',
                'prix' => 800,
                'duree' => 5,
                'inclus' => 'Vol + Riad',
                'description' => 'Imprégnez-vous de l\'ambiance des souks et des jardins luxuriants.',
                'emoji' => '🐪',
                'etoiles' => '⭐⭐⭐⭐',
                'gradient_start' => '#E67E22',
                'gradient_end' => '#D35400'
            ],
        ];

        // Pagination (pour l'instant 1 page avec tous les voyages)
        $current_page = 1;
        $total_pages = 1;

        return $this->render('home/voyages.html.twig', [
            'voyages' => $voyages,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
        ]);
    }
}