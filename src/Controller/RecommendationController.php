<?php

namespace App\Controller;

use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Routing\Annotation\Route;

final class RecommendationController extends AbstractController
{
    #[Route('/recommend-from-quiz', name: 'app_recommend_from_quiz', methods: ['POST'])]
    public function recommendFromQuiz(Request $request, VoyageRepository $voyageRepository): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        $answers = $data['answers'] ?? [];

        // Require destination field from the quiz
        $destVal = trim((string)($answers['destination'] ?? ''));
        if ($destVal === '') {
            return new JsonResponse(['error' => 'destination_required', 'message' => 'Destination obligatoire'], 400);
        }

        $profileParts = [];
        foreach ($answers as $q => $a) {
            $profileParts[] = is_array($a) ? implode(' ', $a) : (string) $a;
        }
        $profileText = trim(implode('. ', $profileParts));

        if ($profileText === '') {
            return new JsonResponse(['error' => 'empty profile'], 400);
        }

        // Prepare docs from DB
        $voyages = $voyageRepository->findAll();
        $texts = [];
        $idMap = [];
        foreach ($voyages as $v) {
            $id = $v->getIdVoyage();
            $title = $v->getTitreVoyage() ?? '';
            $dest = $v->getDestination()?->getNomDestination() ?? '';
            $text = trim($title.' '. $dest);
            $texts[] = $text;
            $idMap[] = $id;
        }

        $hfKey = getenv('HF_API_KEY') ?: ($_ENV['HF_API_KEY'] ?? null);
        if (!$hfKey) {
            return new JsonResponse(['error' => 'missing_hf_api_key', 'message' => 'Set HF_API_KEY in .env.dev'], 400);
        }

        $client = HttpClient::create();
        $model = 'sentence-transformers/all-MiniLM-L6-v2';

        try {
            // Batch embed voyages (use embeddings endpoint)
            $indexResp = $client->request('POST', 'https://api-inference.huggingface.co/embeddings', [
                'headers' => [
                    'Authorization' => 'Bearer '.$hfKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['model' => $model, 'inputs' => $texts],
                'timeout' => 30,
            ]);

            if ($indexResp->getStatusCode() !== 200) {
                // HF failed — fall back to a simple keyword-based recommender below
                $indexFailed = true;
            } else {
                $indexFailed = false;
            }

            $embVoyagesBody = json_decode($indexResp->getContent(), true);
            $embVoyages = [];

            // Response can be list of embedding objects or list of arrays
            if (isset($embVoyagesBody[0]) && isset($embVoyagesBody[0]['embedding'])) {
                foreach ($embVoyagesBody as $item) {
                    $embVoyages[] = $item['embedding'];
                }
            } elseif (isset($embVoyagesBody['embeddings'])) {
                $embVoyages = $embVoyagesBody['embeddings'];
            } elseif (is_array($embVoyagesBody) && isset($embVoyagesBody[0]) && is_array($embVoyagesBody[0])) {
                $embVoyages = $embVoyagesBody;
            } else {
                return new JsonResponse(['error' => 'hf_unexpected_response', 'body' => $embVoyagesBody], 502);
            }

            // Embed query/profile
            $recResp = $client->request('POST', 'https://api-inference.huggingface.co/embeddings', [
                'headers' => [
                    'Authorization' => 'Bearer '.$hfKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['model' => $model, 'inputs' => $profileText],
                'timeout' => 15,
            ]);
        } catch (\Throwable $e) {
            $indexFailed = true;
        }

        if (isset($indexFailed) && $indexFailed) {
            // Fallback: simple keyword matching if Hugging Face embedding calls fail.
            $results = $this->simpleKeywordRecommend($profileText, $voyages);
            // attach fallback descriptions so the frontend always has a description
            foreach ($results as &$r) {
                $r['description'] = $this->fallbackDescription($r['destination'] ?? '');
            }
            return new JsonResponse(['results' => $results]);
        }

        if ($recResp->getStatusCode() !== 200) {
            $results = $this->simpleKeywordRecommend($profileText, $voyages);
            foreach ($results as &$r) {
                $r['description'] = $this->fallbackDescription($r['destination'] ?? '');
            }
            return new JsonResponse(['results' => $results]);
        }

        $embQueryBody = json_decode($recResp->getContent(), true);
        if (isset($embQueryBody['embedding'])) {
            $embQuery = $embQueryBody['embedding'];
        } elseif (isset($embQueryBody[0]) && isset($embQueryBody[0]['embedding'])) {
            $embQuery = $embQueryBody[0]['embedding'];
        } elseif (isset($embQueryBody['embeddings']) && isset($embQueryBody['embeddings'][0])) {
            $embQuery = $embQueryBody['embeddings'][0];
        } elseif (is_array($embQueryBody) && isset($embQueryBody[0]) && is_array($embQueryBody[0]) && is_numeric($embQueryBody[0][0] ?? null)) {
            $embQuery = $embQueryBody[0];
        } else {
            return new JsonResponse(['error' => 'hf_query_unexpected', 'body' => $embQueryBody], 502);
        }

        // compute cosine similarities
        $dot = function(array $a, array $b): float {
            $s = 0.0;
            $len = min(count($a), count($b));
            for ($i = 0; $i < $len; $i++) { $s += $a[$i] * $b[$i]; }
            return $s;
        };
        $norm = function(array $v): float {
            $s = 0.0; foreach ($v as $x) { $s += $x*$x; } return sqrt($s);
        };

        $qnorm = $norm($embQuery) ?: 1.0;
        $scores = [];
        foreach ($embVoyages as $idx => $vec) {
            $vn = $norm($vec) ?: 1.0;
            $scores[$idx] = $dot($embQuery, $vec) / ($qnorm * $vn);
        }

        arsort($scores);
        $top = array_slice(array_keys($scores), 0, 6);
        $ids = array_map(fn($i) => $idMap[$i], $top);

        if (count($ids) === 0) {
            return new JsonResponse(['results' => []]);
        }

        $voyagesFound = $voyageRepository->findBy(['id_voyage' => $ids]);
        $map = [];
        foreach ($voyagesFound as $v) {
            $map[$v->getIdVoyage()] = [
                'id' => $v->getIdVoyage(),
                'title' => $v->getTitreVoyage(),
                'destination' => $v->getDestination()?->getNomDestination(),
            ];
        }

        $results = [];
        foreach ($ids as $id) {
            if (isset($map[$id])) {
                $results[] = $map[$id];
            }
        }

        // Attach generated descriptions (AI) to each result when possible.
        $hfKeyCheck = getenv('HF_API_KEY') ?: ($_ENV['HF_API_KEY'] ?? null);
        foreach ($results as &$r) {
            $r['description'] = $this->generateDestinationDescription($r['destination'], $hfKeyCheck);
        }

        return new JsonResponse(['results' => $results]);
    }

    private function generateDestinationDescription(?string $destination, ?string $hfKey): string
    {
        $dest = trim((string)$destination);
        if ($dest === '') {
            return '';
        }

        if (!$hfKey) {
            return $this->fallbackDescription($dest);
        }

        $client = HttpClient::create();
        $model = 'google/flan-t5-small';
        $prompt = sprintf("Donne une description concise, amicale et touristique de %s en 1-2 phrases.", $dest);

        try {
            $resp = $client->request('POST', 'https://api-inference.huggingface.co/models/'.$model, [
                'headers' => [
                    'Authorization' => 'Bearer '.$hfKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['inputs' => $prompt, 'parameters' => ['max_new_tokens' => 80]],
                'timeout' => 15,
            ]);

            if ($resp->getStatusCode() !== 200) {
                return $this->fallbackDescription($dest);
            }

            $body = json_decode($resp->getContent(), true);
            // Response may be string or array
            if (is_string($body)) {
                return trim($body);
            }
            if (isset($body[0]) && isset($body[0]['generated_text'])) {
                return trim($body[0]['generated_text']);
            }
            if (isset($body[0]) && is_string($body[0])) {
                return trim($body[0]);
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }

        return $this->fallbackDescription($dest);
    }

    private function fallbackDescription(string $dest): string
    {
        // Simple, safe handcrafted fallback (French)
        return sprintf("%s est une destination idéale pour les voyageurs: découvrez sa culture, ses paysages et ses activités incontournables. Parfait pour un séjour mémorable.", $dest);
    }

    private function simpleKeywordRecommend(string $profileText, array $voyages): array
    {
        $profile = mb_strtolower($profileText);
        $tokens = array_filter(array_map('trim', preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}]+/u', ' ', $profile))));

        $scores = [];
        foreach ($voyages as $v) {
            $id = $v->getIdVoyage();
            $title = mb_strtolower($v->getTitreVoyage() ?? '');
            $dest = mb_strtolower($v->getDestination()?->getNomDestination() ?? '');
            $text = $title.' '.$dest;
            $s = 0;
            foreach ($tokens as $t) {
                if ($t === '') continue;
                if (mb_stripos($text, $t) !== false) { $s += 2; }
                // partial match on numbers (budgets/durations)
                if (is_numeric($t) && mb_strpos($text, (string)$t) !== false) { $s += 3; }
            }
            $scores[$id] = $s;
        }

        arsort($scores);
        $top = array_slice(array_keys($scores), 0, 6);

        $out = [];
        foreach ($top as $id) {
            foreach ($voyages as $v) {
                if ($v->getIdVoyage() === $id) {
                    $out[] = [
                        'id' => $v->getIdVoyage(),
                        'title' => $v->getTitreVoyage(),
                        'destination' => $v->getDestination()?->getNomDestination(),
                    ];
                    break;
                }
            }
        }
        return $out;
    }
}
