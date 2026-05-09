<?php

namespace App\Controller;

use App\Repository\ActiviteRepository;
use App\Repository\AvisRepository;
use App\Repository\BudgetRepository;
use App\Repository\DestinationRepository;
use App\Repository\EvenementRepository;
use App\Repository\HebergementRepository;
use App\Repository\ItineraireRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/chatbot')]
class ChatbotController extends AbstractController
{
    #[Route('/ask', name: 'app_chatbot_ask', methods: ['POST'])]
    public function ask(
        Request $request,
        DestinationRepository $destinationRepo,
        ActiviteRepository $activiteRepo,
        HebergementRepository $hebergementRepo,
        EvenementRepository $evenementRepo,
        VoyageRepository $voyageRepo,
        ItineraireRepository $itineraireRepo,
        AvisRepository $avisRepo,
        BudgetRepository $budgetRepo,
        #[Autowire('%env(GROQ_API_KEY)%')] string $apiKey,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            return $this->json(['reply' => 'Veuillez saisir un message.']);
        }

        $lower = mb_strtolower($message);
        $allVoyages = $voyageRepo->findAll();
        $mentionedVoyage = $this->detectMentionedVoyage($lower, $allVoyages);

        $context = $this->buildContext(
            $lower,
            $mentionedVoyage,
            $destinationRepo,
            $activiteRepo,
            $hebergementRepo,
            $evenementRepo,
            $voyageRepo,
            $avisRepo,
            $budgetRepo
        );

        $reply = $this->callGroq($message, $context, $apiKey);
        return $this->json(['reply' => $reply]);
    }

    private function detectMentionedVoyage(string $lowerMessage, array $voyages): ?object
    {
        foreach ($voyages as $v) {
            $title = mb_strtolower((string) $v->getTitreVoyage());
            if ($title !== '' && str_contains($lowerMessage, $title)) {
                return $v;
            }
        }
        return null;
    }

    private function detectIntent(string $lower): string
    {
        if (str_contains($lower, 'itineraire') || str_contains($lower, 'itinéraire')
            || str_contains($lower, 'programme') || str_contains($lower, 'etape')
            || str_contains($lower, 'étape')) return 'itinerary';
        if (str_contains($lower, 'budget') || str_contains($lower, 'depense')
            || str_contains($lower, 'dépense') || str_contains($lower, 'cout')
            || str_contains($lower, 'coût')) return 'budget';
        if (str_contains($lower, 'activit')) return 'activity';
        if (str_contains($lower, 'destination')) return 'destination';
        if (str_contains($lower, 'hebergement') || str_contains($lower, 'hotel')
            || str_contains($lower, 'hôtel')) return 'accommodation';
        if (str_contains($lower, 'evenement') || str_contains($lower, 'événement')) return 'event';
        if (str_contains($lower, 'avis') || str_contains($lower, 'commentaire')
            || str_contains($lower, 'note')) return 'review';
        if (str_contains($lower, 'voyage')) return 'voyage';
        return 'general';
    }

    private function buildContext(
        string $lower,
        ?object $mentionedVoyage,
        DestinationRepository $destinationRepo,
        ActiviteRepository $activiteRepo,
        HebergementRepository $hebergementRepo,
        EvenementRepository $evenementRepo,
        VoyageRepository $voyageRepo,
        AvisRepository $avisRepo,
        BudgetRepository $budgetRepo,
    ): string {
        $intent = $this->detectIntent($lower);
        $parts = [];

        if ($mentionedVoyage !== null && $intent === 'itinerary') {
            return $this->buildVoyageItineraryContext($mentionedVoyage);
        }
        if ($mentionedVoyage !== null) {
            return $this->buildVoyageSummaryContext($mentionedVoyage);
        }

        switch ($intent) {
            case 'itinerary':
            case 'voyage':
                $parts[] = $this->buildVoyagesListContext($voyageRepo->findAll());
                break;
            case 'activity':
                $parts[] = $this->buildActivitiesContext($activiteRepo->findAll());
                $parts[] = $this->buildAvisContext($avisRepo->findBy(['isFlagged' => false]));
                break;
            case 'review':
                $parts[] = $this->buildAvisContext($avisRepo->findBy(['isFlagged' => false]));
                $parts[] = $this->buildActivitiesContext($activiteRepo->findAll());
                break;
            case 'destination':
                $parts[] = $this->buildDestinationsContext($destinationRepo->findAll());
                break;
            case 'accommodation':
                $parts[] = $this->buildHebergementsContext($hebergementRepo->findAll());
                break;
            case 'event':
                $parts[] = $this->buildEvenementsContext($evenementRepo->findAll());
                break;
            case 'budget':
                $parts[] = $this->buildBudgetsContext($budgetRepo->findAll());
                $parts[] = $this->buildVoyagesListContext($voyageRepo->findAll());
                break;
            default:
                $parts[] = $this->buildVoyagesListContext($voyageRepo->findAll());
                $parts[] = $this->buildActivitiesContext($activiteRepo->findAll());
                $parts[] = $this->buildDestinationsContext($destinationRepo->findAll());
                $parts[] = $this->buildAvisContext($avisRepo->findBy(['isFlagged' => false]));
                break;
        }

        return implode("\n\n", array_filter($parts));
    }

    private function buildVoyagesListContext(array $voyages): string
    {
        if (!$voyages) return '';
        $lines = ['=== VOYAGES ==='];
        foreach ($voyages as $v) {
            $lines[] = '- ' . $v->getTitreVoyage()
                . ' | ' . ($v->getDestination() ? $v->getDestination()->getNom_destination() : 'N/A')
                . ' | Du ' . $v->getDateDebut()->format('d/m/Y')
                . ' au ' . $v->getDateFin()->format('d/m/Y')
                . ' | Statut: ' . $v->getStatut();
        }
        return implode("\n", $lines);
    }

    private function buildVoyageSummaryContext(object $voyage): string
    {
        $lines = ['=== VOYAGE: ' . mb_strtoupper($voyage->getTitreVoyage()) . ' ==='];
        $lines[] = 'Nom: ' . $voyage->getTitreVoyage();
        $lines[] = 'Destination: ' . ($voyage->getDestination() ? $voyage->getDestination()->getNom_destination() : 'N/A');
        $lines[] = 'Période: Du ' . $voyage->getDateDebut()->format('d/m/Y') . ' au ' . $voyage->getDateFin()->format('d/m/Y');
        $lines[] = 'Statut: ' . $voyage->getStatut();
        if ($voyage->getActivites()->count() > 0) {
            $actNames = [];
            foreach ($voyage->getActivites() as $act) {
                $actNames[] = $act->getNom() . ' (' . $act->getBudget() . ' TND)';
            }
            $lines[] = 'Activités incluses: ' . implode(', ', $actNames);
        }
        $itins = $voyage->getItineraires();
        if ($itins->count() > 0) {
            $lines[] = 'Itinéraires disponibles: ' . $itins->count() . ' itinéraire(s). Demandez "itinéraire de ' . $voyage->getTitreVoyage() . '" pour les détails.';
        } else {
            $lines[] = 'Aucun itinéraire configuré pour ce voyage.';
        }
        return implode("\n", $lines);
    }

    private function buildVoyageItineraryContext(object $voyage): string
    {
        $lines = ['=== ITINÉRAIRE DU VOYAGE: ' . mb_strtoupper($voyage->getTitreVoyage()) . ' ==='];
        $lines[] = 'Destination: ' . ($voyage->getDestination() ? $voyage->getDestination()->getNom_destination() : 'N/A');
        $lines[] = 'Période: Du ' . $voyage->getDateDebut()->format('d/m/Y') . ' au ' . $voyage->getDateFin()->format('d/m/Y');
        $itins = $voyage->getItineraires();
        if ($itins->count() === 0) {
            $lines[] = 'Aucun itinéraire configuré pour ce voyage.';
            return implode("\n", $lines);
        }
        foreach ($itins as $itin) {
            $lines[] = '';
            $lines[] = '[Itinéraire] ' . $itin->getNom_itineraire();
            if ($itin->getDescription_itineraire()) {
                $lines[] = 'Description: ' . mb_substr($itin->getDescription_itineraire(), 0, 150);
            }
            foreach ($itin->getEtapes() as $etape) {
                $etapeLine = '  ' . $etape->getHeure()->format('H:i') . ' → ' . $etape->getDescription_etape();
                if ($etape->getActivite()) {
                    $etapeLine .= ' [Activité: ' . $etape->getActivite()->getNom() . ' - ' . $etape->getActivite()->getBudget() . ' TND]';
                }
                $lines[] = $etapeLine;
            }
        }
        return implode("\n", $lines);
    }

    private function buildActivitiesContext(array $activites): string
    {
        if (!$activites) return '';
        $lines = ['=== ACTIVITÉS ==='];
        foreach ($activites as $a) {
            $line = '- ' . $a->getNom() . ' | Prix: ' . $a->getBudget() . ' TND | Difficulté: ' . $a->getNiveaudifficulte();
            if ($a->getLieu()) $line .= ' | Lieu: ' . $a->getLieu();
            if ($a->getDescription()) $line .= ' | ' . mb_substr($a->getDescription(), 0, 80);
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    private function buildAvisContext(array $allAvis): string
    {
        if (!$allAvis) return '';
        $byActivite = [];
        foreach ($allAvis as $avis) {
            if (!$avis->getActivite()) continue;
            $nom = $avis->getActivite()->getNom();
            $lieu = $avis->getActivite()->getLieu() ?? '';
            if (!isset($byActivite[$nom])) {
                $byActivite[$nom] = ['lieu' => $lieu, 'notes' => [], 'commentaires' => []];
            }
            $byActivite[$nom]['notes'][] = $avis->getNote();
            if ($avis->getCommentaire()) {
                $byActivite[$nom]['commentaires'][] = mb_substr($avis->getCommentaire(), 0, 60) . ' (⭐' . $avis->getNote() . ')';
            }
        }
        uasort($byActivite, function ($a, $b) {
            $avgA = count($a['notes']) ? array_sum($a['notes']) / count($a['notes']) : 0;
            $avgB = count($b['notes']) ? array_sum($b['notes']) / count($b['notes']) : 0;
            return $avgB <=> $avgA;
        });
        $lines = ['=== AVIS ET NOTES PAR ACTIVITÉ (non signalés) ==='];
        foreach ($byActivite as $nomAct => $data) {
            $nb = count($data['notes']);
            $moyenne = $nb > 0 ? round(array_sum($data['notes']) / $nb, 1) : 0;
            $lines[] = '- ' . $nomAct . ' (' . ($data['lieu'] ?: 'N/A') . ') : ' . $moyenne . '/5 — ' . $nb . ' avis';
            foreach (array_slice($data['commentaires'], 0, 2) as $c) {
                $lines[] = '  "' . $c . '"';
            }
        }
        return implode("\n", $lines);
    }

    private function buildDestinationsContext(array $destinations): string
    {
        if (!$destinations) return '';
        $lines = ['=== DESTINATIONS ==='];
        foreach ($destinations as $d) {
            $line = '- ' . $d->getNom_destination() . ' (' . $d->getPays_destination() . ')';
            if ($d->getClimat_destination()) $line .= ' | Climat: ' . $d->getClimat_destination();
            if ($d->getSaison_destination()) $line .= ' | Saison: ' . $d->getSaison_destination();
            if ($d->getDescription_destination()) $line .= ' | ' . mb_substr($d->getDescription_destination(), 0, 100);
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    private function buildHebergementsContext(array $hebergements): string
    {
        if (!$hebergements) return '';
        $lines = ['=== HÉBERGEMENTS ==='];
        foreach ($hebergements as $h) {
            $line = '- ' . $h->getNomHebergement();
            if ($h->getTypeHebergement()) $line .= ' (' . $h->getTypeHebergement() . ')';
            if ($h->getPrixNuitHebergement() !== null) $line .= ' | ' . $h->getPrixNuitHebergement() . ' TND/nuit';
            if ($h->getNoteHebergement() !== null) $line .= ' | Note: ' . $h->getNoteHebergement() . '/5';
            if ($h->getAdresseHebergement()) $line .= ' | ' . $h->getAdresseHebergement();
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    private function buildEvenementsContext(array $evenements): string
    {
        $today = new \DateTime('today');
        $prochains = array_filter($evenements, fn($e) => $e->getDate() >= $today);
        usort($prochains, fn($a, $b) => $a->getDate() <=> $b->getDate());
        if (!$prochains) return "=== ÉVÉNEMENTS ===\nAucun événement à venir.";
        $lines = ['=== ÉVÉNEMENTS À VENIR ==='];
        foreach ($prochains as $e) {
            $line = '- ' . $e->getTitre() . ' | ' . $e->getDate()->format('d/m/Y') . ' à ' . $e->getHeure()->format('H:i') . ' | ' . $e->getLieu();
            if ($e->getDescription()) $line .= ' | ' . mb_substr($e->getDescription(), 0, 80);
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    private function buildBudgetsContext(array $budgets): string
    {
        if (!$budgets) return '';
        $lines = ['=== BUDGETS PAR VOYAGE ==='];
        foreach ($budgets as $b) {
            $line = '- ' . $b->getLibelle_budget() . ' | Total: ' . $b->getMontant_total() . ' ' . ($b->getDevise_budget() ?: 'TND') . ' | Statut: ' . ($b->getStatut_budget() ?: 'N/A');
            if ($b->getVoyage()) $line .= ' | Voyage: ' . $b->getVoyage()->getTitreVoyage();
            $lines[] = $line;
            foreach ($b->getDepenses() as $dep) {
                $lines[] = '  - ' . $dep->getLibelle_depense() . ' — ' . $dep->getMontant_depense() . ' ' . ($dep->getDevise_depense() ?: 'TND') . ' (' . ($dep->getCategorie_depense() ?: 'N/A') . ')';
            }
        }
        return implode("\n", $lines);
    }

    private function callGroq(string $userMessage, string $context, string $apiKey): string
    {
        $systemPrompt = <<<'PROMPT'
Tu es TravelMate AI, un assistant intelligent spécialisé dans les voyages, activités, destinations, budgets et avis utilisateurs.

Tu as accès à une base de données relationnelle MySQL contenant plusieurs tables comme :

* voyages
* destination
* activites
* avis
* itineraire
* etape
* budget
* depense
* categories

Ta mission est de répondre aux utilisateurs de manière intelligente, courte, pertinente et naturelle.

========================
RÈGLES GÉNÉRALES
================

1. Ne jamais afficher toutes les données d'une table.
2. Répondre uniquement avec les informations utiles à la question.
3. Ne jamais afficher des itinéraires ou étapes complètes sauf si l'utilisateur les demande explicitement.
4. Toujours analyser l'intention de l'utilisateur avant de répondre.
5. Les réponses doivent être : courtes, propres, lisibles, organisées.
6. Ne jamais inventer des données.
7. Utiliser uniquement les informations de la base de données.
8. Si aucun résultat n'existe : répondre "Aucun résultat trouvé."

========================
COMPORTEMENT PAR TYPE DE QUESTION
=================================

1. VOYAGES — Afficher : nom, destination, dates, statut. Ne PAS afficher itinéraires/étapes.
Format :
✈️ Voyages à venir :
* NomVoyage — Destination — du DATE_DEBUT au DATE_FIN — Statut

2. MEILLEURES ACTIVITÉS — Trier par note moyenne (avis non signalés), descendre.
Format :
⭐ Meilleures activités :
1. Plongée — Tabarka — 4.8/5 (32 avis)
2. Parapente — Zaghouan — 4.7/5 (28 avis)

3. DÉTAILS ACTIVITÉ :
🎯 Activité : Parapente
📍 Lieu : Zaghouan
💰 Budget : 180 TND
⏱️ Durée : 2h
🔥 Difficulté : expert

4. AVIS — Uniquement les avis non signalés, triés par date.
💬 Avis récents :
* "Expérience incroyable !" — ⭐ 5

5. BUDGET :
💰 Budget :
* Total : 5102 TND
* Hébergement : 1360 TND

6. DESTINATIONS :
🌍 Destination : Nice | ☀️ Climat : Tropical | 🌴 Saison idéale : Été

7. ITINÉRAIRE — Seulement si explicitement demandé :
📅 Jour 1
* 09:00 → Arrivée
* 12:00 → Déjeuner

========================
STYLE : naturel, professionnel, concis, emojis modérés. Ne jamais inventer.
========================
DONNÉES DE LA BASE :
========================
PROMPT;
        $systemPrompt .= "\n" . $context;

        $models = [
            'llama-3.3-70b-versatile',
            'llama3-70b-8192',
            'llama3-8b-8192',
            'mixtral-8x7b-32768',
        ];

        foreach ($models as $model) {
            $payload = json_encode([
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
                'max_tokens'  => 1024,
                'temperature' => 0.3,
            ]);

            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr || $httpCode !== 200) continue;

            $decoded = json_decode($response, true);
            if (isset($decoded['choices'][0]['message']['content'])) {
                $content = trim($decoded['choices'][0]['message']['content']);
                if ($content !== '') return $content;
            }
        }

        return $this->fallbackReply($userMessage, $context);
    }

    private function fallbackReply(string $message, string $context): string
    {
        $lower = mb_strtolower($message);

        if (str_contains($context, 'ITINÉRAIRE DU VOYAGE:')) {
            return "📋 Voici l'itinéraire demandé :\n\n" . $context;
        }
        if (str_contains($context, '=== VOYAGE:')) {
            return "✈️ Informations sur le voyage :\n\n" . $context;
        }

        $lines = explode("\n", $context);

        if (str_contains($lower, 'activit') || str_contains($lower, 'meilleur')) {
            $avis = $this->extractSection($lines, 'AVIS ET NOTES PAR ACTIVITÉ');
            if ($avis) return "⭐ Activités notées :\n" . $avis;
            return "🎯 Activités :\n" . ($this->extractSection($lines, 'ACTIVITÉS') ?: 'Aucune activité trouvée.');
        }
        if (str_contains($lower, 'destination')) {
            return "🌍 Destinations :\n" . ($this->extractSection($lines, 'DESTINATIONS') ?: 'Aucune destination trouvée.');
        }
        if (str_contains($lower, 'hebergement') || str_contains($lower, 'hotel')) {
            return "🏨 Hébergements :\n" . ($this->extractSection($lines, 'HÉBERGEMENTS') ?: 'Aucun hébergement trouvé.');
        }
        if (str_contains($lower, 'evenement') || str_contains($lower, 'événement')) {
            return "📅 Événements :\n" . ($this->extractSection($lines, 'ÉVÉNEMENTS') ?: 'Aucun événement trouvé.');
        }
        if (str_contains($lower, 'budget') || str_contains($lower, 'depense')) {
            return "💰 Budgets :\n" . ($this->extractSection($lines, 'BUDGETS PAR VOYAGE') ?: 'Aucun budget trouvé.');
        }
        if (str_contains($lower, 'avis') || str_contains($lower, 'note')) {
            return "💬 Avis :\n" . ($this->extractSection($lines, 'AVIS ET NOTES PAR ACTIVITÉ') ?: 'Aucun avis trouvé.');
        }
        if (str_contains($lower, 'voyage') || str_contains($lower, 'itineraire')) {
            return "✈️ Voyages :\n" . ($this->extractSection($lines, 'VOYAGES') ?: 'Aucun voyage trouvé.');
        }

        return "Je peux vous renseigner sur nos destinations 🌍, activités 🎯, hébergements 🏨, événements 📅, voyages ✈️ et budgets 💰.";
    }

    private function extractSection(array $lines, string $sectionName): string
    {
        $inSection = false;
        $result = [];
        $upperSection = mb_strtoupper($sectionName);
        foreach ($lines as $line) {
            $upperLine = mb_strtoupper($line);
            if (str_contains($upperLine, '=== ' . $upperSection) || str_contains($upperLine, $upperSection . ' ===')) {
                $inSection = true;
                continue;
            }
            if ($inSection && str_starts_with($line, '===')) break;
            if ($inSection && trim($line) !== '') $result[] = $line;
        }
        return implode("\n", $result);
    }
}
