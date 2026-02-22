<?php

namespace App\Controller;

use App\Entity\AlertIA;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IAController extends AbstractController
{
    #[Route('/admin/peak', name: 'app_peak')]
    public function predictPeak(
        UserRepository $repo,
        HttpClientInterface $client,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $data = $repo->countSignupsByDay();

        $formatted = [];
        foreach ($data as $row) {
            $date = new \DateTime($row['date']);
            $formatted[] = [
                'jour' => (int)$date->format('d'),
                'mois' => (int)$date->format('m'),
                'jour_semaine' => (int)$date->format('w'),
                'total' => (int)$row['total']
            ];
        }

        $response = $client->request('POST', 'http://127.0.0.1:5000/predict_peak', [
            'json' => $formatted
        ]);

        // Toutes les données pour le graphique (jamais filtrées)
        $allResults = $response->toArray();

        // Sauvegarder les alertes critiques
        foreach ($allResults as $r) {
            if ($r['peak'] && $r['prediction'] > 80) {
                $alert = new AlertIA();
                $alert->setDatePeak(new \DateTime($r['date']));
                $alert->setPredictionValue($r['prediction']);
                $alert->setCreatedAt(new \DateTime());
                $alert->setStatus($r['risk']);
                $em->persist($alert);
            }
        }
        $em->flush();

        // Copie pour filtrage/tri
        $results = $allResults;

        // Tri
        $sort = $request->query->get('sort');
        if ($sort === 'prediction_desc') {
            usort($results, fn($a, $b) => $b['prediction'] <=> $a['prediction']);
        } elseif ($sort === 'date_asc') {
            usort($results, fn($a, $b) => strcmp($a['date'], $b['date']));
        }

        // Filtre par seuil minimum
        $min = $request->query->get('min');
        if ($min) {
            $results = array_values(array_filter($results, fn($r) => $r['prediction'] >= $min));
        }

        // Filtre par niveau de risque
        $risk = $request->query->get('risk');
        if ($risk) {
            $results = array_values(array_filter($results, fn($r) => $r['risk'] === $risk));
        }

        $peaks = array_values(array_filter($results, fn($r) => $r['peak']));

        return $this->render('ia/peak.html.twig', [
            'results' => $results,
            'peaks' => $peaks,
            'allResults' => $allResults  // pour le graphique
        ]);
    }
}