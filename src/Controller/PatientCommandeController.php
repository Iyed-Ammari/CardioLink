<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/patient/commandes')]
final class PatientCommandeController extends AbstractController
{
    private function getLocalUser(EntityManagerInterface $em): User
    {
        // ✅ email de test (mets celui qui existe dans ta DB)
        $email = 'patient@cardiolink.tn';

        $user = $this->getUser();
        if ($user instanceof User) {
            return $user;
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            throw $this->createNotFoundException("User local introuvable: $email. Crée-le en base.");
        }
        return $user;
    }

    #[Route('', name: 'patient_commandes_index', methods: ['GET'])]
    public function index(CommandeRepository $repo, EntityManagerInterface $em)
    {
        $user = $this->getLocalUser($em);

        $commandes = $repo->findCommandesByUser($user);

        return $this->render('patient/commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/{id}/cancel', name: 'patient_commande_cancel', methods: ['POST'])]
    public function cancel(int $id, CommandeRepository $repo, EntityManagerInterface $em): RedirectResponse
    {
        $user = $this->getLocalUser($em);

        $commande = $repo->find($id);
        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable');
            return $this->redirectToRoute('patient_commandes_index');
        }

        if ($commande->getUser()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès interdit');
            return $this->redirectToRoute('patient_commandes_index');
        }

        if (!in_array($commande->getStatut(), [
            Commande::STATUT_PANIER,
            Commande::STATUT_EN_ATTENTE_PAIEMENT
        ], true)) {
            $this->addFlash('danger', 'Annulation interdite pour ce statut');
            return $this->redirectToRoute('patient_commandes_index');
        }

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
