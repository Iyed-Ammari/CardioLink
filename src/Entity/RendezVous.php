<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
#[ORM\Table(name: "rendezvous")] // nom de table en singulier
#[ORM\HasLifecycleCallbacks]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: "Veuillez choisir une date")]
    #[Assert\GreaterThan("now", message: "Le rendez-vous doit être dans le futur")]
    private ?\DateTimeImmutable $dateHeure = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(
        choices: ['En attente', 'Confirmé', 'Annulé', 'Complété'],
        message: 'Le statut doit être valide'
    )]
    private string $statut;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: "Choisissez un type de consultation")]
    #[Assert\Choice(
        choices: ['Présentiel', 'Téléconsultation'],
        message: "Type invalide"
    )]
    private string $type;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Url(message: 'Le lien de visio doit être une URL valide')]
    private ?string $lienVisio = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Veuillez décrire votre problème")]
    #[Assert\Length(
        min: 10,
        minMessage: "Veuillez donner plus de détails (minimum 10 caractères)",
        max: 500
    )]
    private string $remarques;

    #[ORM\ManyToOne(inversedBy: 'rendezVouses')]
    #[Assert\NotNull(message: 'Le patient est obligatoire')]
    private ?User $patient = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVousMedecin')]
    #[Assert\NotNull(message: "Veuillez sélectionner un médecin")]
    private ?User $medecin = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVouses')]
    private ?Lieu $lieu = null;

    #[ORM\OneToOne(mappedBy: 'consultation', cascade: ['persist', 'remove'])]
    private ?Ordonnance $ordonnance = null;

    // =========================
    // GETTERS & SETTERS
    // =========================

    public function getId(): ?int
    {
        return $this->id;
    }

public function getDateHeure(): \DateTimeImmutable
{
    return $this->dateHeure;
}

public function setDateHeure(\DateTimeImmutable $dateHeure): self
{
    $this->dateHeure = $dateHeure;
    return $this;
}
    

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getLienVisio(): ?string
    {
        return $this->lienVisio;
    }

    public function setLienVisio(?string $lienVisio): self
    {
        $this->lienVisio = $lienVisio;
        return $this;
    }

    public function getRemarques(): string
    {
        return $this->remarques;
    }

    public function setRemarques(string $remarques): self
    {
        $this->remarques = $remarques;
        return $this;
    }

    public function getPatient(): ?User
    {
        return $this->patient;
    }

    public function setPatient(?User $patient): self
    {
        $this->patient = $patient;
        return $this;
    }

    public function getMedecin(): ?User
    {
        return $this->medecin;
    }

    public function setMedecin(?User $medecin): self
    {
        $this->medecin = $medecin;
        return $this;
    }

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getOrdonnance(): ?Ordonnance
    {
        return $this->ordonnance;
    }

    public function setOrdonnance(?Ordonnance $ordonnance): self
    {
        if ($ordonnance === null && $this->ordonnance !== null) {
            $this->ordonnance->setConsultation(null);
        }

        if ($ordonnance !== null && $ordonnance->getConsultation() !== $this) {
            $ordonnance->setConsultation($this);
        }

        $this->ordonnance = $ordonnance;

        return $this;
    }

    // =========================
    // VALIDATION PERSONNALISÉE
    // =========================

    #[Assert\Callback]
    public function validateTypeLieu(ExecutionContextInterface $context): void
    {
        if ($this->type === 'Téléconsultation' && $this->lieu !== null) {
            $context->buildViolation('Un rendez-vous en visio ne doit pas avoir de lieu physique')
                ->atPath('lieu')
                ->addViolation();
        }
    }

    // =========================
    // LIFECYCLE CALLBACK
    // =========================

  #[ORM\PrePersist]
public function initializeDateHeure(): void
{
    if (!isset($this->dateHeure)) {
        $this->dateHeure = new \DateTimeImmutable();
    }
}
}