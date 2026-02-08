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
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/produits')]
final class AdminProduitController extends AbstractController
{
    #[Route('', name: 'admin_produit_index', methods: ['GET'])]
    public function index(Request $request, ProduitRepository $repo): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $categorie = trim((string) $request->query->get('categorie', ''));

        if ($q !== '' || $categorie !== '') {
            $produits = $repo->search(
                $q !== '' ? $q : null,
                null,
                null,
                null,
                1,
                50,
                $categorie !== '' ? $categorie : null
            );
        } else {
            $produits = $repo->findBy([], ['id' => 'DESC']);
        }

        // ✅ ids des produits déjà utilisés (FK)
        $lockedIds = $repo->findLockedProductIds();

        return $this->render('admin/produit/index.html.twig', [
            'produits' => $produits,
            'q' => $q,
            'categorie' => $categorie,
            'lockedIds' => $lockedIds,
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
            'form' => $form->createView(),
            'mode' => 'create',
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
            'form' => $form->createView(),
            'mode' => 'edit',
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_produit_delete', methods: ['POST'])]
    public function delete(Produit $produit, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_produit_'.$produit->getId(), (string) $request->request->get('_token'))) {
            // on garde cette alerte (sécurité)
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_produit_index');
        }

        try {
            $em->remove($produit);
            $em->flush();
            $this->addFlash('success', 'Produit supprimé.');
        } catch (ForeignKeyConstraintViolationException) {
            // ✅ on ne met plus d'alerte danger (UI désactive déjà)
            // sécurité backend: on ignore juste
        }

        return $this->redirectToRoute('admin_produit_index');
    }

    private function handleImage(Produit $produit, ?UploadedFile $file, SluggerInterface $slugger, ?string $oldImageUrl): void
    {
        // Upload prioritaire
        if ($file instanceof UploadedFile) {
            $newFilename = $this->storeProductImage($file, $slugger);
            $produit->setImageUrl('/uploads/produits/' . $newFilename);
            return;
        }

        // Sinon URL
        $url = trim((string) $produit->getImageUrl());
        if ($url === '') {
            $produit->setImageUrl($oldImageUrl ?: null);
            return;
        }

        $produit->setImageUrl($url);
    }

    private function storeProductImage(UploadedFile $file, SluggerInterface $slugger): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName)->lower();
        $ext = $file->guessExtension() ?: 'jpg';

        $newFilename = $safeName.'-'.bin2hex(random_bytes(6)).'.'.$ext;

        $targetDir = $this->getParameter('kernel.project_dir').'/public/uploads/produits';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $file->move($targetDir, $newFilename);

        return $newFilename;
    }
}
