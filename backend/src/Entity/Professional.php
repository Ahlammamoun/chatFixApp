<?php

namespace App\Entity;

use App\Repository\ProfessionalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfessionalRepository::class)]
class Professional
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(length: 255)]
    private ?string $speciality = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $zone = null;

    #[ORM\Column]
    private ?float $pricePerHour = null;

    #[ORM\Column]
    private ?bool $availability = null;

       // 🔹 Ajout du champ SIRET
    #[ORM\Column(length: 14, unique: true)]
    private ?string $siret = null;


    #[ORM\ManyToOne(inversedBy: 'professionals')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getSpeciality(): ?string
    {
        return $this->speciality;
    }

    public function setSpeciality(string $speciality): static
    {
        $this->speciality = $speciality;

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

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function setZone(string $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getPricePerHour(): ?float
    {
        return $this->pricePerHour;
    }

    public function setPricePerHour(float $pricePerHour): static
    {
        $this->pricePerHour = $pricePerHour;

        return $this;
    }

    public function isAvailability(): ?bool
    {
        return $this->availability;
    }

    public function setAvailability(bool $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

        // 🔹 Getter / Setter du SIRET
    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(string $siret): static
    {
        if (!preg_match('/^\d{14}$/', $siret)) {
            throw new \InvalidArgumentException('Le numéro SIRET doit contenir exactement 14 chiffres.');
        }

        if (!$this->isValidSiret($siret)) {
            throw new \InvalidArgumentException('Le numéro SIRET fourni est invalide.');
        }

        $this->siret = $siret;
        return $this;
    }

    private function isValidSiret(string $siret): bool
    {
        // Vérifie avec l’algorithme de Luhn
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $digit = (int)$siret[$i];
            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        return $sum % 10 === 0;
    }
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
