<?php
// src/Controller/ProduitApiController.php

namespace App\Controller;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/produits')]
class ProduitApiController extends AbstractController
{
    private function produitToArray(Produit $p): array
    {
        return [
            'id'          => $p->getId(),
            'nom'         => $p->getNom(),
            'description' => $p->getDescription(),
            'prix'        => (float) $p->getPrix(),
            'stock'       => $p->getStock(),
            'stockStatus' => $p->getStockStatus(),
            'imageUrl'    => $p->getImageUrl(),
            'categorie'   => $p->getCategorie(),
        ];
    }

    private function validationErrorsToJson($errors): array
    {
        $out = [];
        foreach ($errors as $e) {
            $out[] = [
                'field'   => $e->getPropertyPath(),
                'message' => $e->getMessage(),
            ];
        }
        return $out;
    }

    // ✅ GET /api/produits — accessible à tous les utilisateurs connectés
    #[Route('', name: 'api_produit_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(ProduitRepository $repo): JsonResponse
    {
        $produits = $repo->findBy([], ['id' => 'DESC']);
        return $this->json(array_map(fn(Produit $p) => $this->produitToArray($p), $produits));
    }

    // ✅ GET /api/produits/search — accessible à tous les utilisateurs connectés
    #[Route('/search', name: 'api_produit_search', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function search(Request $request, ProduitRepository $repo): JsonResponse
    {
        $q           = $request->query->get('q');
        $minPrix     = $request->query->get('minPrix');
        $maxPrix     = $request->query->get('maxPrix');
        $stockStatus = $request->query->get('stockStatus');
        $page        = max(1, (int) $request->query->get('page', 1));
        $limit       = min(50, max(1, (int) $request->query->get('limit', 10)));
        $categorie   = $request->query->get('categorie');

        $produits = $repo->search(
            $q ?: null,
            ($minPrix !== null && $minPrix !== '') ? (float) $minPrix : null,
            ($maxPrix !== null && $maxPrix !== '') ? (float) $maxPrix : null,
            $stockStatus ?: null,
            $page,
            $limit,
            $categorie ?: null
        );

        return $this->json([
            'page'  => $page,
            'limit' => $limit,
            'items' => array_map(fn(Produit $p) => $this->produitToArray($p), $produits),
        ]);
    }

    // ✅ GET /api/produits/{id} — accessible à tous les utilisateurs connectés
    #[Route('/{id}', name: 'api_produit_show', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(Produit $produit): JsonResponse
    {
        return $this->json($this->produitToArray($produit));
    }

    // ✅ POST /api/produits — ADMIN uniquement
    #[Route('', name: 'api_produit_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')] // ✅ CORRECTION : seul l'admin peut créer via API
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        ProduitRepository $repo
    ): JsonResponse {
        $nom         = (string) $request->request->get('nom', '');
        $description = $request->request->get('description');
        $prix        = (string) $request->request->get('prix', '0');
        $stock       = (int) $request->request->get('stock', 0);
        $imageUrl    = $request->request->get('imageUrl');
        $categorie   = $request->request->get('categorie');

        /** @var UploadedFile|null $file */
        $file = $request->files->get('image');

        if ($nom !== '' && $repo->findOneBy(['nom' => $nom])) {
            return $this->json([
                'errors' => [
                    ['field' => 'nom', 'message' => 'Ce produit existe déjà (nom unique).']
                ]
            ], 422);
        }

        $produit = new Produit();
        $produit->setNom($nom);
        $produit->setDescription($description);
        $produit->setPrix($prix);
        $produit->setStock($stock);
        $produit->setCategorie($categorie);

        if (!$file && $imageUrl) {
            $produit->setImageUrl($imageUrl);
        }

        $errors = $validator->validate($produit);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->validationErrorsToJson($errors)], 422);
        }

        $em->persist($produit);
        $em->flush();

        if ($file) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowed, true)) {
                return $this->json(['error' => 'Format non supporté (jpeg/png/webp).'], 422);
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/produits';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = 'p'.$produit->getId().'_'.bin2hex(random_bytes(6)).'.'.$file->guessExtension();
            $file->move($uploadDir, $filename);

            $produit->setImageUrl('/uploads/produits/'.$filename);
            $em->flush();
        }

        return $this->json([
            'message' => 'Produit créé',
            'produit' => $this->produitToArray($produit),
        ], 201);
    }

    // ✅ DELETE /api/produits/{id} — ADMIN uniquement
    #[Route('/{id}', name: 'api_produit_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')] 
    public function delete(Produit $produit, EntityManagerInterface $em): JsonResponse
    {
        if (($produit->getStock() ?? 0) <= 0) {
            return $this->json(['error' => 'Suppression interdite: produit en rupture (stock=0).'], 409);
        }

        if ($produit->getLignes()->count() > 0) {
            return $this->json(['error' => 'Suppression interdite: produit déjà utilisé dans une commande.'], 409);
        }

        $em->remove($produit);
        $em->flush();

        return $this->json(['message' => 'Produit supprimé']);
    }
}