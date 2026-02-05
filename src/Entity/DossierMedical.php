<?php

namespace App\Entity;

use App\Repository\DossierMedicalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DossierMedicalRepository::class)]
class DossierMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    private ?string $groupeSanguin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $antecedents = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $allergies = null;

    #[ORM\OneToOne(inversedBy: 'dossierMedical', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroupeSanguin(): ?string
    {
        return $this->groupeSanguin;
    }

    public function setGroupeSanguin(string $groupeSanguin): static
    {
        $this->groupeSanguin = $groupeSanguin;

        return $this;
    }

    public function getAntecedents(): ?string
    {
        return $this->antecedents;
    }

    public function setAntecedents(string $antecedents): static
    {
        $this->antecedents = $antecedents;

        return $this;
    }

    public function getAllergies(): ?string
    {
        return $this->allergies;
    }

    public function setAllergies(string $allergies): static
    {
        $this->allergies = $allergies;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
    public function updateHistory(string $newInfo): self
{
    $this->antecedents .= "\n Mis à jour le " . date('d/m/Y') . " : " . $newInfo;
    return $this;
}

public function getSummary(): string
{
    return sprintf("Patient: %s | Groupe: %s | Allergies: %s", 
        $this->getUser()->getNom(), 
        $this->groupeSanguin, 
        $this->allergies
    );
}
}
