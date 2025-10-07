<?php

namespace App\Entity;

use App\Repository\MonsterGameRankRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonsterGameRankRepository::class)]
class MonsterGameRank
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\ManyToOne(targetEntity: Monster::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Monster $monster;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: Rank::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Rank $rank;

    public function getMonster(): Monster
    {
        return $this->monster;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getRank(): Rank
    {
        return $this->rank;
    }
}