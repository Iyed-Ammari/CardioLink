<?php

namespace App\Controller;

use App\Entity\Intervention;
use App\Entity\User;
use App\Form\InterventionFormType;
use App\Repository\InterventionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/intervention')]
class InterventionController extends AbstractController
{
    #[Route('', name: 'app_intervention_index', methods: ['GET'])]
    public function index(InterventionRepository $interventionRepository): Response
    {
        $interventions = $interventionRepository->findPending();

        return $this->render('intervention/index.html.twig', [
            'interventions' => $interventions,
        ]);
    }

    #[Route('/urgent', name: 'app_intervention_urgent', methods: ['GET'])]
    public function urgent(InterventionRepository $interventionRepository): Response
    {
        $interventions = $interventionRepository->findUrgentSOS();

        return $this->render('intervention/urgent.html.twig', [
            'interventions' => $interventions,
        ]);
    }

    #[Route('/{id}/voir', name: 'app_intervention_show', methods: ['GET'])]
    public function show(Intervention $intervention): Response
    {
        return $this->render('intervention/show.html.twig', [
            'intervention' => $intervention,
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_intervention_accept', methods: ['POST'])]
    public function accept(
        Request $request,
        Intervention $intervention,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $medecin
    ): Response {
        if ($this->isCsrfTokenValid('accept' . $intervention->getId(), $request->request->get('_token'))) {
            $intervention->setStatut('Acceptée');
            $intervention->setMedecin($medecin);

            $entityManager->flush();

            $this->addFlash('success', 'L\'intervention a été acceptée.');
        }

        return $this->redirectToRoute('app_intervention_show', ['id' => $intervention->getId()]);
    }

    #[Route('/{id}/marquer-effectuee', name: 'app_intervention_complete', methods: ['POST'])]
    public function complete(
        Request $request,
        Intervention $intervention,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('complete' . $intervention->getId(), $request->request->get('_token'))) {
            $intervention->markAsCompleted();

            $entityManager->flush();

            $this->addFlash('success', 'L\'intervention a été marquée comme effectuée.');
        }

        return $this->redirectToRoute('app_intervention_show', ['id' => $intervention->getId()]);
    }

    #[Route('/{id}/modifier', name: 'app_intervention_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Intervention $intervention,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(InterventionFormType::class, $intervention);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'L\'intervention a été modifiée avec succès.');

            return $this->redirectToRoute('app_intervention_show', ['id' => $intervention->getId()]);
        }

        return $this->render('intervention/edit.html.twig', [
            'form' => $form,
            'intervention' => $intervention,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_intervention_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Intervention $intervention,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $intervention->getId(), $request->request->get('_token'))) {
            $entityManager->remove($intervention);
            $entityManager->flush();

            $this->addFlash('success', 'L\'intervention a été supprimée.');
        }

        return $this->redirectToRoute('app_intervention_index');
    }
}
