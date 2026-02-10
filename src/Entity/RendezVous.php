<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $dateHeure = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lienVisio = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $remarques = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVouses')]
    private ?User $patient = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVousMedecin')]
    private ?User $medecin = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVouses')]
    private ?Lieu $lieu = null;

    #[ORM\OneToOne(mappedBy: 'consultation', cascade: ['persist', 'remove'])]
    private ?Ordonnance $ordonnance = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateHeure(): ?\DateTime
    {
        return $this->dateHeure;
    }

    public function setDateHeure(\DateTime $dateHeure): static
    {
        $this->dateHeure = $dateHeure;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLienVisio(): ?string
    {
        return $this->lienVisio;
    }

    public function setLienVisio(?string $lienVisio): static
    {
        $this->lienVisio = $lienVisio;

        return $this;
    }

    public function getRemarques(): ?string
    {
        return $this->remarques;
    }

    public function setRemarques(string $remarques): static
    {
        $this->remarques = $remarques;

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

    public function getMedecin(): ?User
    {
        return $this->medecin;
    }

    public function setMedecin(?User $medecin): static
    {
        $this->medecin = $medecin;

        return $this;
    }

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getOrdonnance(): ?Ordonnance
    {
        return $this->ordonnance;
    }

    public function setOrdonnance(?Ordonnance $ordonnance): static
    {
        // unset the owning side of the relation if necessary
        if ($ordonnance === null && $this->ordonnance !== null) {
            $this->ordonnance->setConsultation(null);
        }

        // set the owning side of the relation if necessary
        if ($ordonnance !== null && $ordonnance->getConsultation() !== $this) {
            $ordonnance->setConsultation($this);
        }

        $this->ordonnance = $ordonnance;

        return $this;
    }

    // ============= MÉTHODES UTILITAIRES =============

    /**
     * Vérifie si la consultation est passée (plus d'1h après l'heure prévue)
     */
    public function isPassedConsultation(): bool
    {
        if (!$this->dateHeure) {
            return false;
        }

        $now = new \DateTime();
        $consultationEndTime = (clone $this->dateHeure)->add(new \DateInterval('PT1H'));

        return $now > $consultationEndTime;
    }

    /**
     * Vérifie si le RDV est finalisé (accepté ou refusé)
     */
    public function isFinalized(): bool
    {
        return in_array($this->statut, ['Accepté', 'Refusé']);
    }

    /**
     * Vérifie si le patient peut modifier ce RDV
     */
    public function canPatientEdit(): bool
    {
        // Le patient ne peut pas modifier si le RDV est finalisé ou passé
        return !$this->isFinalized() && !$this->isPassedConsultation();
    }

    /**
     * Vérifie si le patient peut supprimer ce RDV
     */
    public function canPatientDelete(): bool
    {
        // Même conditions que pour l'édition
        return $this->canPatientEdit();
    }

    /**
     * Vérifie si le médecin peut modifier ce RDV
     */
    public function canDoctorEdit(): bool
    {
        // Le médecin ne peut modifier que si le RDV n'est pas encore passé (ou en cours de la consultation)
        return !$this->isPassedConsultation();
    }

    /**
     * Vérifie si le médecin peut supprimer ce RDV
     */
    public function canDoctorDelete(): bool
    {
        // Le médecin ne peut supprimer que si le RDV n'est pas encore passé
        return !$this->isPassedConsultation();
    }

    /**
     * Met à jour automatiquement le statut si la consultation est passée
     */
    public function updateStatusIfPassed(): void
    {
        if ($this->isPassedConsultation() && $this->statut === 'En attente') {
            $this->statut = 'Complété';
        }
    }
}
