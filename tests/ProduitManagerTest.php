<?php

namespace App\Tests;

use App\Entity\Produit;
use App\Service\ProduitManager;
use PHPUnit\Framework\TestCase;

class ProduitManagerTest extends TestCase
{
    // Test 1 : Produit valide
    public function testProduitValide(): void
    {
        $produit = new Produit();
        $produit->setNom('Tensiomètre');
        $produit->setPrix('150');
        $produit->setStock(10);

        $manager = new ProduitManager();
        $this->assertTrue($manager->validate($produit));
    }

    // Test 2 : Nom obligatoire
    public function testProduitSansNom(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom du produit est obligatoire.');

        $produit = new Produit();
        $produit->setNom('');
        $produit->setPrix('150');
        $produit->setStock(5);

        $manager = new ProduitManager();
        $manager->validate($produit);
    }

    // Test 3 : Prix invalide
    public function testProduitAvecPrixNul(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix doit être strictement supérieur à 0.');

        $produit = new Produit();
        $produit->setNom('Stéthoscope');
        $produit->setPrix('0');
        $produit->setStock(5);

        $manager = new ProduitManager();
        $manager->validate($produit);
    }

    // Test 4 : Stock négatif
    public function testProduitAvecStockNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le stock ne peut pas être négatif.');

        $produit = new Produit();
        $produit->setNom('Oxymètre');
        $produit->setPrix('200');
        $produit->setStock(-1);

        $manager = new ProduitManager();
        $manager->validate($produit);
    }
}