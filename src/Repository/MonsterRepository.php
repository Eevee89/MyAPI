<?php

namespace App\Repository;

use App\Entity\Monster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Monster>
 */
class MonsterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Monster::class);
    }


    /**
     * @return Monster[]
     */
    public function findByGame(string $game, string $rank): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.games LIKE :gamePattern')
            ->andWhere('m.ranks LIKE :rankPattern')
            ->setParameter('gamePattern', '%'.$game.'%')
            ->setParameter('rankPattern', '%'.$rank.'%')
            ->getQuery()
            ->getResult()
        ;
    }
}
