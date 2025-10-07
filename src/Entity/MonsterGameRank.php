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
    
    #[ORM\Column(nullable: false)]
    private Monster $monster;

    #[ORM\Column(nullable: false)]
    private Game $game;

    #[ORM\Column(nullable: false)]
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