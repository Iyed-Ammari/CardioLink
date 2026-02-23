<?php

namespace App\Controller;

use App\Entity\Intervention;
use App\Entity\Suivi;
use App\Entity\User;
use App\Form\SuiviFormType;
use App\Repository\SuiviRepository;
use App\Repository\InterventionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/suivi')]
class SuiviController extends AbstractController
{
    /**
     * Liste des suivis avec fonctionnalités de tri et de recherche
     */
    #[Route('', name: 'app_suivi_index', methods: ['GET'])]
    public function index(
        Request $request,
        SuiviRepository $suiviRepository,
        #[CurrentUser] User $user
    ): Response {
        // 1. Récupération des paramètres de recherche et de tri depuis l'URL
        $search = $request->query->get('search');
        $sort = $request->query->get('sort', 'dateSaisie'); // 'dateSaisie' par défaut
        $direction = $request->query->get('direction', 'DESC'); // 'DESC' par défaut

        // 2. Appel de la nouvelle méthode filtrée dans le Repository
        // Note : On passe l'ID de l'utilisateur connecté pour qu'il ne voie que ses données
        $suivis = $suiviRepository->findByPatientFiltered(
            $user->getId(),
            $search,
            $sort,
            $direction
        );

        return $this->render('suivi/index.html.twig', [
            'suivis' => $suivis,
            'user' => $user,
            'current_search' => $search,
            'current_sort' => $sort,
            'current_direction' => $direction,
        ]);
    }

    #[Route('/nouveau', name: 'app_suivi_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): Response {
        $suivi = new Suivi();
        $suivi->setPatient($user);
        $suivi->setDateSaisie(new \DateTimeImmutable());

        $form = $this->createForm(SuiviFormType::class, $suivi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->setUnitByTypeDonnee($suivi);
            $suivi->setNiveauUrgence($this->calculateUrgencyLevel($suivi));

            $entityManager->persist($suivi);
            $entityManager->flush();

            if ($suivi->isCritical()) {
                $this->createCriticalIntervention($suivi, $entityManager);
            }

            $this->addFlash('success', 'Le suivi a été enregistré avec succès.');
            return $this->redirectToRoute('app_suivi_index');
        }

        return $this->render('suivi/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/voir', name: 'app_suivi_show', methods: ['GET'])]
    public function show(Suivi $suivi, #[CurrentUser] User $user): Response 
    {
        if ($suivi->getPatient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce suivi.');
        }

        return $this->render('suivi/show.html.twig', [
            'suivi' => $suivi,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_suivi_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Suivi $suivi,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): Response {
        if ($suivi->getPatient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce suivi.');
        }

        $form = $this->createForm(SuiviFormType::class, $suivi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->setUnitByTypeDonnee($suivi);
            $suivi->setNiveauUrgence($this->calculateUrgencyLevel($suivi));

            $entityManager->flush();
            $this->addFlash('success', 'Le suivi a été modifié avec succès.');

            return $this->redirectToRoute('app_suivi_show', ['id' => $suivi->getId()]);
        }

        return $this->render('suivi/edit.html.twig', [
            'form' => $form,
            'suivi' => $suivi,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_suivi_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Suivi $suivi,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): Response {
        if ($suivi->getPatient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce suivi.');
        }

        if ($this->isCsrfTokenValid('delete' . $suivi->getId(), $request->request->get('_token'))) {
            if ($suivi->getIntervention()) {
                $entityManager->remove($suivi->getIntervention());
            }
            $entityManager->remove($suivi);
            $entityManager->flush();
            $this->addFlash('success', 'Le suivi a été supprimé.');
        }

        return $this->redirectToRoute('app_suivi_index');
    }

    // =========================================================================
    // MÉTHODES PRIVÉES (LOGIQUE MÉTIER)
    // =========================================================================

    private function setUnitByTypeDonnee(Suivi $suivi): void
    {
        $unites = [
            'Fréquence Cardiaque' => 'bpm',
            'Tension' => 'mmHg',
            'SpO2' => '%',
            'Température' => '°C',
            'Glycémie' => 'mg/dL',
        ];

        if (isset($unites[$suivi->getTypeDonnee()])) {
            $suivi->setUnite($unites[$suivi->getTypeDonnee()]);
        }
    }

    private function calculateUrgencyLevel(Suivi $suivi): string
    {
        if ($suivi->isCritical()) {
            return 'Critique';
        }

        return match ($suivi->getTypeDonnee()) {
            'Fréquence Cardiaque' => $suivi->getValeur() >= 100 && $suivi->getValeur() <= 120 ? 'Stable' : 'Normal',
            'SpO2' => $suivi->getValeur() >= 90 && $suivi->getValeur() < 95 ? 'Stable' : 'Normal',
            'Température' => $suivi->getValeur() > 37.5 && $suivi->getValeur() <= 39 ? 'Stable' : 'Normal',
            'Glycémie' => (($suivi->getValeur() >= 200 && $suivi->getValeur() <= 250) || ($suivi->getValeur() >= 70 && $suivi->getValeur() <= 100)) ? 'Stable' : 'Normal',
            default => 'Normal',
        };
    }

    private function createCriticalIntervention(Suivi $suivi, EntityManagerInterface $entityManager): void
    {
        $intervention = new Intervention();
        $intervention->setType('Alerte SOS');
        $intervention->setSuiviOrigine($suivi);
        $intervention->setDatePlanifiee(new \DateTimeImmutable());
        $intervention->setStatut('En attente');
        $intervention->setDescription($this->generateInterventionDescription($suivi));

        $entityManager->persist($intervention);
        $entityManager->flush();

        $this->addFlash('danger', 'Alerte critique ! Une intervention d\'urgence (SOS) a été créée automatiquement.');
    }

    private function generateInterventionDescription(Suivi $suivi): string
    {
        $typeDonnee = $suivi->getTypeDonnee();
        $valeur = $suivi->getFormattedValue();
        $patientName = $suivi->getPatient()->getPrenom() . ' ' . $suivi->getPatient()->getNom();

        return match ($typeDonnee) {
            'Fréquence Cardiaque' => "ALERTE URGENTE: La fréquence cardiaque du patient $patientName est critique à $valeur.",
            'SpO2' => "ALERTE URGENTE: Le niveau d'oxygénation du patient $patientName est critique à $valeur.",
            'Température' => "ALERTE URGENTE: La température corporelle du patient $patientName est critique à $valeur.",
            'Glycémie' => "ALERTE URGENTE: Le taux de glucose du patient $patientName est critique à $valeur.",
            'Tension' => "ALERTE URGENTE: La tension artérielle du patient $patientName est critique à $valeur.",
            default => "ALERTE URGENTE: Mesure critique détectée pour le patient $patientName ($valeur).",
        };
    }
}