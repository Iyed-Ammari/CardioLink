<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;


#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Veuillez choisir une date")]
    #[Assert\GreaterThan("now", message: "Le rendez-vous doit être dans le futur")]
    private ?\DateTime $dateHeure = null;


    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(choices: ['En attente', 'Confirmé', 'Annulé', 'Complété'], message: 'Le statut doit être valide')]
    private ?string $statut = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Choisissez un type de consultation")]
    #[Assert\Choice(['Présentiel', 'Télémédecine'])]
    private ?string $type = null;


    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Le lien de visio doit être une URL valide')]
    private ?string $lienVisio = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Veuillez décrire votre problème")]
    #[Assert\Length(
        min: 10,
        minMessage: "Veuillez donner plus de détails (minimum 10 caractères)",
        max: 500
    )]
    private ?string $remarques = null;


    #[ORM\ManyToOne(inversedBy: 'rendezVouses')]
    #[Assert\NotBlank(message: 'Le patient est obligatoire')]
    private ?User $patient = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVousMedecin')]
    #[Assert\NotNull(message: "Veuillez sélectionner un médecin")]
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
    #[Assert\Callback]
public function validateTypeLieu(ExecutionContextInterface $context): void
{
    

    if ($this->type === 'Télémédecine' && $this->lieu !== null) {
        $context->buildViolation('Un rendez-vous en visio ne doit pas avoir de lieu physique')
            ->atPath('lieu')
            ->addViolation();
    }
}
}