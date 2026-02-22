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
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $datePeak = null;

    #[ORM\Column]
    private ?float $predictionValue = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatePeak(): ?\DateTime
    {
        return $this->datePeak;
    }

    public function setDatePeak(\DateTime $datePeak): static
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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
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
