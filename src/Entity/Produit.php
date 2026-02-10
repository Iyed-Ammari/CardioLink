<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
#[UniqueEntity(fields: ['nom'], message: 'Ce produit existe déjà (nom unique).')]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Nom trop court (min 2).')]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix est obligatoire.')]
    #[Assert\Positive(message: 'Le prix doit être strictement > 0.')]
    private ?string $prix = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le stock est obligatoire.')]
    #[Assert\GreaterThanOrEqual(0, message: 'Le stock doit être >= 0.')]
    private ?int $stock = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex(
        pattern: "/^(https?:\/\/.+|\/uploads\/produits\/.+)$/",
        message: "imageUrl doit être une URL http(s) OU un chemin /uploads/produits/..."
    )]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $categorie = null;

    /**
     * @var Collection<int, LigneCommande>
     */
    #[ORM\OneToMany(targetEntity: LigneCommande::class, mappedBy: 'produit')]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = trim($nom); return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getPrix(): ?string { return $this->prix; }
    public function setPrix(string $prix): static { $this->prix = $prix; return $this; }

    public function getStock(): ?int { return $this->stock; }
    public function setStock(int $stock): static { $this->stock = $stock; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie ? strtoupper(trim($categorie)) : null;
        return $this;
    }

    /** Statut rupture/disponible calculé */
    public function getStockStatus(): string
    {
        return ($this->stock ?? 0) === 0 ? 'RUPTURE' : 'DISPONIBLE';
    }

    /** @return Collection<int, LigneCommande> */
    public function getLignes(): Collection { return $this->lignes; }

    public function addLigne(LigneCommande $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setProduit($this);
        }
        return $this;
    }

    public function removeLigne(LigneCommande $ligne): static
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getProduit() === $this) {
                $ligne->setProduit(null);
            }
        }
        return $this;
    }
}
