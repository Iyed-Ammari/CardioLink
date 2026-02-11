<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/commandes')]
final class AdminCommandeController extends AbstractController
{
    #[Route('', name: 'admin_commandes_index', methods: ['GET'])]
    public function index(CommandeRepository $repo)
    {

        $commandes = $repo->findAllNonPanier();

        return $this->render('admin/commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/{id}/pay', name: 'admin_commande_pay', methods: ['POST'])]
    public function pay(int $id, Request $request, CommandeRepository $repo, EntityManagerInterface $em): RedirectResponse
    {

        if (!$this->isCsrfTokenValid('admin_pay_'.$id, (string)$request->request->get('_token'))) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        $commande = $repo->find($id);
        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable');
            return $this->redirectToRoute('admin_commandes_index');
        }

        if ($commande->getStatut() !== Commande::STATUT_EN_ATTENTE_PAIEMENT) {
            $this->addFlash('danger', 'Transition interdite (PAYEE uniquement depuis EN_ATTENTE_PAIEMENT)');
            return $this->redirectToRoute('admin_commandes_index');
        }

        try {
            $commande->marquerPayee();
            $em->flush();
            $this->addFlash('success', '✅ Commande marquée PAYEE');
        } catch (\Throwable $e) {
            $this->addFlash('danger', '❌ Impossible de marquer PAYEE: '.$e->getMessage());
        }

        return $this->redirectToRoute('admin_commandes_index');
    }

    #[Route('/{id}/deliver', name: 'admin_commande_deliver', methods: ['POST'])]
    public function deliver(int $id, Request $request, CommandeRepository $repo, EntityManagerInterface $em): RedirectResponse
    {

        if (!$this->isCsrfTokenValid('admin_deliver_'.$id, (string)$request->request->get('_token'))) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        $commande = $repo->find($id);
        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable');
            return $this->redirectToRoute('admin_commandes_index');
        }

        if ($commande->getStatut() !== Commande::STATUT_PAYEE) {
            $this->addFlash('danger', 'Transition interdite (LIVREE uniquement depuis PAYEE)');
            return $this->redirectToRoute('admin_commandes_index');
        }

        try {
            $commande->marquerLivree();
            $em->flush();
            $this->addFlash('success', '✅ Commande marquée LIVREE');
        } catch (\Throwable $e) {
            $this->addFlash('danger', '❌ Impossible de marquer LIVREE: '.$e->getMessage());
        }

        return $this->redirectToRoute('admin_commandes_index');
    }
}
