<?php

namespace App\Entity;

use App\Repository\RatingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RatingRepository::class)]
#[ORM\Table(name: 'rating')]
#[ORM\UniqueConstraint(name: 'uniq_offer_user_rating', columns: ['offer_id', 'user_id'])]
class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Professional $professional = null;

    #[ORM\ManyToOne(inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Offer $offer = null;

    #[ORM\Column]
    private ?float $value = null;

    public function getId(): ?int { return $this->id; }

    public function getProfessional(): ?Professional { return $this->professional; }
    public function setProfessional(?Professional $p): static { $this->professional = $p; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $u): static { $this->user = $u; return $this; }

    public function getOffer(): ?Offer { return $this->offer; }
    public function setOffer(?Offer $o): static { $this->offer = $o; return $this; }

    public function getValue(): ?float { return $this->value; }
    public function setValue(?float $v): static { $this->value = $v; return $this; }
}
