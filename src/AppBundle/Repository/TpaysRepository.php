<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Entity\Cart;
use AppBundle\Entity\Produit;

/**
 * CartRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TpaysRepository
{
    const EXCEPTION = [
        'Roche',
        'Font',
    ];

    /**
    * @var EntityManagerInterface
    */
    private $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
    * Find Tpays
    *
    * @return array
    */
    public function findAllCountry()
    {
        $query = $this->entityManager
        ->createQuery('SELECT t FROM AppBundle\Entity\Tpays t ORDER BY t.nompays');
        return $query->getResult();
    }

    /**
    * Find Tpays by country code
    *
    * @return Tpays
    */
    public function findCountryByCode($codCountry)
    {
        $query = $this->entityManager
        ->createQuery('SELECT tp 
        FROM AppBundle\Entity\Tpays tp 
        WHERE tp.codpays=:country');
        $query->setParameter('country', $codCountry);
        return $query->getOneOrNullResult();
    }

    /**
    * Check Zipcode
    *
    * @return Bool
    */
    public function checkZipcode($country, $zipcode, $destliv)
    {
        if (in_array($destliv, $this::EXCEPTION)) {
            return true;
        }

        if (strlen($country) !== 2) {
            return false;
        }
 
        $query = $this->entityManager
        ->createQuery('SELECT tp.nompays 
        FROM AppBundle\Entity\Tpays tp 
        WHERE tp.codpays=:country');
        $query->setParameter('country', $country);
        $result =  $query->getOneOrNullResult();
        $country = $result;
 
        $query = $this->entityManager
        ->createQuery('SELECT tp.codpostaux 
        FROM AppBundle\Entity\Tpays tp 
        WHERE tp.nompays=:country');
        $query->setParameter('country', $country['nompays']);
        $results =  $query->getOneOrNullResult();

        if ($results === null) {
            return false;
        }

        if ($results['codpostaux'] === []) {
            return true;
        }

        if ($results['codpostaux'] === null) {
            return false;
        }

        foreach ($results['codpostaux'] as $k => $result) {
            if ($zipcode >= $result[0] && $zipcode <= $result[1]) {
                return true;
            }
        }
        
        return false;
    }

    public function findCode($country)
    {
        $query = $this->entityManager
        ->createQuery('SELECT t.codpayspbx, t.codpayspaypal FROM AppBundle\Entity\Tpays t WHERE t.codpays = :country');
        $query->setParameter('country', $country);
        return $query->getOneOrNullResult();
    }
}
