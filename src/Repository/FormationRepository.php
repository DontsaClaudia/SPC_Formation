<?php

namespace App\Repository;

use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function save(Formation $formation)
    {
        $this->getEntityManager()->persist($formation);
        $this->getEntityManager()->flush();
    }

    public function delete(Formation $formation)
    {
        $this->getEntityManager()->remove($formation);
    }

}
