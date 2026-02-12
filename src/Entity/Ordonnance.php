<?php

namespace App\Entity;

use App\Repository\OrdonnanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrdonnanceRepository::class)]
class Ordonnance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $diagnostic = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 255)]
    private ?string $patientNom = null;

    #[ORM\Column(length: 255)]
    private ?string $medecinNom = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = $reference; return $this; }

    public function getDateCreation(): ?\DateTime { return $this->dateCreation; }
    public function setDateCreation(\DateTime $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }

    public function getDiagnostic(): ?string { return $this->diagnostic; }
    public function setDiagnostic(?string $diagnostic): static { $this->diagnostic = $diagnostic; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getPatientNom(): ?string { return $this->patientNom; }
    public function setPatientNom(string $patientNom): static { $this->patientNom = $patientNom; return $this; }

    public function getMedecinNom(): ?string { return $this->medecinNom; }
    public function setMedecinNom(string $medecinNom): static { $this->medecinNom = $medecinNom; return $this; }
    // ...existing code...

    #[ORM\OneToOne(inversedBy: 'ordonnance')]
    private ?RendezVous $consultation = null;

    public function getConsultation(): ?RendezVous
    {
        return $this->consultation;
    }

    public function setConsultation(?RendezVous $consultation): static
    {
        $this->consultation = $consultation;
        return $this;
    }
}