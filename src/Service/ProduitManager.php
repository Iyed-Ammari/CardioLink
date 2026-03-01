<?php

namespace App\Service;

use App\Entity\Produit;

class ProduitManager
{
    public function validate(Produit $produit): bool
    {
        if (empty($produit->getNom())) {
            throw new \InvalidArgumentException('Le nom du produit est obligatoire.');
        }

        if ((float) $produit->getPrix() <= 0) {
            throw new \InvalidArgumentException('Le prix doit être strictement supérieur à 0.');
        }

        if (($produit->getStock() ?? 0) < 0) {
            throw new \InvalidArgumentException('Le stock ne peut pas être négatif.');
        }

        return true;
    }
}