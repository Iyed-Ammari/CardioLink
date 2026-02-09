<?php

namespace App\Entity;

use App\Repository\InterventionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
class Intervention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type d\'intervention est obligatoire.')]
    #[Assert\Choice(
        choices: ['Alerte SOS', 'Consultation', 'Suivi Rapproché', 'Hospitalisation'],
        message: 'Le type {{ value }} n\'est pas valide.'
    )]
    private ?string $type = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 10, minMessage: 'La description doit contenir au moins 10 caractères.')]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ['En attente', 'Acceptée', 'En cours', 'Effectuée', 'Annulée'],
        message: 'Le statut {{ value }} n\'est pas valide.'
    )]
    private ?string $statut = 'En attente';

    #[ORM\Column]
    private ?\DateTimeImmutable $datePlanifiee = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateCompletion = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'interventions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $medecin = null;

    #[ORM\OneToOne(inversedBy: 'intervention', targetEntity: Suivi::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Suivi $suiviOrigine = null;

    public function __construct()
    {
        $this->datePlanifiee = new \DateTimeImmutable();
        $this->statut = 'En attente';
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
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

    public function getDatePlanifiee(): ?\DateTimeImmutable
    {
        return $this->datePlanifiee;
    }

    public function setDatePlanifiee(\DateTimeImmutable $datePlanifiee): static
    {
        $this->datePlanifiee = $datePlanifiee;
        return $this;
    }

    public function getDateCompletion(): ?\DateTimeImmutable
    {
        return $this->dateCompletion;
    }

    public function setDateCompletion(?\DateTimeImmutable $dateCompletion): static
    {
        $this->dateCompletion = $dateCompletion;
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

    public function getSuiviOrigine(): ?Suivi
    {
        return $this->suiviOrigine;
    }

    public function setSuiviOrigine(?Suivi $suiviOrigine): static
    {
        $this->suiviOrigine = $suiviOrigine;
        return $this;
    }

    /**
     * Marque l'intervention comme complétée et enregistre l'heure de fin.
     */
    public function markAsCompleted(): static
    {
        $this->statut = 'Effectuée';
        $this->dateCompletion = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Vérifie si cette intervention est une alerte d'urgence SOS.
     *
     * @return bool
     */
    public function isUrgent(): bool
    {
        return $this->type === 'Alerte SOS';
    }
}
