<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tax
 *
 * @ORM\Table(name="regles_taxes")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TaxRepository")
 */
class Tax
{
    /**
     * @var int
     *
     * @ORM\Column(name="IdTax", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="LibTax", type="string", length=30)
     */
    private $name;

    /**
     * @var float
     *
     * @ORM\Column(name="Taux", type="decimal", precision=10, scale=2)
     */
    private $rate;

    /**
     * @var string
     *
     * @ORM\Column(name="Pays", type="text", length=65535)
     */
    private $countries;

    /**
     * @var string
     *
     * @ORM\Column(name="TypPrd", type="string", length=8)
     */
    private $typPrd;

    public function __construct()
    {
        $this->name = '';
        $this->rate = 0;
    }


    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Tax
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set rate.
     *
     * @param float $rate
     *
     * @return Tax
     */
    public function setRate($rate)
    {
        $this->rate = $rate;

        return $this;
    }

    /**
     * Get rate.
     *
     * @return float
     */
    public function getRate()
    {
        return $this->rate;
    }

    /**
     * Set countries.
     *
     * @param json $countries
     *
     * @return Tax
     */
    public function setCountries($countries)
    {
        $this->countries = $countries;

        return $this;
    }

    /**
     * Get countries.
     *
     * @return string
     */
    public function getCountries()
    {
        return $this->countries;
    }

    /**
     * Set typPrd.
     *
     * @param string $typPrd
     *
     * @return Tax
     */
    public function setTypPrd($typPrd)
    {
        $this->typPrd = $typPrd;

        return $this;
    }

    /**
     * Get typPrd.
     *
     * @return string
     */
    public function getTypPrd()
    {
        return $this->typPrd;
    }
}
