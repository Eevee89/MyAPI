<?php

namespace App\Manager;

use App\Entity\Monster;
use App\Entity\Game;
use App\Entity\Rank;
use App\Entity\MonsterGameRank;
use App\Repository\MonsterGameRankRepository;
use App\Repository\GameRepository;
use App\Repository\RankRepository;
use Symfony\Bundle\FrameworkBundle\Manager\AbstractManager;

class MonsterManager extends AbstractManager
{
    public function __construct(
        private MonsterGameRankRepository $mgrRepository, 
        private GameRepository $gameRepository,
        private RankRepository $rankRepository
    ) { }

    public function getMonsters(?string $game, ?string $rank): array
    {
        if (null === $game || null === $rank) {
            return [];
        }

        $game = $this->gameRepository->findOneBy(["abreviation" => $game]);
        $rank = $this->rankRepository->findOneBy(["abreviation" => $rank]);

        if (null === $game || null === $rank) {
            return [];
        }

        $monsters = $this->mgrRepository->findBy([
            "game" => $game,
            "rank" => $rank
        ]);

        if (null === $monsters) {
            return [];
        }

        return array_map(fn($monster) => $monster->getMonster()->getName(), $monsters);
    }
}