<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
class Commande
{
    public const STATUT_PANIER = 'PANIER';
    public const STATUT_EN_ATTENTE_PAIEMENT = 'EN_ATTENTE_PAIEMENT';
    public const STATUT_PAYEE = 'PAYEE';
    public const STATUT_LIVREE = 'LIVREE';
    public const STATUT_ANNULEE = 'ANNULEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ✅ Patient propriétaire
    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCommande = null;

    #[ORM\Column(length: 50)]
    private string $statut = self::STATUT_PANIER;

    // ✅ DECIMAL en string (Doctrine) — on le calcule depuis les lignes
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $montantTotal = '0.00';

    /**
     * @var Collection<int, LigneCommande>
     */
    #[ORM\OneToMany(targetEntity: LigneCommande::class, mappedBy: 'commande', cascade: ['persist'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->dateCommande = new \DateTimeImmutable();
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getDateCommande(): ?\DateTimeImmutable { return $this->dateCommande; }
    public function setDateCommande(\DateTimeImmutable $dateCommande): static { $this->dateCommande = $dateCommande; return $this; }

    public function getStatut(): string { return $this->statut; }

    public function setStatut(string $statut): static
    {
        $allowed = [
            self::STATUT_PANIER,
            self::STATUT_EN_ATTENTE_PAIEMENT,
            self::STATUT_PAYEE,
            self::STATUT_LIVREE,
            self::STATUT_ANNULEE,
        ];

        if (!in_array($statut, $allowed, true)) {
            throw new \InvalidArgumentException("Statut commande invalide: ".$statut);
        }

        $this->statut = $statut;
        return $this;
    }

    public function getMontantTotal(): string { return $this->montantTotal; }

    // 🔒 On évite setMontantTotal() manuel : on calcule toujours
    private function setMontantTotal(string $montantTotal): void
    {
        $this->montantTotal = $montantTotal;
    }

    /** @return Collection<int, LigneCommande> */
    public function getLignes(): Collection { return $this->lignes; }

    public function addLigne(LigneCommande $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCommande($this);
        }
        $this->recalculateTotal();
        return $this;
    }

    public function removeLigne(LigneCommande $ligne): static
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getCommande() === $this) {
                $ligne->setCommande(null);
            }
        }
        $this->recalculateTotal();
        return $this;
    }

    // ✅ total = somme(qte * prixUnitaire)
    public function recalculateTotal(): void
    {
        $total = 0.0;
        foreach ($this->lignes as $l) {
            $total += ((float)$l->getPrixUnitaire()) * $l->getQuantite();
        }
        $this->setMontantTotal(number_format($total, 2, '.', ''));
    }

    // ✅ règles métier
    public function canEditPanier(): bool
    {
        return $this->statut === self::STATUT_PANIER;
    }

    public function validerCommande(): void
    {
        if ($this->statut !== self::STATUT_PANIER) {
            throw new \LogicException("Seul un panier peut être validé.");
        }
        if ($this->lignes->count() === 0) {
            throw new \LogicException("Panier vide.");
        }
        $this->setStatut(self::STATUT_EN_ATTENTE_PAIEMENT);
        $this->recalculateTotal();
    }

    public function annuler(): void
    {
        if (in_array($this->statut, [self::STATUT_PAYEE, self::STATUT_LIVREE], true)) {
            throw new \LogicException("Impossible d'annuler une commande payée/livrée.");
        }
        $this->setStatut(self::STATUT_ANNULEE);
    }

    public function marquerPayee(): void
    {
        if ($this->statut !== self::STATUT_EN_ATTENTE_PAIEMENT) {
            throw new \LogicException("Commande non valide pour paiement.");
        }
        $this->setStatut(self::STATUT_PAYEE);
    }

    public function marquerLivree(): void
    {
        if ($this->statut !== self::STATUT_PAYEE) {
            throw new \LogicException("Une commande doit être PAYEE avant LIVREE.");
        }
        $this->setStatut(self::STATUT_LIVREE);
    }
}
