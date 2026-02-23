<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\AlertIARepository;
use App\Repository\DossierMedicalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(
        UserRepository $userRepo,
        AlertIARepository $alertRepo,
        DossierMedicalRepository $dossierRepo
    ): Response {
        $stats = [
            'patients' => $userRepo->countPatients(),
            'medecins' => $userRepo->countMedecins(),
            'inscriptionsMois' => $userRepo->countInscriptionsMois(),
        ];

        $alertes = $alertRepo->findBy([], ['createdAt' => 'DESC'], 10);

        // Statistiques par risque cardiaque
        $dossiers = $dossierRepo->findAll();
        $statsRisque = ['NORMAL' => 0, 'MODÉRÉ' => 0, 'ÉLEVÉ' => 0, 'CRITIQUE' => 0];
        foreach ($dossiers as $d) {
            $risque = $d->getRisqueCardiaque();
            if (isset($statsRisque[$risque])) {
                $statsRisque[$risque]++;
            }
        }

        return $this->render('dashboard/adminDashboard.html.twig', [
            'stats' => $stats,
            'alertes' => $alertes,
            'statsRisque' => $statsRisque,
        ]);
    }
}