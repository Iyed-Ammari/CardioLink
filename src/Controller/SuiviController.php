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
    #[Route('', name: 'app_suivi_index', methods: ['GET'])]
    public function index(
        SuiviRepository $suiviRepository,
        #[CurrentUser] User $user
    ): Response {
        $suivis = $suiviRepository->findByPatient($user->getId());

        return $this->render('suivi/index.html.twig', [
            'suivis' => $suivis,
            'user' => $user,
        ]);
    }

    #[Route('/nouveau', name: 'app_suivi_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        InterventionRepository $interventionRepository,
        #[CurrentUser] User $user
    ): Response {
        $suivi = new Suivi();
        $suivi->setPatient($user);
        $suivi->setDateSaisie(new \DateTimeImmutable());

        $form = $this->createForm(SuiviFormType::class, $suivi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Remplir automatiquement l'unité selon le type de donnée
            $this->setUnitByTypeDonnee($suivi);

            // Calculer le niveau d'urgence
            $suivi->setNiveauUrgence($this->calculateUrgencyLevel($suivi));

            $entityManager->persist($suivi);
            $entityManager->flush();

            // Vérifier si le suivi est critique et créer une intervention si nécessaire
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
    public function show(
        Suivi $suivi,
        #[CurrentUser] User $user
    ): Response {
        // Vérifier que l'utilisateur est le propriétaire du suivi
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
        // Vérifier que l'utilisateur est le propriétaire du suivi
        if ($suivi->getPatient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce suivi.');
        }

        $form = $this->createForm(SuiviFormType::class, $suivi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Remplir automatiquement l'unité selon le type de donnée
            $this->setUnitByTypeDonnee($suivi);

            // Recalculer le niveau d'urgence
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
        // Vérifier que l'utilisateur est le propriétaire du suivi
        if ($suivi->getPatient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce suivi.');
        }

        if ($this->isCsrfTokenValid('delete' . $suivi->getId(), $request->request->get('_token'))) {
            // Supprimer l'intervention associée si elle existe
            if ($suivi->getIntervention()) {
                $entityManager->remove($suivi->getIntervention());
            }

            $entityManager->remove($suivi);
            $entityManager->flush();

            $this->addFlash('success', 'Le suivi a été supprimé.');
        }

        return $this->redirectToRoute('app_suivi_index');
    }

    /**
     * Définit automatiquement l'unité de mesure selon le type de donnée.
     */
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

    /**
     * Calcule le niveau d'urgence basé sur les critères médicaux.
     */
    private function calculateUrgencyLevel(Suivi $suivi): string
    {
        if ($suivi->isCritical()) {
            return 'Critique';
        }

        // Vérifier les plages "Stable"
        return match ($suivi->getTypeDonnee()) {
            'Fréquence Cardiaque' => $suivi->getValeur() >= 100 && $suivi->getValeur() <= 120 ? 'Stable' : 'Normal',
            'SpO2' => $suivi->getValeur() >= 90 && $suivi->getValeur() < 95 ? 'Stable' : 'Normal',
            'Température' => $suivi->getValeur() > 37.5 && $suivi->getValeur() <= 39 ? 'Stable' : 'Normal',
            'Glycémie' => (($suivi->getValeur() >= 200 && $suivi->getValeur() <= 250) || ($suivi->getValeur() >= 70 && $suivi->getValeur() <= 100)) ? 'Stable' : 'Normal',
            default => 'Normal',
        };
    }

    /**
     * Crée automatiquement une intervention si le suivi est critique.
     */
    private function createCriticalIntervention(Suivi $suivi, EntityManagerInterface $entityManager): void
    {
        $intervention = new Intervention();
        $intervention->setType('Alerte SOS');
        $intervention->setSuiviOrigine($suivi);
        $intervention->setDatePlanifiee(new \DateTimeImmutable());
        $intervention->setStatut('En attente');

        // Générer une description automatique selon le type de donnée
        $description = $this->generateInterventionDescription($suivi);
        $intervention->setDescription($description);

        $entityManager->persist($intervention);
        $entityManager->flush();

        $this->addFlash('danger', 'Alerte critique ! Une intervention d\'urgence (SOS) a été créée automatiquement.');
    }

    /**
     * Génère automatiquement une description pour l'intervention basée sur le suivi critique.
     */
    private function generateInterventionDescription(Suivi $suivi): string
    {
        $typeDonnee = $suivi->getTypeDonnee();
        $valeur = $suivi->getFormattedValue();
        $patient = $suivi->getPatient();
        $patientName = $patient->getPrenom() . ' ' . $patient->getNom();

        return match ($typeDonnee) {
            'Fréquence Cardiaque' => "ALERTE URGENTE: La fréquence cardiaque du patient $patientName est critique à $valeur. Intervention d'urgence requise immédiatement.",
            'SpO2' => "ALERTE URGENTE: Le niveau d'oxygénation du patient $patientName est critique à $valeur. Intervention d'urgence requise immédiatement.",
            'Température' => "ALERTE URGENTE: La température corporelle du patient $patientName est critique à $valeur. Intervention d'urgence requise immédiatement.",
            'Glycémie' => "ALERTE URGENTE: Le taux de glucose du patient $patientName est critique à $valeur. Intervention d'urgence requise immédiatement.",
            'Tension' => "ALERTE URGENTE: La tension artérielle du patient $patientName est critique à $valeur. Intervention d'urgence requise immédiatement.",
            default => "ALERTE URGENTE: Mesure critique détectée pour le patient $patientName ($valeur). Intervention d'urgence requise immédiatement.",
        };
    }
}
