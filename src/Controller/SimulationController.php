<?php

namespace App\Controller;

use App\Repository\DossierMedicalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SimulationController extends AbstractController
{
    #[Route('/medecin/simulation', name: 'medecin_simulation')]
    public function index(DossierMedicalRepository $repo, Request $request): Response
    {
        // Données actuelles depuis la base
        $dossiers = $repo->findAll();
        
        $stats = [
            'CRITIQUE' => 0,
            'ÉLEVÉ' => 0,
            'MODÉRÉ' => 0,
            'NORMAL' => 0,
        ];

        foreach ($dossiers as $d) {
            $risque = $d->getRisqueCardiaque();
            if (isset($stats[$risque])) {
                $stats[$risque]++;
            }
        }

        // Paramètres simulation
        $taux = $request->query->get('taux', 20) / 100;
        $mois = 12;
        $y0 = $stats['CRITIQUE'] + $stats['ÉLEVÉ'];

        // Calcul modèle exponentiel y = y0 * e^(r*t)
        $simulation = [];
        for ($t = 1; $t <= $mois; $t++) {
            $valeur = round($y0 * exp($taux * ($t / 12)), 1);
            $simulation[] = [
                'mois' => $t,
                'valeur' => $valeur,
                'critique' => round($valeur * ($stats['CRITIQUE'] / max($y0, 1)), 1),
                'eleve' => round($valeur * ($stats['ÉLEVÉ'] / max($y0, 1)), 1),
            ];
        }

        return $this->render('simulation/index.html.twig', [
            'stats' => $stats,
            'simulation' => $simulation,
            'taux' => $request->query->get('taux', 20),
            'y0' => $y0,
            'total' => count($dossiers),
        ]);
    }
}