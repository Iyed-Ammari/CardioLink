<?php

namespace App\Entity;

use App\Repository\SuiviRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SuiviRepository::class)]
class Suivi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type de donnée est obligatoire.')]
    #[Assert\Choice(
        choices: ['Fréquence Cardiaque', 'Tension', 'SpO2', 'Température', 'Glycémie'],
        message: 'Le type de donnée {{ value }} n\'est pas valide.'
    )]
    private ?string $typeDonnee = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La valeur est obligatoire.')]
    #[Assert\Type(type: 'float', message: 'La valeur doit être un nombre.')]
    private ?float $valeur = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unite = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateSaisie = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ['Normal', 'Stable', 'Critique'],
        message: 'Le niveau d\'urgence {{ value }} n\'est pas valide.'
    )]
    private ?string $niveauUrgence = 'Normal';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'suivis')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Un patient doit être sélectionné.')]
    private ?User $patient = null;

    #[ORM\OneToOne(mappedBy: 'suiviOrigine', targetEntity: Intervention::class, cascade: ['remove'])]
    private ?Intervention $intervention = null;

    public function __construct()
    {
        $this->dateSaisie = new \DateTimeImmutable();
        $this->niveauUrgence = 'Normal';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeDonnee(): ?string
    {
        return $this->typeDonnee;
    }

    public function setTypeDonnee(string $typeDonnee): static
    {
        $this->typeDonnee = $typeDonnee;
        return $this;
    }

    public function getValeur(): ?float
    {
        return $this->valeur;
    }

    public function setValeur(float $valeur): static
    {
        $this->valeur = $valeur;
        $this->updateNiveauUrgence();
        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): static
    {
        $this->unite = $unite;
        return $this;
    }

    public function getDateSaisie(): ?\DateTimeImmutable
    {
        return $this->dateSaisie;
    }

    public function setDateSaisie(\DateTimeImmutable $dateSaisie): static
    {
        $this->dateSaisie = $dateSaisie;
        return $this;
    }

    public function getNiveauUrgence(): ?string
    {
        return $this->niveauUrgence;
    }

    public function setNiveauUrgence(string $niveauUrgence): static
    {
        $this->niveauUrgence = $niveauUrgence;
        return $this;
    }

    public function getPatient(): ?User
    {
        return $this->patient;
    }

    public function setPatient(?User $patient): static
    {
        $this->patient = $patient;
        return $this;
    }

    public function getIntervention(): ?Intervention
    {
        return $this->intervention;
    }

    public function setIntervention(?Intervention $intervention): static
    {
        if ($intervention === null && $this->intervention !== null) {
            $this->intervention->setSuiviOrigine(null);
        }
        if ($intervention !== null && $intervention->getSuiviOrigine() !== $this) {
            $intervention->setSuiviOrigine($this);
        }
        $this->intervention = $intervention;
        return $this;
    }

    /**
     * Vérifie si les valeurs mesurées dépassent les seuils critiques
     * selon les recommandations médicales.
     *
     * @return bool true si la valeur est critique
     */
    public function isCritical(): bool
    {
        return match ($this->typeDonnee) {
            // Fréquence Cardiaque: critique si > 120 bpm au repos ou < 40 bpm
            'Fréquence Cardiaque' => $this->valeur > 120 || $this->valeur < 40,
            
            // Tension: critique si systolique > 180 mmHg ou diastolique > 110 mmHg
            // Format: XXX/XX (ex: 140/90)
            'Tension' => true, // Traité dans updateNiveauUrgence
            
            // SpO2: critique si < 90%
            'SpO2' => $this->valeur < 90,
            
            // Température: critique si > 39°C ou < 35°C
            'Température' => $this->valeur > 39 || $this->valeur < 35,
            
            // Glycémie: critique si > 250 mg/dL ou < 70 mg/dL
            'Glycémie' => $this->valeur > 250 || $this->valeur < 70,
            
            default => false,
        };
    }

    /**
     * Met à jour automatiquement le niveau d'urgence selon la valeur mesurée.
     */
    private function updateNiveauUrgence(): void
    {
        if ($this->isCritical()) {
            $this->niveauUrgence = 'Critique';
        } elseif ($this->isStable()) {
            $this->niveauUrgence = 'Stable';
        } else {
            $this->niveauUrgence = 'Normal';
        }
    }

    /**
     * Vérifie si la valeur est dans une plage stable (avant critique).
     */
    private function isStable(): bool
    {
        return match ($this->typeDonnee) {
            // FC: stable entre 100-120 bpm
            'Fréquence Cardiaque' => $this->valeur >= 100 && $this->valeur <= 120,
            
            // Tension: stable si systolique 140-180 ou diastolique 90-110
            'Tension' => true,
            
            // SpO2: stable entre 90-95%
            'SpO2' => $this->valeur >= 90 && $this->valeur < 95,
            
            // Température: stable entre 37.5-39°C
            'Température' => $this->valeur > 37.5 && $this->valeur <= 39,
            
            // Glycémie: stable entre 200-250 mg/dL ou 70-100 mg/dL
            'Glycémie' => ($this->valeur >= 200 && $this->valeur <= 250) || ($this->valeur >= 70 && $this->valeur <= 100),
            
            default => false,
        };
    }

    /**
     * Retourne la valeur formatée avec son unité.
     * Ex: "120 bpm", "140/90 mmHg"
     *
     * @return string
     */
    public function getFormattedValue(): string
    {
        return $this->valeur . ' ' . $this->unite;
    }
}
