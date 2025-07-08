<?php

namespace App\Entity;

use App\Repository\PodiumRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PodiumRepository::class)]
class Podium
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 255)]
    private ?string $first = null;

    #[ORM\Column(length: 255)]
    private ?string $second = null;

    #[ORM\Column(length: 255)]
    private ?string $third = null;

    public function getLabel(): ?string 
    {
        return $this->label;
    }

    public function getFirst(): ?string 
    {
        return $this->first;
    }

    public function getSecond(): ?string 
    {
        return $this->second;
    }

    public function getThird(): ?string 
    {
        return $this->third;
    }

    public function toArray(): array 
    {
        return [
            "id" => $this->id,
            "label" => $this->label,
            "first" => $this->first,
            "second" => $this->second,
            "third" => $this->third
        ];
    }
}