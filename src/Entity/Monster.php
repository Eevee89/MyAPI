<?php

namespace App\Entity;

use App\Repository\MonsterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonsterRepository::class)]
class Monster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'json')]
    private array $games = [];

    #[ORM\Column(type: 'json')]
    private array $ranks = [];

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

    public function getGames(): array
    {
        return $this->games;
    }

    public function setGames(array $games): static
    {
        $this->games = $games;

        return $this;
    }

    public function getRanks(): array
    {
        return $this->ranks;
    }

    public function setRanks(array $ranks): static
    {
        $this->ranks = $ranks;

        return $this;
    }
}