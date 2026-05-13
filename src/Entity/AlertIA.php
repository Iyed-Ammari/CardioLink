<?php

namespace App\Entity;

use App\Repository\AlertIARepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertIARepository::class)]
class AlertIA
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    // ✅ FIX 1: Type Mismatch - changé Column type en datetime pour correspondre au type hint
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeImmutable $datePeak = null;

    #[ORM\Column]
    private ?float $predictionValue = null;

    // ✅ FIX 2: Type Mismatch - changé Column type en datetime pour correspondre au type hint
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatePeak(): ?\DateTimeImmutable
    {
        return $this->datePeak;
    }

    public function setDatePeak(\DateTimeImmutable $datePeak): static
    {
        $this->datePeak = $datePeak;
        return $this;
    }

    public function getPredictionValue(): ?float
    {
        return $this->predictionValue;
    }

    public function setPredictionValue(float $predictionValue): static
    {
        $this->predictionValue = $predictionValue;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }
}