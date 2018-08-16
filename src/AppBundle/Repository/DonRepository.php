<?php
namespace AppBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;

/**
 * DonRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class DonRepository
{

    /**
    * @var EntityManagerInterface
    */
    private $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    /**
    * Find last year's ref
    *
    */
    public function findLastRef($year)
    {
        $query = $this->entityManager
        ->createQuery('SELECT d.refdon
        FROM AppBundle\Entity\Don d 
        WHERE d.refdon LIKE :year
        ORDER BY d.refdon DESC');
        $query->setParameter('year', $year.'-%')
        ->setMaxResults(1);
        return $query->getOneOrNullResult()['refdon'];
    }

    /**
     * Find by reference
     */
    public function findByRef($ref)
    {
        $query = $this->entityManager
        ->createQuery('SELECT d
        FROM AppBundle\Entity\Don d 
        WHERE d.refdon LIKE :ref');
        $query->setParameter('ref', $ref)
        ->setMaxResults(1);
        return $query->getOneOrNullResult();
    }
}
