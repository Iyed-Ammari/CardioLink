<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/patient/produits')]
final class PatientProduitController extends AbstractController
{
    #[Route('', name: 'patient_produit_index', methods: ['GET'])]
    public function index(Request $request, ProduitRepository $repo): Response
    {
        $categorie = $request->query->get('categorie');
        $q = $request->query->get('q');

        // On réutilise ton search() existant (sans prix, sans stockStatus)
        $items = $repo->search(
            $q ?: null,
            null,
            null,
            null,
            1,
            50,
            $categorie ?: null
        );

        return $this->render('patient/produit/index.html.twig', [
            'produits' => $items,
            'categorie' => $categorie,
            'q' => $q,
        ]);
    }
}
