<?php

namespace App\Repository;

use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }


    /**
     * @return Quote[]
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('q')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Quote[]
     */
    public function findWithCategoryAndType(?int $category, ?bool $type): array
    {
        if (null !== $category) {
            if (null !== $type) {
                return $this->createQueryBuilder('q')
                    ->where('q.category = :category')
                    ->andWhere('q.type = :type')
                    ->setParameter('category', $category)
                    ->setParameter('type', $type)
                    ->getQuery()
                    ->getResult()
                ;
            }
            return $this->createQueryBuilder('q')
                ->where('q.category = :category')
                ->setParameter('category', $category)
                ->getQuery()
                ->getResult()
            ;
        }
        if (null !== $type) {
            return $this->createQueryBuilder('q')
                ->where('q.type = :type')
                ->setParameter('type', $type)
                ->getQuery()
                ->getResult()
            ;
        }
        return $this->createQueryBuilder('q')
            ->getQuery()
            ->getResult()
        ;
    }
}
