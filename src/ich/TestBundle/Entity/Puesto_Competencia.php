<?php

namespace ich\TestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Puesto_Competencia
 *
 * @ORM\Table(name="puesto_competencia")
 * @ORM\Entity(repositoryClass="ich\TestBundle\Repository\Puesto_CompetenciaRepository")
 */
class Puesto_Competencia
{
    
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="ich\TestBundle\Entity\Puesto", inversedBy="competencias") 
     * @ORM\JoinColumn(name="puesto_id",referencedColumnName="id", nullable=false)
     */
    protected $puesto;
  
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="ich\TestBundle\Entity\Competencia", inversedBy="puestos") 
     * @ORM\JoinColumn(name="competencia_id",referencedColumnName="id", nullable=false)
     */
    protected $competencia;

    /**
     * @var int
     *
     * @ORM\Column(name="ponderacion", type="integer")
     */
    private $ponderacion;


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
}
