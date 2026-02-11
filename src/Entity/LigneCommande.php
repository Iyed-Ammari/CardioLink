<?php

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
#[ORM\Table(name: 'ligne_commande')]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $quantite = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $prixUnitaire = '0.00';

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produit = null;

    public function getId(): ?int { return $this->id; }

    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $quantite): static
    {
        if ($quantite < 1) {
            throw new \InvalidArgumentException("Quantité doit être >= 1");
        }
        $this->quantite = $quantite;

        if ($this->commande) {
            $this->commande->recalculateTotal();
        }

        return $this;
    }

    public function getPrixUnitaire(): string { return $this->prixUnitaire; }
    public function setPrixUnitaire(string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        if ($this->commande) {
            $this->commande->recalculateTotal();
        }

        return $this;
    }

    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
        return $this;
    }

    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;
        return $this;
    }

    public function getTotalLigne(): string
    {
        $total = ((float)$this->prixUnitaire) * $this->quantite;
        return number_format($total, 2, '.', '');
    }
}
