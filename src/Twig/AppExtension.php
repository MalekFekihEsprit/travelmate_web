<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('sum', [$this, 'arraySum']),
            new TwigFilter('max', [$this, 'getMax']),
        ];
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