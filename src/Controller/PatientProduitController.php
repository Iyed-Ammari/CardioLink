<?php
// src/Controller/PatientProduitController.php

namespace App\Controller;

use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient/produits')]
#[IsGranted('ROLE_USER')]
final class PatientProduitController extends AbstractController
{
    #[Route('', name: 'patient_produit_index', methods: ['GET'])]
    public function index(Request $request, ProduitRepository $repo): Response
    {
        $categorie = trim((string) $request->query->get('categorie', ''));
        $q         = trim((string) $request->query->get('q', ''));

        $categories = $repo->findExistingCategories();

        if ($categorie !== '' && !in_array($categorie, $categories, true)) {
            $categorie = '';
        }

        $items = $repo->search(
            $q !== '' ? $q : null,
            $categorie !== '' ? $categorie : null
        );

        return $this->render('patient/produit/index.html.twig', [
            'produits'            => $items,
            'q'                   => $q,
            'categorie'           => $categorie,
            'categories'          => $categories,
            'showCategorieFilter' => count($categories) > 0,
        ]);
    }
}