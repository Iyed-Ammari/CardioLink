<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/paiement')]
final class PaiementApiController extends AbstractController
{
    #[Route('/{id}/payer', name: 'api_paiement_payer', methods: ['POST'])]
    public function payer(
        int $id,
        Request $request,
        CommandeRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $commande = $repo->find($id);
        if (!$commande) {
            return $this->json(['error' => 'Commande introuvable'], 404);
        }
        if ($commande->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès interdit'], 403);
        }
        if ($commande->getStatut() !== Commande::STATUT_EN_ATTENTE_PAIEMENT) {
            return $this->json([
                'error' => 'Cette commande ne peut pas être payée',
                'statut' => $commande->getStatut()
            ], 409);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        $nomCarte    = trim((string)($data['nomCarte'] ?? ''));
        $numeroCarte = preg_replace('/[\s\-]/', '', (string)($data['numeroCarte'] ?? ''));
        $expiration  = trim((string)($data['expiration'] ?? ''));
        $cvv         = trim((string)($data['cvv'] ?? ''));

        $errors = [];

        if ($nomCarte === '') {
            $errors[] = ['field' => 'nomCarte', 'message' => 'Le nom sur la carte est obligatoire.'];
        } elseif (mb_strlen($nomCarte) < 2) {
            $errors[] = ['field' => 'nomCarte', 'message' => 'Le nom est trop court (min 2 caractères).'];
        } elseif (mb_strlen($nomCarte) > 100) {
            $errors[] = ['field' => 'nomCarte', 'message' => 'Le nom est trop long (max 100 caractères).'];
        } elseif (!preg_match('/^[\p{L}\s\-]+$/u', $nomCarte)) {
            $errors[] = ['field' => 'nomCarte', 'message' => 'Le nom ne doit contenir que des lettres.'];
        }

        if ($numeroCarte === '') {
            $errors[] = ['field' => 'numeroCarte', 'message' => 'Le numéro de carte est obligatoire.'];
        } elseif (!preg_match('/^\d{13,19}$/', $numeroCarte)) {
            $errors[] = ['field' => 'numeroCarte', 'message' => 'Le numéro de carte doit contenir entre 13 et 19 chiffres.'];
        }

        if ($expiration === '') {
            $errors[] = ['field' => 'expiration', 'message' => "La date d'expiration est obligatoire."];
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiration)) {
            $errors[] = ['field' => 'expiration', 'message' => "Format invalide. Attendu : MM/YY."];
        } else {
            [$mois, $annee] = explode('/', $expiration);
            $expTimestamp = mktime(0, 0, 0, (int)$mois + 1, 1, 2000 + (int)$annee);
            if ($expTimestamp < time()) {
                $errors[] = ['field' => 'expiration', 'message' => 'Cette carte est expirée.'];
            }
        }

        if ($cvv === '') {
            $errors[] = ['field' => 'cvv', 'message' => 'Le CVV est obligatoire.'];
        } elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
            $errors[] = ['field' => 'cvv', 'message' => 'Le CVV doit contenir 3 ou 4 chiffres.'];
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $conn = $em->getConnection();
        $conn->beginTransaction();

        try {
            foreach ($commande->getLignes() as $l) {
                $em->lock($l->getProduit(), LockMode::PESSIMISTIC_WRITE);
            }

            foreach ($commande->getLignes() as $l) {
                $p = $l->getProduit();
                if (($p->getStock() ?? 0) < $l->getQuantite()) {
                    $conn->rollBack();
                    return $this->json([
                        'error' => 'Stock insuffisant pour : ' . $p->getNom(),
                    ], 409);
                }
            }

            foreach ($commande->getLignes() as $l) {
                $p = $l->getProduit();
                $p->setStock(($p->getStock() ?? 0) - $l->getQuantite());
            }

            $commande->marquerPayee();

            $em->flush();
            $conn->commit();

            return $this->json([
                'message'      => 'Paiement accepté ! Commande PAYÉE.',
                'commandeId'   => $commande->getId(),
                'statut'       => $commande->getStatut(),
                'montantTotal' => $commande->getMontantTotal(),
            ]);

        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            return $this->json([
                'error'   => 'Paiement échoué',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}