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
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/produits')]
class ProduitApiController extends AbstractController
{
    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param ConstraintViolationListInterface<\Symfony\Component\Validator\ConstraintViolationInterface> $errors
     * @return array<int, array<string, string>>
     */
    private function validationErrorsToJson(ConstraintViolationListInterface $errors): array
    {
        $out = [];
        foreach ($errors as $e) {
            $out[] = [
                'field'   => $e->getPropertyPath(),
                'message' => (string) $e->getMessage(),
            ];
        }
        return $out;
    }

    #[Route('', name: 'api_produit_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(ProduitRepository $repo): JsonResponse
    {
        $produits = $repo->findBy([], ['id' => 'DESC']);
        return $this->json(array_map(fn(Produit $p) => $this->produitToArray($p), $produits));
    }

    #[Route('/search', name: 'api_produit_search', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function search(Request $request, ProduitRepository $repo): JsonResponse
    {
        $q           = $request->query->getString('q') ?: null;
        $minPrix     = $request->query->get('minPrix');
        $maxPrix     = $request->query->get('maxPrix');
        $stockStatus = $request->query->getString('stockStatus') ?: null;
        $page        = max(1, $request->query->getInt('page', 1));
        $limit       = min(50, max(1, $request->query->getInt('limit', 10)));
        $categorie   = $request->query->getString('categorie') ?: null;

        $produits = $repo->search(
            $q,
            $categorie,
            ($minPrix !== null && $minPrix !== '') ? (float) $minPrix : null,
            ($maxPrix !== null && $maxPrix !== '') ? (float) $maxPrix : null,
            $stockStatus,
            $page,
            $limit
        );

        return $this->json([
            'page'  => $page,
            'limit' => $limit,
            'items' => array_map(fn(Produit $p) => $this->produitToArray($p), $produits),
        ]);
    }

    #[Route('/{id}', name: 'api_produit_show', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(Produit $produit): JsonResponse
    {
        return $this->json($this->produitToArray($produit));
    }

    #[Route('', name: 'api_produit_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        ProduitRepository $repo
    ): JsonResponse {
        $nom         = $request->request->getString('nom');
        $description = $request->request->getString('description') ?: null;
        $prix        = $request->request->getString('prix') ?: '0';
        $stock       = $request->request->getInt('stock', 0);
        $imageUrl    = $request->request->getString('imageUrl') ?: null;
        $categorie   = $request->request->getString('categorie') ?: null;

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

        if ($file instanceof UploadedFile) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowed, true)) {
                return $this->json(['error' => 'Format non supporté (jpeg/png/webp).'], 422);
            }

            $projectDir = $this->getParameter('kernel.project_dir');
            assert(is_string($projectDir));
            $uploadDir = $projectDir . '/public/uploads/produits';

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