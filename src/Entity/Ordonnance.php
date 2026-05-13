<?php

namespace App\Entity;

use App\Repository\OrdonnanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\RendezVous;

#[ORM\Entity(repositoryClass: OrdonnanceRepository::class)]
class Ordonnance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $reference;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $diagnostic = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 255)]
    private string $patientNom;

    #[ORM\Column(length: 255)]
    private string $medecinNom;

    #[ORM\OneToOne(inversedBy: 'ordonnance', targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?RendezVous $consultation = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->reference = 'ORDO-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getDateCreation(): \DateTimeImmutable { return $this->dateCreation; }

    public function getDiagnostic(): ?string { return $this->diagnostic; }
    public function setDiagnostic(?string $diagnostic): self { $this->diagnostic = $diagnostic; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }

    public function getPatientNom(): string { return $this->patientNom; }
    public function setPatientNom(string $patientNom): self { $this->patientNom = $patientNom; return $this; }

    public function getMedecinNom(): string { return $this->medecinNom; }
    public function setMedecinNom(string $medecinNom): self { $this->medecinNom = $medecinNom; return $this; }

    public function getConsultation(): ?RendezVous { return $this->consultation; }
    public function setConsultation(?RendezVous $consultation): self { $this->consultation = $consultation; return $this; }
}