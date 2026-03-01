<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\LigneCommandeRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient/panier')]
#[IsGranted('ROLE_USER')]
final class PatientPanierController extends AbstractController
{
    private function getLocalUser(EntityManagerInterface $em): User
    {
        $user = $this->getUser();
        if ($user instanceof User) return $user;

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'patient@cardiolink.tn']);
        if (!$user) {
            throw $this->createNotFoundException("User introuvable.");
        }
        return $user;
    }

    private function getOrCreatePanier(CommandeRepository $commandeRepo, EntityManagerInterface $em): Commande
    {
        $user   = $this->getLocalUser($em);
        $panier = $commandeRepo->findPanierByUser($user);
        if ($panier) return $panier;

        $panier = new Commande();
        $panier->setUser($user);
        $panier->setStatut(Commande::STATUT_PANIER);
        $panier->recalculateTotal();
        $em->persist($panier);
        $em->flush();
        return $panier;
    }

    #[Route('', name: 'patient_panier_show', methods: ['GET'])]
    public function show(CommandeRepository $commandeRepo, EntityManagerInterface $em): Response
    {
        $panier = $this->getOrCreatePanier($commandeRepo, $em);
        return $this->render('patient/panier/show.html.twig', [
            'commande' => $panier,
        ]);
    }

    #[Route('/add', name: 'patient_panier_add', methods: ['POST'])]
    public function add(
        Request $request,
        ProduitRepository $produitRepo,
        CommandeRepository $commandeRepo,
        EntityManagerInterface $em
    ): RedirectResponse {

        $produitId = (int) $request->request->get('produitId', 0);
        $quantite  = max(1, (int) $request->request->get('quantite', 1));

        if ($produitId <= 0) {
            $this->addFlash('danger', 'Produit invalide');
            return $this->redirectToRoute('patient_produit_index');
        }

        $produit = $produitRepo->find($produitId);
        if (!$produit) {
            $this->addFlash('danger', 'Produit introuvable');
            return $this->redirectToRoute('patient_produit_index');
        }

        $panier = $this->getOrCreatePanier($commandeRepo, $em);

        if (!$panier->canEditPanier()) {
            $this->addFlash('danger', 'Panier non modifiable');
            return $this->redirectToRoute('patient_panier_show');
        }

        foreach ($panier->getLignes() as $ligne) {
            $ligneProduit = $ligne->getProduit();
            if ($ligneProduit !== null && $ligneProduit->getId() === $produit->getId()) {
                $newQty = $ligne->getQuantite() + $quantite;
                if (($produit->getStock() ?? 0) < $newQty) {
                    $this->addFlash('danger', 'Stock insuffisant : '.$produit->getNom());
                    return $this->redirectToRoute('patient_produit_index');
                }
                $ligne->setQuantite($newQty);
                $panier->recalculateTotal();
                $em->flush();
                $this->addFlash('success', 'Quantité mise à jour');
                return $this->redirectToRoute('patient_panier_show');
            }
        }

        if (($produit->getStock() ?? 0) < $quantite) {
            $this->addFlash('danger', 'Stock insuffisant : '.$produit->getNom());
            return $this->redirectToRoute('patient_produit_index');
        }

        $ligne = new LigneCommande();
        $ligne->setProduit($produit);
        $ligne->setQuantite($quantite);
        $ligne->setPrixUnitaire((string) $produit->getPrix());

        $panier->addLigne($ligne);
        $panier->recalculateTotal();
        $em->persist($ligne);
        $em->flush();

        $this->addFlash('success', 'Produit ajouté au panier ✅');
        return $this->redirectToRoute('patient_panier_show');
    }

    #[Route('/ligne/{id}/update', name: 'patient_panier_update_ligne', methods: ['POST'])]
    public function updateLigne(
        int $id,
        Request $request,
        LigneCommandeRepository $ligneRepo,
        EntityManagerInterface $em
    ): RedirectResponse {

        $user  = $this->getLocalUser($em);
        $ligne = $ligneRepo->find($id);

        if (!$ligne) {
            $this->addFlash('danger', 'Ligne introuvable');
            return $this->redirectToRoute('patient_panier_show');
        }

        $commande = $ligne->getCommande();
        if (!$commande || $commande->getUser() === null || $commande->getUser()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès interdit');
            return $this->redirectToRoute('patient_panier_show');
        }
        if (!$commande->canEditPanier()) {
            $this->addFlash('danger', 'Panier non modifiable');
            return $this->redirectToRoute('patient_panier_show');
        }

        $quantite = max(1, (int) $request->request->get('quantite', 1));
        $produit  = $ligne->getProduit();

        if ($produit === null || ($produit->getStock() ?? 0) < $quantite) {
            $this->addFlash('danger', 'Stock insuffisant');
            return $this->redirectToRoute('patient_panier_show');
        }

        $ligne->setQuantite($quantite);
        $commande->recalculateTotal();
        $em->flush();

        $this->addFlash('success', 'Ligne mise à jour');
        return $this->redirectToRoute('patient_panier_show');
    }

    #[Route('/ligne/{id}/delete', name: 'patient_panier_delete_ligne', methods: ['POST'])]
    public function deleteLigne(
        int $id,
        LigneCommandeRepository $ligneRepo,
        EntityManagerInterface $em
    ): RedirectResponse {

        $user  = $this->getLocalUser($em);
        $ligne = $ligneRepo->find($id);

        if (!$ligne) {
            $this->addFlash('danger', 'Ligne introuvable');
            return $this->redirectToRoute('patient_panier_show');
        }

        $commande = $ligne->getCommande();
        if (!$commande || $commande->getUser() === null || $commande->getUser()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès interdit');
            return $this->redirectToRoute('patient_panier_show');
        }
        if (!$commande->canEditPanier()) {
            $this->addFlash('danger', 'Panier non modifiable');
            return $this->redirectToRoute('patient_panier_show');
        }

        $commande->removeLigne($ligne);
        $commande->recalculateTotal();
        $em->remove($ligne);
        $em->flush();

        $this->addFlash('success', 'Ligne supprimée');
        return $this->redirectToRoute('patient_panier_show');
    }

    #[Route('/checkout', name: 'patient_panier_checkout', methods: ['POST'])]
    public function checkout(CommandeRepository $commandeRepo, EntityManagerInterface $em): RedirectResponse
    {
        $user   = $this->getLocalUser($em);
        $panier = $commandeRepo->findPanierByUser($user);

        if (!$panier) {
            $this->addFlash('danger', 'Panier introuvable');
            return $this->redirectToRoute('patient_panier_show');
        }
        if (!$panier->canEditPanier()) {
            $this->addFlash('danger', 'Panier non modifiable');
            return $this->redirectToRoute('patient_panier_show');
        }
        if ($panier->getLignes()->count() === 0) {
            $this->addFlash('danger', 'Panier vide');
            return $this->redirectToRoute('patient_panier_show');
        }

        foreach ($panier->getLignes() as $l) {
            $p = $l->getProduit();
            if ($p === null || ($p->getStock() ?? 0) < $l->getQuantite()) {
                $this->addFlash('danger', 'Stock insuffisant');
                return $this->redirectToRoute('patient_panier_show');
            }
        }

        try {
            $panier->recalculateTotal();
            $panier->validerCommande();
            $em->flush();

            $this->addFlash('success', '✅ Commande validée ! Procédez au paiement.');
            return $this->redirectToRoute('patient_commandes_index');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur : '.$e->getMessage());
            return $this->redirectToRoute('patient_panier_show');
        }
    }
}