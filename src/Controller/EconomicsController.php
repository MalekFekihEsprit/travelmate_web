<?php

namespace App\Controller;

use App\Service\CurrencyService;
use App\Service\Inflationservice;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/economics')]
class EconomicsController extends AbstractController
{
    public function __construct(
        private CurrencyService $currencyService,
        private Inflationservice $inflationService
    ) {}

    #[Route('/currencies', name: 'economics_currencies')]
    public function currencies(): Response
    {
        return $this->render('economics/currencies.html.twig', [
            'currencies' => $this->currencyService->getAvailableCurrencies(),
        ]);
    }

    #[Route('/convert', name: 'economics_convert', methods: ['GET'])]
    public function convert(Request $request): Response
    {
        $from   = strtoupper($request->query->get('from', 'EUR'));
        $to     = strtoupper($request->query->get('to', 'TND'));
        $amount = (float) $request->query->get('amount', 1);

        $result = $this->currencyService->convert($from, $to, $amount);

        return $this->render('economics/convert.html.twig', [
            'result'     => $result,
            'currencies' => $this->currencyService->getAvailableCurrencies(),
        ]);
    }

    #[Route('/inflation/{country}', name: 'economics_inflation')]
    public function inflation(string $country = 'TN'): Response
    {
        return $this->render('economics/inflation.html.twig', [
            'latest'     => $this->inflationService->getLatestInflation($country),
            'historical' => $this->inflationService->getHistoricalInflation($country),
            'country'    => $country,
        ]);
    }
}