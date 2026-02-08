<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/commandes')]
final class AdminCommandeApiController extends AbstractController
{
    private function denyIfNotAdmin(): ?JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Accès admin requis'], 403);
        }
        return null;
    }

    #[Route('', name: 'api_admin_commandes_list', methods: ['GET'])]
    public function list(CommandeRepository $repo): JsonResponse
    {
        if ($resp = $this->denyIfNotAdmin()) return $resp;

        $items = $repo->findAllNonPanier();

        $out = array_map(function (Commande $c) {
            return [
                'id' => $c->getId(),
                'patient' => $c->getUser()->getEmail(),
                'statut' => $c->getStatut(),
                'dateCommande' => $c->getDateCommande()?->format('Y-m-d H:i:s'),
                'montantTotal' => $c->getMontantTotal(),
                'nbLignes' => $c->getLignes()->count(),
            ];
        }, $items);

        return $this->json($out);
    }

    #[Route('/{id}/pay', name: 'api_admin_commandes_pay', methods: ['POST'])]
    public function pay(int $id, CommandeRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        if ($resp = $this->denyIfNotAdmin()) return $resp;

        $commande = $repo->find($id);
        if (!$commande) return $this->json(['error' => 'Commande introuvable'], 404);

        // ✅ transition autorisée uniquement si EN_ATTENTE_PAIEMENT
        if ($commande->getStatut() !== Commande::STATUT_EN_ATTENTE_PAIEMENT) {
            return $this->json([
                'error' => 'Transition interdite',
                'from' => $commande->getStatut(),
                'to' => Commande::STATUT_PAYEE,
            ], 409);
        }

        try {
            $commande->marquerPayee();
            $em->flush();
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Impossible de marquer PAYEE', 'details' => $e->getMessage()], 409);
        }

        return $this->json(['message' => 'Commande marquée PAYEE', 'statut' => $commande->getStatut()]);
    }

    #[Route('/{id}/deliver', name: 'api_admin_commandes_deliver', methods: ['POST'])]
    public function deliver(int $id, CommandeRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        if ($resp = $this->denyIfNotAdmin()) return $resp;

        $commande = $repo->find($id);
        if (!$commande) return $this->json(['error' => 'Commande introuvable'], 404);

        // ✅ transition autorisée uniquement si PAYEE
        if ($commande->getStatut() !== Commande::STATUT_PAYEE) {
            return $this->json([
                'error' => 'Transition interdite',
                'from' => $commande->getStatut(),
                'to' => Commande::STATUT_LIVREE,
            ], 409);
        }

        try {
            $commande->marquerLivree();
            $em->flush();
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Impossible de marquer LIVREE', 'details' => $e->getMessage()], 409);
        }

        return $this->json(['message' => 'Commande marquée LIVREE', 'statut' => $commande->getStatut()]);
    }
}
