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

        $matchStageObject = $ville
            ? [
                'ville' => $ville,
                'valeur_fonciere' => ['$gte' => 10000, '$lte' => 2000000],
                'surface_reelle_bati' => ['$gte' => 10]
            ]
            : [
                'valeur_fonciere' => ['$gte' => 10000, '$lte' => 2000000],
                'surface_reelle_bati' => ['$gte' => 10]
            ];


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

        $cityMediansCollection = $db->selectCollection('city_medians');

        $medianGlobale = 0;
        $prixM2Global = null;

        if ($ville) {
            $villeDoc = $cityMediansCollection->findOne(['_id' => $ville]);
            $medianGlobale = $villeDoc['valeur_fonciere_mediane'] ?? 0;
            $prixM2Global = $villeDoc['prix_m2_moyen'] ?? null;
        } else {
            $globalMedianDoc = $cityMediansCollection->aggregate([
                [
                    '$group' => [
                        '_id' => null,
                        'mediane_globale' => ['$avg' => '$valeur_fonciere_mediane']
                    ]
                ]
            ])->toArray();

            $medianGlobale = $globalMedianDoc[0]['mediane_globale'] ?? 0;

            $globalM2Doc = $cityMediansCollection->aggregate([
                [
                    '$match' => ['prix_m2_moyen' => ['$gt' => 0]]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'prix_m2_moyen_global' => ['$avg' => '$prix_m2_moyen']
                    ]
                ]
            ])->toArray();

            $prixM2Global = $globalM2Doc[0]['prix_m2_moyen_global'] ?? null;
        }

        $allCityMedians = $cityMediansCollection->find([], ['sort' => ['valeur_fonciere_mediane' => -1]])->toArray();

        $allCityMedians = array_filter($allCityMedians, function ($doc) {
            return isset($doc['valeur_fonciere_moyenne'], $doc['valeur_fonciere_mediane'], $doc['nb_transactions'])
                && $doc['nb_transactions'] >= 10
                && $doc['valeur_fonciere_mediane'] >= 10000
                && $doc['valeur_fonciere_mediane'] <= 2000000
                && $doc['valeur_fonciere_moyenne'] >= 10000
                && $doc['valeur_fonciere_moyenne'] <= 2000000;
        });

        $allCityMedians = array_map(function ($doc) {
            return [
                'ville' => $doc['_id'],
                'valeur_fonciere_moyenne' => $doc['valeur_fonciere_moyenne'],
                'valeur_fonciere_mediane' => $doc['valeur_fonciere_mediane'],
                'nb_transactions' => $doc['nb_transactions']
            ];
        }, $allCityMedians);

        usort($allCityMedians, fn($a, $b) => $b['valeur_fonciere_mediane'] <=> $a['valeur_fonciere_mediane']);
        $topVilles = array_slice($allCityMedians, 0, 5);

        $filteredCityMedians = array_values($allCityMedians);
        $rawCityMedians = $cityMediansCollection->find([], ['sort' => ['valeur_fonciere_moyenne' => -1]])->toArray();
        $worstOutliers = array_map(function ($doc) {
            return [
                'ville' => $doc['_id'],
                'valeur_fonciere_moyenne' => $doc['valeur_fonciere_moyenne'] ?? 0,
                'valeur_fonciere_mediane' => $doc['valeur_fonciere_mediane'] ?? 0,
                'nb_transactions' => $doc['nb_transactions'] ?? 0
            ];
        }, array_slice($rawCityMedians, 0, 20));


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

            if ($p2021 > 0 && $p2022 > 0) {
                $variation = $p2022 - $p2021;
                $growth = ($variation / $p2021) * 100;

                $progressions[] = [
                    'ville' => $doc['nom_commune'],
                    'code_insee' => $doc['code_insee'],
                    'pop_2021' => $p2021,
                    'pop_2022' => $p2022,
                    'variation' => $variation,
                    'croissance_percent' => round($growth, 2)
                ];
            }
        }

        usort($progressions, fn($a, $b) => $b['croissance_percent'] <=> $a['croissance_percent']);
        $topProgressions = array_slice($progressions, 0, 10);

        $priceDistributionCollection = $db->selectCollection('price_distribution');
        $priceDoc = $priceDistributionCollection->findOne([], ['sort' => ['generated_at' => -1]]);
        $priceDistribution = $priceDoc ?? [];

        $transactionsVille = [];

        if ($ville) {
            $transactionsCursor = $transactionCollection->find(
                ['ville' => $ville],
                ['limit' => 100, 'sort' => ['date' => -1], 'projection' => ['valeur_fonciere' => 1, 'surface_reelle_bati' => 1, 'date' => 1]]
            );

            foreach ($transactionsCursor as $doc) {
                $transactionsVille[] = [
                    'date' => $doc['date'] instanceof \MongoDB\BSON\UTCDateTime ? $doc['date']->toDateTime()->format('Y-m-d') : null,
                    'valeur_fonciere' => $doc['valeur_fonciere'] ?? null,
                    'surface_reelle_bati' => $doc['surface_reelle_bati'] ?? null,
                ];
            }
        }


        $populationDeclineCollection = $db->selectCollection('population_decline');
        $topDeclines = $populationDeclineCollection->find(
            [],
            ['sort' => ['croissance_percent' => 1], 'limit' => 10]
        )->toArray();


        return $this->json([
            'ville' => $ville,
            'kpi' => [
                'total_transactions' => $kpi['total_transactions'] ?? 0,
                'valeur_fonciere_moyenne' => round($kpi['valeur_fonciere_moyenne'] ?? 0, 2),
                'valeur_fonciere_mediane' => round($medianGlobale, 2),
                'prix_m2_moyen' => $prixM2Global !== null ? round($prixM2Global, 2) : null,
                'derniere_transaction' => $lastDate,
            ],
            'top_villes' => $topVilles,
            'all_city_medians' => $allCityMedians,
            'filtered_city_medians' => $filteredCityMedians,
            'worst_outliers' => $worstOutliers,
            'annees_comparees' => '2021 vs 2022',
            'topProgressions' => $topProgressions,
            'price_distribution' => $priceDistribution,
            'topDeclines' => $topDeclines,
            'transactions_ville' => $transactionsVille,
        ]);
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

    #[Route('/api/doc', name: 'api_doc')]
    public function apiDoc(): Response
    {
        return $this->render('dashboard/api_doc.html.twig');
    }


    #[Route('/api/villes', name: 'api_villes')]
    public function villes(DocumentManager $dm): JsonResponse
    {
        $collection = $dm->getDocumentCollection(Transaction::class);
        $villes = $collection->distinct('ville');
        sort($villes, SORT_STRING | SORT_FLAG_CASE);
        return $this->json(['villes' => $villes]);
    }

    #[Route('/api/population/baisses', name: 'api_population_decline')]
    public function populationDecline(Client $nativeClient): JsonResponse
    {
        $db = $nativeClient->selectDatabase('dataimmolabs');
        $populationCollection = $db->selectCollection('population');

        $cursor = $populationCollection->find([
            'populations.2021' => ['$exists' => true],
            'populations.2022' => ['$exists' => true],
            'populations.2021' => ['$gte' => 100]
        ]);

        $declines = [];
        foreach ($cursor as $doc) {
            $p2021 = $doc['populations']['2021'];
            $p2022 = $doc['populations']['2022'];

            if ($p2021 > 0 && $p2022 > 0 && $p2022 < $p2021) {
                $variation = $p2022 - $p2021;
                $growth = ($variation / $p2021) * 100;

                $declines[] = [
                    'ville' => $doc['nom_commune'],
                    'code_insee' => $doc['code_insee'],
                    'pop_2021' => $p2021,
                    'pop_2022' => $p2022,
                    'variation' => $variation,
                    'croissance_percent' => round($growth, 2)
                ];
            }
        }

        usort($declines, fn($a, $b) => $a['croissance_percent'] <=> $b['croissance_percent']);
        return new JsonResponse(array_slice($declines, 0, 10));
    }

    #[Route('/api/population', name: 'api_population_all')]
    public function allPopulation(Client $nativeClient): JsonResponse
    {
        $db = $nativeClient->selectDatabase('dataimmolabs');
        $populationCollection = $db->selectCollection('population');

        // Limiter la projection pour ne pas charger tout
        $cursor = $populationCollection->find(
            [],
            ['projection' => [
                'code_insee' => 1,
                'nom_commune' => 1,
                'populations' => 1
            ]]
        );

        $results = [];
        foreach ($cursor as $doc) {
            $results[] = [
                'code_insee' => $doc['code_insee'],
                'nom_commune' => $doc['nom_commune'],
                'populations' => $doc['populations']
            ];

            // Stopper après 5000 pour éviter de saturer la mémoire
            if (count($results) >= 5000) {
                break;
            }
        }

        return new JsonResponse(['data' => $results], 200);
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
        return new JsonResponse(['top_10_progression' => array_slice($progressions, 0, 10)], 200);
    }
}
