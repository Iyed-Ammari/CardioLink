<?php
// src/Controller/CommandeApiController.php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commandes')]
#[IsGranted('ROLE_USER')]
final class CommandeApiController extends AbstractController
{
    #[Route('', name: 'api_commandes_list', methods: ['GET'])]
    public function list(CommandeRepository $repo): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $items = $repo->findCommandesByUser($user);

        $out = array_map(function (Commande $c) {
            return [
                'id'           => $c->getId(),
                'statut'       => $c->getStatut(),
                'dateCommande' => $c->getDateCommande()?->format('Y-m-d H:i:s'),
                'montantTotal' => $c->getMontantTotal(),
                'nbLignes'     => $c->getLignes()->count(),
            ];
        }, $items);

        return $this->json($out);
    }

    #[Route('/{id}/cancel', name: 'api_commandes_cancel', methods: ['POST'])]
    public function cancel(int $id, CommandeRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $commande = $repo->find($id);
        if (!$commande) {
            return $this->json(['error' => 'Commande introuvable'], 404);
        }

        $commandeUser = $commande->getUser();
        if ($commandeUser === null || $commandeUser->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès interdit'], 403);
        }

        if (!in_array($commande->getStatut(), [
            Commande::STATUT_PANIER,
            Commande::STATUT_EN_ATTENTE_PAIEMENT
        ], true)) {
            return $this->json([
                'error'  => 'Annulation interdite pour ce statut',
                'statut' => $commande->getStatut()
            ], 409);
        }

        $commande->annuler();
        $em->flush();

        return $this->json([
            'message' => 'Commande annulée',
            'statut'  => $commande->getStatut()
        ]);
    }
}