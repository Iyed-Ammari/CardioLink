<?php

namespace App\Entity;

use App\Repository\DossierMedicalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DossierMedicalRepository::class)]
#[ORM\Table(name: 'dossier_medical')] // ✅ force le nom exact
class DossierMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank(message: "Le groupe sanguin est obligatoire.")]
    #[Assert\Choice(
        choices: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        message: "Le groupe sanguin choisi n'est pas valide."
    )]
    private ?string $groupeSanguin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: "La description des antécédents ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $antecedents = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: "La liste des allergies est trop longue (max {{ limit }} caractères)."
    )]
    private ?string $allergies = null;

    #[ORM\OneToOne(inversedBy: 'dossierMedical', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    public function getId(): ?int { return $this->id; }

    public function getGroupeSanguin(): ?string { return $this->groupeSanguin; }
    public function setGroupeSanguin(string $groupeSanguin): static { $this->groupeSanguin = $groupeSanguin; return $this; }

    public function getAntecedents(): ?string { return $this->antecedents; }
    public function setAntecedents(?string $antecedents): static { $this->antecedents = $antecedents; return $this; }

    public function getAllergies(): ?string { return $this->allergies; }
    public function setAllergies(?string $allergies): static { $this->allergies = $allergies; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    // ✅ sécurité: si antecedents est null, on évite erreur
    public function updateHistory(string $newInfo): self
    {
        $this->antecedents .= "\n Mis à jour le " . date('d/m/Y') . " : " . $newInfo;
        return $this;
    }

    public function getSummary(): string
    {
        return sprintf(
            "Patient: %s | Groupe: %s | Allergies: %s",
            $this->getUser()->getNom(),
            $this->groupeSanguin,
            $this->allergies
        );
    }
}
