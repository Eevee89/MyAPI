<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $abreviation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAbrev(): ?string
    {
        return $this->abreviation;
    }

    public function setAbrev(string $abreviation): static
    {
        $this->abreviation = $abreviation;

        return $this;
    }

    public function toArray(): array
    {
        return [
            "abrev" => $this->abreviation,
            "name" => $this->name
        ];
    }
}