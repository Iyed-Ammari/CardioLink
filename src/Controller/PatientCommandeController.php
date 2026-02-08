<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/patient/commandes')]
final class PatientCommandeController extends AbstractController
{
    #[Route('', name: 'patient_commandes_index', methods: ['GET'])]
    public function index(CommandeRepository $repo)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $commandes = $repo->findCommandesByUser($user);

        return $this->render('patient/commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/{id}/cancel', name: 'patient_commande_cancel', methods: ['POST'])]
public function cancel(int $id, CommandeRepository $repo, EntityManagerInterface $em): RedirectResponse
{
    $this->denyAccessUnlessGranted('ROLE_USER');
    /** @var \App\Entity\User $user */
    $user = $this->getUser();

    $commande = $repo->find($id);
    if (!$commande) {
        $this->addFlash('danger', 'Commande introuvable');
        return $this->redirectToRoute('patient_commandes_index');
    }

    if ($commande->getUser()->getId() !== $user->getId()) {
        $this->addFlash('danger', 'Accès interdit');
        return $this->redirectToRoute('patient_commandes_index');
    }

    // ✅ verrou métier
    if (!in_array($commande->getStatut(), [
        Commande::STATUT_PANIER,
        Commande::STATUT_EN_ATTENTE_PAIEMENT
    ], true)) {
        $this->addFlash('danger', 'Annulation interdite pour ce statut');
        return $this->redirectToRoute('patient_commandes_index');
    }

    // Restock seulement si EN_ATTENTE_PAIEMENT
    if ($commande->getStatut() === Commande::STATUT_EN_ATTENTE_PAIEMENT) {
        foreach ($commande->getLignes() as $l) {
            $p = $l->getProduit();
            $p->setStock(($p->getStock() ?? 0) + $l->getQuantite());
        }
    }

    $commande->annuler();
    $em->flush();

    $this->addFlash('success', 'Commande annulée');
    return $this->redirectToRoute('patient_commandes_index');
}
}