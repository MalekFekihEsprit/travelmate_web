<?php

namespace App\Twig;

use App\Repository\CategorieRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private CategorieRepository $categorieRepository) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('sum', [$this, 'arraySum']),
            new TwigFilter('max', [$this, 'getMax']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_categories_with_activites', [$this, 'getNavCategories']),
        ];
    }

    public function getNavCategories(): array
    {
        return $this->categorieRepository->findAll();
    }

    public function getMax(array $array): mixed
    {
        if (empty($array)) {
            return 0;
        }
        return max($array);
    }

    public function arraySum($array): int|float
    {
        if (!is_array($array)) return 0;
        return array_sum($array);
    }
}