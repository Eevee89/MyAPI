<?php

namespace App\Entity;

use App\Repository\QuoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuoteRepository::class)]
class Quote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $category = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $quote = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $oeuvre = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $characters = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $episode = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, name: 'releaseDate')]
    private ?int $releaseDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $answer = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategory(): ?int
    {
        return $this->category;
    }

    public function setCategory(int $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getQuote(): ?string
    {
        return $this->quote;
    }

    public function setQuote(string $quote): static
    {
        $this->quote = $quote;

        return $this;
    }

    public function getOeuvre(): ?string
    {
        return $this->oeuvre;
    }

    public function setOeuvre(string $oeuvre): static
    {
        $this->oeuvre = $oeuvre;

        return $this;
    }

    public function isWhoSaid(): ?bool
    {
        return $this->type;
    }

    public function setType(bool $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCharacters(): ?string
    {
        return $this->characters;
    }

    public function setCharacters(?string $characters): static
    {
        $this->characters = $characters;

        return $this;
    }

    public function getEpisode(): ?string
    {
        return $this->episode;
    }

    public function setEpisode(?string $episode): static
    {
        $this->episode = $episode;

        return $this;
    }

    public function getRelease(): ?int
    {
        return $this->releaseDate;
    }

    public function setRelease(?int $release): static
    {
        $this->releaseDate = $release;

        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): static
    {
        $this->answer = $answer;

        return $this;
    }
}