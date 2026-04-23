<?php

namespace App\Repository;

use App\Entity\Chapitre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chapitre>
 */
class ChapitreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chapitre::class);
    }

    public function save(Chapitre $chapitre)
    {
        $this->getEntityManager()->persist($chapitre);
        $this->getEntityManager()->flush();
    }

    public function remove(Chapitre $chapitre)
    {
        $this->getEntityManager()->remove($chapitre);
        $this->getEntityManager()->flush();
    }
}
