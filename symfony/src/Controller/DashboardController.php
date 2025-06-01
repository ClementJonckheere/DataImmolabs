<?php

namespace App\Controller;

use App\Document\Transaction;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/api/dashboard', name: 'api_dashboard')]
    public function apiDashboard(DocumentManager $dm, Client $nativeClient, Request $request): JsonResponse
    {
        $ville = $request->query->get('ville');

        $db = $nativeClient->selectDatabase($dm->getConfiguration()->getDefaultDB());
        $transactionCollection = $dm->getDocumentCollection(Transaction::class);

        $matchStageArray = $ville ? ['ville' => $ville] : [];
        $matchStageObject = $ville ? ['ville' => $ville] : (object)[];

        $pipeline = [
            ['$match' => $matchStageObject],
            [
                '$facet' => [
                    'kpi' => [[
                        '$group' => [
                            '_id' => null,
                            'valeur_fonciere_moyenne' => ['$avg' => '$valeur_fonciere'],
                            'surface_moyenne' => ['$avg' => '$surface_terrain'],
                            'total_transactions' => ['$sum' => 1]
                        ]
                    ]],
                    'last_transaction' => [
                        ['$sort' => ['date' => -1]],
                        ['$limit' => 1],
                        ['$project' => ['date' => 1]]
                    ]
                ]
            ]
        ];

        $aggregationResult = $transactionCollection->aggregate($pipeline)->toArray()[0] ?? [];
        $kpi = $aggregationResult['kpi'][0] ?? [];
        $lastTransaction = $aggregationResult['last_transaction'][0]['date'] ?? null;

        $lastDate = $lastTransaction instanceof \MongoDB\BSON\UTCDateTime
            ? $lastTransaction->toDateTime()->format('Y-m-d')
            : null;

        $topVilles = $transactionCollection->aggregate([
            [
                '$match' => array_merge(
                    ['valeur_fonciere' => ['$gt' => 100, '$lt' => 10_000_000]],
                    $matchStageArray
                )
            ],
            ['$group' => [
                '_id' => '$ville',
                'valeur_fonciere_moyenne' => ['$avg' => '$valeur_fonciere']
            ]],
            ['$sort' => ['valeur_fonciere_moyenne' => -1]],
            ['$limit' => 5]
        ])->toArray();

        $cityStats = $db->selectCollection('city_statistics')->find(
            $ville ? ['_id' => $ville] : [],
            $ville ? [] : ['limit' => 20]
        )->toArray();

        $marketTrends = $db->selectCollection('market_trends')->find(
            $ville ? ['_id.ville' => $ville] : [],
            ['sort' => ['_id' => -1]]
        )->toArray();

        $hotspotZones = $db->selectCollection('hotspot_zones')->find(
            $ville ? ['_id' => $ville] : []
        )->toArray();

        $populationCollection = $db->selectCollection('population');
        $cursor = $populationCollection->find([
            'populations.2021' => ['$exists' => true],
            'populations.2022' => ['$exists' => true],
            'populations.2021' => ['$gte' => 100]
        ]);

        $progressions = [];
        foreach ($cursor as $doc) {
            $p2021 = $doc['populations']['2021'];
            $p2022 = $doc['populations']['2022'];

            if ($p2021 > 0) {
                $variation = ($p2022 - $p2021) / $p2021 * 100;
                $progressions[] = [
                    'code_insee' => $doc['code_insee'],
                    'nom_commune' => $doc['nom_commune'],
                    'pop_2021' => $p2021,
                    'pop_2022' => $p2022,
                    'variation_pct' => round($variation, 2),
                    'variation_brute' => $p2022 - $p2021
                ];
            }
        }

        usort($progressions, fn($a, $b) => $b['variation_pct'] <=> $a['variation_pct']);
        $topProgressions = array_slice($progressions, 0, 10);

        return $this->json([
            'ville' => $ville,
            'kpi' => [
                'total_transactions' => $kpi['total_transactions'] ?? 0,
                'valeur_fonciere_moyenne' => round($kpi['valeur_fonciere_moyenne'] ?? 0, 2),
                'surface_moyenne' => round($kpi['surface_moyenne'] ?? 0, 2),
                'derniere_transaction' => $lastDate,
            ],
            'top_villes' => $topVilles,
            'city_statistics' => $cityStats,
            'market_trends' => $marketTrends,
            'hotspot_zones' => $hotspotZones,
            'topProgressions' => $topProgressions
        ], 200);
    }

    #[Route('/dashboard', name: 'dashboard')]
    public function view(Request $request, DocumentManager $dm, Client $nativeClient): Response
    {
        $data = json_decode(
            $this->apiDashboard($dm, $nativeClient, $request)->getContent(),
            true
        );

        return $this->render('dashboard/index.html.twig', $data);
    }

    #[Route('/api/villes', name: 'api_villes')]
    public function villes(DocumentManager $dm): JsonResponse
    {
        $collection = $dm->getDocumentCollection(Transaction::class);
        $villes = $collection->distinct('ville');

        sort($villes, SORT_STRING | SORT_FLAG_CASE);

        return $this->json([
            'villes' => $villes
        ]);
    }

    #[Route('/api/population/progression', name: 'api_population_progression')]
    public function populationProgression(Client $nativeClient): JsonResponse
    {
        $db = $nativeClient->selectDatabase('dataimmolabs');
        $populationCollection = $db->selectCollection('population');

        $cursor = $populationCollection->find([
            'populations.2021' => ['$exists' => true],
            'populations.2022' => ['$exists' => true],
            'populations.2021' => ['$gte' => 100]
        ]);

        $progressions = [];

        foreach ($cursor as $doc) {
            $p2021 = $doc['populations']['2021'];
            $p2022 = $doc['populations']['2022'];

            if ($p2021 > 0) {
                $variation = ($p2022 - $p2021) / $p2021 * 100;

                $progressions[] = [
                    'code_insee' => $doc['code_insee'],
                    'nom_commune' => $doc['nom_commune'],
                    'pop_2021' => $p2021,
                    'pop_2022' => $p2022,
                    'variation_pct' => round($variation, 2),
                    'variation_brute' => $p2022 - $p2021
                ];
            }
        }

        usort($progressions, fn($a, $b) => $b['variation_pct'] <=> $a['variation_pct']);

        return new JsonResponse([
            'top_10_progression' => array_slice($progressions, 0, 10)
        ], 200);
    }
}
