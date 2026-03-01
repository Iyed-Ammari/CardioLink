<?php

namespace App\Entity;

use App\Repository\DossierMedicalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DossierMedicalRepository::class)]
#[ORM\Table(name: 'dossier_medical')]
class DossierMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank(message: "Le groupe sanguin est obligatoire.")]
    #[Assert\Choice(
        choices: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        message: "Le groupe sanguin choisi n'est pas valide."
    )]
    private ?string $groupeSanguin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: "La description des antécédents ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $antecedents = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: "La liste des allergies est trop longue (max {{ limit }} caractères).")]
    private ?string $allergies = null;

    #[ORM\OneToOne(inversedBy: 'dossierMedical', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(nullable: true)]
    private ?float $poids = null;

    #[ORM\Column(nullable: true)]
    private ?float $taille = null;

    #[ORM\Column(nullable: true)]
    private ?int $tensionSystolique = null;

    #[ORM\Column(nullable: true)]
    private ?int $tensionDiastolique = null;

    #[ORM\Column(nullable: true)]
    private ?int $frequenceCardiaque = null;

    public function getId(): ?int { return $this->id; }

    public function getGroupeSanguin(): ?string { return $this->groupeSanguin; }
    public function setGroupeSanguin(string $groupeSanguin): static { $this->groupeSanguin = $groupeSanguin; return $this; }

    public function getAntecedents(): ?string { return $this->antecedents; }
    public function setAntecedents(?string $antecedents): static { $this->antecedents = $antecedents; return $this; }

    public function getAllergies(): ?string { return $this->allergies; }
    public function setAllergies(?string $allergies): static { $this->allergies = $allergies; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getPoids(): ?float { return $this->poids; }
    public function setPoids(?float $poids): static { $this->poids = $poids; return $this; }

    public function getTaille(): ?float { return $this->taille; }
    public function setTaille(?float $taille): static { $this->taille = $taille; return $this; }

    public function getTensionSystolique(): ?int { return $this->tensionSystolique; }
    public function setTensionSystolique(?int $t): static { $this->tensionSystolique = $t; return $this; }

    public function getTensionDiastolique(): ?int { return $this->tensionDiastolique; }
    public function setTensionDiastolique(?int $t): static { $this->tensionDiastolique = $t; return $this; }

    public function getFrequenceCardiaque(): ?int { return $this->frequenceCardiaque; }
    public function setFrequenceCardiaque(?int $f): static { $this->frequenceCardiaque = $f; return $this; }

    public function updateHistory(string $newInfo): self
    {
        $this->antecedents .= "\n Mis à jour le " . date('d/m/Y') . " : " . $newInfo;
        return $this;
    }

    public function getSummary(): string
    {
        return sprintf(
            "Patient: %s | Groupe: %s | Allergies: %s",
            $this->getUser()?->getNom() ?? 'Inconnu',
            $this->groupeSanguin,
            $this->allergies
        );
    }

    public function edit(
        string $groupeSanguin,
        ?string $antecedents,
        ?string $allergies,
        ?float $poids,
        ?float $taille,
        ?int $tensionSystolique,
        ?int $tensionDiastolique,
        ?int $frequenceCardiaque
    ): self {
        $this->groupeSanguin = $groupeSanguin;
        $this->antecedents = $antecedents;
        $this->allergies = $allergies;
        $this->poids = $poids;
        $this->taille = $taille;
        $this->tensionSystolique = $tensionSystolique;
        $this->tensionDiastolique = $tensionDiastolique;
        $this->frequenceCardiaque = $frequenceCardiaque;
        return $this;
    }

    public function getIMC(): ?float
    {
        if ($this->poids && $this->taille && $this->taille > 0) {
            return round($this->poids / (($this->taille / 100) ** 2), 2);
        }
        return null;
    }

    public function getRisqueCardiaque(): string
    {
        $score = 0;
        $imc = $this->getIMC();

        if ($imc !== null && $imc > 30) $score++;
        if ($this->tensionSystolique !== null && $this->tensionSystolique > 140) $score++;
        if ($this->tensionDiastolique !== null && $this->tensionDiastolique > 90) $score++;
        if ($this->frequenceCardiaque !== null && $this->frequenceCardiaque > 100) $score++;

        if ($score >= 3) return 'CRITIQUE';
        if ($score >= 2) return 'ÉLEVÉ';
        if ($score >= 1) return 'MODÉRÉ';
        return 'NORMAL';
    }
}