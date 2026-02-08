<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Repository\CommandeRepository;
use App\Repository\LigneCommandeRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/patient/panier')]
final class PatientPanierController extends AbstractController
{
    private function getOrCreatePanier(CommandeRepository $commandeRepo, EntityManagerInterface $em): Commande
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) throw $this->createAccessDeniedException('Non authentifié');

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
    public function show(CommandeRepository $commandeRepo, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

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
        $this->denyAccessUnlessGranted('ROLE_USER');

        $produitId = (int) $request->request->get('produitId', 0);
        $quantite = (int) $request->request->get('quantite', 1);
        if ($quantite < 1) $quantite = 1;

        if ($produitId <= 0) {
            $this->addFlash('danger', 'produitId requis');
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

        // ✅ si ligne existe => augmente quantité + contrôle stock
        foreach ($panier->getLignes() as $ligne) {
            if ($ligne->getProduit()->getId() === $produit->getId()) {
                $newQty = $ligne->getQuantite() + $quantite;

                if (($produit->getStock() ?? 0) < $newQty) {
                    $this->addFlash('danger', 'Stock insuffisant: '.$produit->getNom());
                    return $this->redirectToRoute('patient_produit_index');
                }

                $ligne->setQuantite($newQty);
                $panier->recalculateTotal(); // ✅ total garanti
                $em->flush();

                $this->addFlash('success', 'Quantité mise à jour dans le panier');
                return $this->redirectToRoute('patient_panier_show');
            }
        }

        if (($produit->getStock() ?? 0) < $quantite) {
            $this->addFlash('danger', 'Stock insuffisant: '.$produit->getNom());
            return $this->redirectToRoute('patient_produit_index');
        }

        $ligne = new LigneCommande();
        $ligne->setProduit($produit);
        $ligne->setQuantite($quantite);
        $ligne->setPrixUnitaire($produit->getPrix()); // gel prix

        $panier->addLigne($ligne);
        $panier->recalculateTotal(); // ✅ total garanti

        $em->persist($ligne);
        $em->flush();

        $this->addFlash('success', 'Produit ajouté au panier');
        return $this->redirectToRoute('patient_panier_show');
    }

    #[Route('/ligne/{id}/update', name: 'patient_panier_update_ligne', methods: ['POST'])]
    public function updateLigne(
        int $id,
        Request $request,
        LigneCommandeRepository $ligneRepo,
        EntityManagerInterface $em
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ligne = $ligneRepo->find($id);
        if (!$ligne) {
            $this->addFlash('danger', 'Ligne introuvable');
            return $this->redirectToRoute('patient_panier_show');
        }

        $commande = $ligne->getCommande();
        if (!$commande || $commande->getUser()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès interdit');
            return $this->redirectToRoute('patient_panier_show');
        }
        if (!$commande->canEditPanier()) {
            $this->addFlash('danger', 'Panier non modifiable');
            return $this->redirectToRoute('patient_panier_show');
        }

        $quantite = (int) $request->request->get('quantite', 1);
        if ($quantite < 1) $quantite = 1;

        // ✅ contrôle stock
        $produit = $ligne->getProduit();
        if (($produit->getStock() ?? 0) < $quantite) {
            $this->addFlash('danger', 'Stock insuffisant: '.$produit->getNom());
            return $this->redirectToRoute('patient_panier_show');
        }

        $ligne->setQuantite($quantite);
        $commande->recalculateTotal(); // ✅ total garanti
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
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ligne = $ligneRepo->find($id);
        if (!$ligne) {
            $this->addFlash('danger', 'Ligne introuvable');
            return $this->redirectToRoute('patient_panier_show');
        }

        $commande = $ligne->getCommande();
        if (!$commande || $commande->getUser()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès interdit');
            return $this->redirectToRoute('patient_panier_show');
        }
        if (!$commande->canEditPanier()) {
            $this->addFlash('danger', 'Panier non modifiable');
            return $this->redirectToRoute('patient_panier_show');
        }

        $commande->removeLigne($ligne);
        $commande->recalculateTotal(); // ✅ total garanti

        $em->remove($ligne);
        $em->flush();

        $this->addFlash('success', 'Ligne supprimée');
        return $this->redirectToRoute('patient_panier_show');
    }

    #[Route('/checkout', name: 'patient_panier_checkout', methods: ['POST'])]
    public function checkout(CommandeRepository $commandeRepo, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

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

        $em->beginTransaction();
        try {
            // ✅ lock pessimiste sur produits
            foreach ($panier->getLignes() as $l) {
                $em->lock($l->getProduit(), LockMode::PESSIMISTIC_WRITE);
            }

            // recheck stock
            foreach ($panier->getLignes() as $l) {
                $p = $l->getProduit();
                if (($p->getStock() ?? 0) < $l->getQuantite()) {
                    $em->rollback();
                    $this->addFlash('danger', 'Stock insuffisant: '.$p->getNom());
                    return $this->redirectToRoute('patient_panier_show');
                }
            }

            // décrémenter stock
            foreach ($panier->getLignes() as $l) {
                $p = $l->getProduit();
                $p->setStock(($p->getStock() ?? 0) - $l->getQuantite());
            }

            $panier->recalculateTotal(); // ✅ total garanti
            $panier->validerCommande();  // -> EN_ATTENTE_PAIEMENT

            $em->flush();
            $em->commit();

            $this->addFlash('success', 'Commande validée (EN_ATTENTE_PAIEMENT)');
            return $this->redirectToRoute('patient_commandes_index');
        } catch (\Throwable $e) {
            $em->rollback();
            $this->addFlash('danger', 'Checkout échoué: '.$e->getMessage());
            return $this->redirectToRoute('patient_panier_show');
        }
    }
}
