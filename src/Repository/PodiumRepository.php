<?php

namespace App\Repository;

use App\Entity\Podium;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Podium>
 */
class PodiumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Podium::class);
    }


    /**
     * @return Podium[]
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('m')
            ->getQuery()
            ->getResult()
        ;
    }
}
