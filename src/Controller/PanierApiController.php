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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/panier')]
final class PanierApiController extends AbstractController
{
    private function commandeToArray(Commande $c): array
    {
        return [
            'id' => $c->getId(),
            'statut' => $c->getStatut(),
            'dateCommande' => $c->getDateCommande()?->format('Y-m-d H:i:s'),
            'montantTotal' => $c->getMontantTotal(),
            'lignes' => array_map(fn(LigneCommande $l) => [
                'id' => $l->getId(),
                'produitId' => $l->getProduit()->getId(),
                'produitNom' => $l->getProduit()->getNom(),
                'prixUnitaire' => $l->getPrixUnitaire(),
                'quantite' => $l->getQuantite(),
                'totalLigne' => $l->getTotalLigne(),
                'stockActuelProduit' => $l->getProduit()->getStock(),
            ], $c->getLignes()->toArray()),
        ];
    }

    private function getUserOr401(): ?JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }
        return null;
    }

    private function getOrCreatePanier(CommandeRepository $commandeRepo, EntityManagerInterface $em): Commande
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

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

    #[Route('', name: 'api_panier_show', methods: ['GET'])]
    public function show(CommandeRepository $commandeRepo, EntityManagerInterface $em): JsonResponse
    {
        if ($resp = $this->getUserOr401()) return $resp;

        $panier = $this->getOrCreatePanier($commandeRepo, $em);
        return $this->json($this->commandeToArray($panier));
    }

    #[Route('/add', name: 'api_panier_add', methods: ['POST'])]
    public function add(
        Request $request,
        ProduitRepository $produitRepo,
        CommandeRepository $commandeRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($resp = $this->getUserOr401()) return $resp;

        $data = json_decode($request->getContent(), true) ?: [];
        $produitId = (int)($data['produitId'] ?? 0);
        $quantite = (int)($data['quantite'] ?? 1);

        if ($produitId <= 0) return $this->json(['error' => 'produitId requis'], 422);
        if ($quantite < 1) return $this->json(['error' => 'quantite doit être >= 1'], 422);

        $produit = $produitRepo->find($produitId);
        if (!$produit) return $this->json(['error' => 'Produit introuvable'], 404);

        $panier = $this->getOrCreatePanier($commandeRepo, $em);

        if (!$panier->canEditPanier()) {
            return $this->json(['error' => 'Panier non modifiable'], 409);
        }

        // ✅ contrôle stock (nouvelle quantité totale)
        foreach ($panier->getLignes() as $ligne) {
            if ($ligne->getProduit()->getId() === $produit->getId()) {
                $newQty = $ligne->getQuantite() + $quantite;
                if (($produit->getStock() ?? 0) < $newQty) {
                    return $this->json([
                        'error' => 'Stock insuffisant',
                        'produitId' => $produit->getId(),
                        'produit' => $produit->getNom(),
                        'stock' => $produit->getStock(),
                        'demande' => $newQty,
                    ], 409);
                }

                $ligne->setQuantite($newQty);
                $panier->recalculateTotal(); // ✅ total garanti
                $em->flush();

                return $this->json(['message' => 'Quantité mise à jour', 'panier' => $this->commandeToArray($panier)]);
            }
        }

        if (($produit->getStock() ?? 0) < $quantite) {
            return $this->json([
                'error' => 'Stock insuffisant',
                'produitId' => $produit->getId(),
                'produit' => $produit->getNom(),
                'stock' => $produit->getStock(),
                'demande' => $quantite,
            ], 409);
        }

        $ligne = new LigneCommande();
        $ligne->setProduit($produit);
        $ligne->setQuantite($quantite);
        $ligne->setPrixUnitaire($produit->getPrix()); // gel prix

        $panier->addLigne($ligne);
        $panier->recalculateTotal(); // ✅ total garanti

        $em->persist($ligne);
        $em->flush();

        return $this->json(['message' => 'Produit ajouté au panier', 'panier' => $this->commandeToArray($panier)], 201);
    }

    #[Route('/ligne/{id}', name: 'api_panier_update_ligne', methods: ['PATCH'])]
    public function updateLigne(
        int $id,
        Request $request,
        LigneCommandeRepository $ligneRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($resp = $this->getUserOr401()) return $resp;
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ligne = $ligneRepo->find($id);
        if (!$ligne) return $this->json(['error' => 'Ligne introuvable'], 404);

        $commande = $ligne->getCommande();
        if (!$commande || $commande->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès interdit'], 403);
        }
        if (!$commande->canEditPanier()) {
            return $this->json(['error' => 'Panier non modifiable'], 409);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $quantite = (int)($data['quantite'] ?? 0);
        if ($quantite < 1) return $this->json(['error' => 'quantite doit être >= 1'], 422);

        // ✅ contrôle stock sur la nouvelle quantité
        $produit = $ligne->getProduit();
        if (($produit->getStock() ?? 0) < $quantite) {
            return $this->json([
                'error' => 'Stock insuffisant',
                'produitId' => $produit->getId(),
                'produit' => $produit->getNom(),
                'stock' => $produit->getStock(),
                'demande' => $quantite,
            ], 409);
        }

        $ligne->setQuantite($quantite);
        $commande->recalculateTotal(); // ✅ total garanti
        $em->flush();

        return $this->json(['message' => 'Ligne mise à jour', 'panier' => $this->commandeToArray($commande)]);
    }

    #[Route('/ligne/{id}', name: 'api_panier_delete_ligne', methods: ['DELETE'])]
    public function deleteLigne(
        int $id,
        LigneCommandeRepository $ligneRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($resp = $this->getUserOr401()) return $resp;
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ligne = $ligneRepo->find($id);
        if (!$ligne) return $this->json(['error' => 'Ligne introuvable'], 404);

        $commande = $ligne->getCommande();
        if (!$commande || $commande->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès interdit'], 403);
        }
        if (!$commande->canEditPanier()) {
            return $this->json(['error' => 'Panier non modifiable'], 409);
        }

        $commande->removeLigne($ligne);
        $commande->recalculateTotal(); // ✅ total garanti

        $em->remove($ligne);
        $em->flush();

        return $this->json(['message' => 'Ligne supprimée', 'panier' => $this->commandeToArray($commande)]);
    }

   #[Route('/checkout', name: 'api_panier_checkout', methods: ['POST'])]
public function checkout(CommandeRepository $commandeRepo, EntityManagerInterface $em): JsonResponse
{
    if ($resp = $this->getUserOr401()) return $resp;

    /** @var \App\Entity\User $user */
    $user = $this->getUser();

    $panier = $commandeRepo->findPanierByUser($user);
    if (!$panier) return $this->json(['error' => 'Panier introuvable'], 404);
    if (!$panier->canEditPanier()) return $this->json(['error' => 'Panier non modifiable'], 409);
    if ($panier->getLignes()->count() === 0) return $this->json(['error' => 'Panier vide'], 409);

    $conn = $em->getConnection();
    $conn->beginTransaction();

    try {
        foreach ($panier->getLignes() as $l) {
            $em->lock($l->getProduit(), LockMode::PESSIMISTIC_WRITE);
        }

        foreach ($panier->getLignes() as $l) {
            $p = $l->getProduit();
            if (($p->getStock() ?? 0) < $l->getQuantite()) {
                $conn->rollBack();
                return $this->json([
                    'error' => 'Stock insuffisant',
                    'produit' => $p->getNom()
                ], 409);
            }
        }

        foreach ($panier->getLignes() as $l) {
            $p = $l->getProduit();
            $p->setStock(($p->getStock() ?? 0) - $l->getQuantite());
        }

        $panier->recalculateTotal();
        $panier->validerCommande();

        $em->flush();
        $conn->commit();

        return $this->json([
            'message' => 'Commande validée (EN_ATTENTE_PAIEMENT)',
            'commande' => $this->commandeToArray($panier)
        ]);
    } catch (\Throwable $e) {
        if ($conn->isTransactionActive()) {
            $conn->rollBack();
        }
        return $this->json([
            'error' => 'Checkout échoué',
            'details' => $e->getMessage()
        ], 500);
    }
}
}