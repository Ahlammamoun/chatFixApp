<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Professional $professional = null;

    #[ORM\Column(type: 'float')]
    private float $price;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending'; // pending | accepted | refused

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // getters / setters …

    public function getId(): ?int
{
    return $this->id;
}

public function getClient(): ?User
{
    return $this->client;
}

public function setClient(User $client): self
{
    $this->client = $client;
    return $this;
}

public function getProfessional(): ?Professional
{
    return $this->professional;
}

public function setProfessional(Professional $professional): self
{
    $this->professional = $professional;
    return $this;
}

public function getPrice(): float
{
    return $this->price;
}

public function setPrice(float $price): self
{
    $this->price = $price;
    return $this;
}

public function getStatus(): string
{
    return $this->status;
}

public function setStatus(string $status): self
{
    $this->status = $status;
    return $this;
}

public function getMessage(): ?string
{
    return $this->message;
}

public function setMessage(?string $message): self
{
    $this->message = $message;
    return $this;
}

public function getCreatedAt(): \DateTimeImmutable
{
    return $this->createdAt;
}

}
