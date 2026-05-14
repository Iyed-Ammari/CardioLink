<?php
// src/Controller/AdminProduitController.php

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/produits')]
#[IsGranted('ROLE_ADMIN')] // ✅ CORRECTION : protection manquante ajoutée
final class AdminProduitController extends AbstractController
{
    #[Route('', name: 'admin_produit_index')]
    public function index(Request $request, ProduitRepository $repo): Response
    {
        $q         = trim((string) $request->query->get('q', ''));
        $categorie = trim((string) $request->query->get('categorie', ''));

        $categories = $repo->findExistingCategories();

        if ($categorie !== '' && !in_array($categorie, $categories, true)) {
            $categorie = '';
        }

        $produits = $repo->search(
            $q !== '' ? $q : null,
            $categorie !== '' ? $categorie : null
        );

        return $this->render('admin/produit/index.html.twig', [
            'produits'   => $produits,
            'q'          => $q,
            'categorie'  => $categorie,
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'admin_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImage($produit, $form->get('imageFile')->getData(), $slugger, null);
            $em->persist($produit);
            $em->flush();

            $this->addFlash('success', 'Produit ajouté avec succès.');
            return $this->redirectToRoute('admin_produit_index');
        }

        return $this->render('admin/produit/form.html.twig', [
            'form'    => $form->createView(),
            'mode'    => 'create',
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Produit $produit, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $oldImageUrl = $produit->getImageUrl();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImage($produit, $form->get('imageFile')->getData(), $slugger, $oldImageUrl);
            $em->flush();

            $this->addFlash('success', 'Produit modifié avec succès.');
            return $this->redirectToRoute('admin_produit_index');
        }

        return $this->render('admin/produit/form.html.twig', [
            'form'    => $form->createView(),
            'mode'    => 'edit',
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_produit_delete', methods: ['POST'])]
    public function delete(Produit $produit, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_produit_'.$produit->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_produit_index');
        }

        try {
            $em->remove($produit);
            $em->flush();
            $this->addFlash('success', 'Produit supprimé.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('danger', 'Suppression impossible : produit déjà utilisé dans une commande.');
        }

        return $this->redirectToRoute('admin_produit_index');
    }

    private function handleImage(Produit $produit, ?UploadedFile $file, SluggerInterface $slugger, ?string $oldImageUrl): void
    {
        if ($file instanceof UploadedFile) {
            $imageUrl = $this->storeProductImage($file);
            $produit->setImageUrl($imageUrl);
            return;
        }

        $url = trim((string) $produit->getImageUrl());
        if ($url === '') {
            $produit->setImageUrl($oldImageUrl ?: null);
            return;
        }

        $produit->setImageUrl($url);
    }

    private function storeProductImage(UploadedFile $file): string
    {
        $cloudinary = new \Cloudinary\Cloudinary([
            'cloud' => [
                'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
                'api_key'    => $_ENV['CLOUDINARY_API_KEY'],
                'api_secret' => $_ENV['CLOUDINARY_API_SECRET'],
            ]
        ]);

        $result = $cloudinary->uploadApi()->upload(
            $file->getPathname(),
            ['folder' => 'cardiolink/produits']
        );

        return $result['secure_url'];
    }
}