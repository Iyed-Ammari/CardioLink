<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/commandes')]
final class CommandeApiController extends AbstractController
{
    private function getUserOr401(): ?JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }
        return null;
    }

    #[Route('', name: 'api_commandes_list', methods: ['GET'])]
    public function list(CommandeRepository $repo): JsonResponse
    {
        if ($resp = $this->getUserOr401()) return $resp;
        $items = $repo->findCommandesByUser($this->getUser());

        $out = array_map(function (Commande $c) {
            return [
                'id' => $c->getId(),
                'statut' => $c->getStatut(),
                'dateCommande' => $c->getDateCommande()?->format('Y-m-d H:i:s'),
                'montantTotal' => $c->getMontantTotal(),
                'nbLignes' => $c->getLignes()->count(),
            ];
        }, $items);

        return $this->json($out);
    }

    // POST /api/commandes/{id}/cancel
   #[Route('/{id}/cancel', name: 'api_commandes_cancel', methods: ['POST'])]
public function cancel(int $id, CommandeRepository $repo, EntityManagerInterface $em): JsonResponse
{
    if ($resp = $this->getUserOr401()) return $resp;
    /** @var \App\Entity\User $user */
    $user = $this->getUser();

    $commande = $repo->find($id);
    if (!$commande) {
        return $this->json(['error' => 'Commande introuvable'], 404);
    }

    if ($commande->getUser()->getId() !== $user->getId()) {
        return $this->json(['error' => 'Accès interdit'], 403);
    }

    // ✅ verrou métier
    if (!in_array($commande->getStatut(), [
        Commande::STATUT_PANIER,
        Commande::STATUT_EN_ATTENTE_PAIEMENT
    ], true)) {
        return $this->json([
            'error' => 'Annulation interdite pour ce statut',
            'statut' => $commande->getStatut()
        ], 409);
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

    return $this->json([
        'message' => 'Commande annulée',
        'statut' => $commande->getStatut()
    ]);
}
}
