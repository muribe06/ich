<?php

namespace ich\TestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Puesto_Competencia
 *
 * @ORM\Table(name="puesto_competencia")
 * @ORM\Table(name="puesto_competencia",uniqueConstraints = 
 *	{ @ORM\UniqueConstraint(columns = {"puesto_id", "competencia_id"}) })
 * @ORM\Entity(repositoryClass="ich\TestBundle\Repository\Puesto_CompetenciaRepository")
 */
class Puesto_Competencia
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
	
    /**
     * @ORM\ManyToOne(targetEntity="ich\TestBundle\Entity\Puesto", inversedBy="competencias") 
     * @ORM\JoinColumn(name="puesto_id",referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $puesto;
  
    /**
     * @ORM\ManyToOne(targetEntity="ich\TestBundle\Entity\Competencia", inversedBy="puestos") 
     * @ORM\JoinColumn(name="competencia_id",referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $competencia;

    /**
     * @var int
     * @Assert\NotBlank()
     * @Assert\LessThanOrEqual(value = 10)
     * @Assert\GreaterThanOrEqual(value = 0)
     * @ORM\Column(name="ponderacion", type="integer")
     */
    private $ponderacion;
    
     /**
     * @var bool
     *
     * @ORM\Column(name="activa", type="boolean")
     */
    private $activa;

    /**
     * Set ponderacion
     *
     * @param int $ponderacion
     * @return Puesto_Competencia
     */
    public function setPonderacion($ponderacion)
    {
        $this->ponderacion = $ponderacion;

        return $this;
    }

    /**
     * Get ponderacion
     *
     * @return int 
     */
    public function getPonderacion()
    {
        return $this->ponderacion;
    }

    /**
     * Set puesto
     *
     * @param \ich\TestBundle\Entity\Puesto $puesto
     * @return Puesto_Competencia
     */
    public function setPuesto(\ich\TestBundle\Entity\Puesto $puesto)
    {
        $this->puesto = $puesto;

        return $this;
    }

    /**
     * Get puesto
     *
     * @return \ich\TestBundle\Entity\Puesto 
     */
    public function getPuesto()
    {
        return $this->puesto;
    }

    /**
     * Set competencia
     *
     * @param \ich\TestBundle\Entity\Competencia $competencia
     * @return Puesto_Competencia
     */
    public function setCompetencia(\ich\TestBundle\Entity\Competencia $competencia)
    {
        $this->competencia = $competencia;

        return $this;
    }

    /**
     * Get competencia
     *
     * @return \ich\TestBundle\Entity\Competencia 
     */
    public function getCompetencia()
    {
        return $this->competencia;
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set activa
     *
     * @param boolean $activa
     * @return Puesto_Competencia
     */
    public function setActiva($activa)
    {
        $this->activa = $activa;

        return $this;
    }

    /**
     * Get activa
     *
     * @return boolean 
     */
    public function getActiva()
    {
        return $this->activa;
    }
}
