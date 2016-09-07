<?php

namespace ich\TestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Competencia
 *
 * @ORM\Table(name="competencia")
 * @ORM\Entity(repositoryClass="ich\TestBundle\Repository\CompetenciaRepository")
 */
class Competencia
{

     /**
     * @ORM\OneToOne(targetEntity="ich\TestBundle\Entity\Auditoria", inversedBy="competencia") 
     * @ORM\JoinColumn(name="eliminado",referencedColumnName="id", nullable=true)
     */
    protected $auditoria;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="codigo", type="string", length=10)
     */
    private $codigo;

    /**
     * @var string
     *
     * @ORM\Column(name="nombre", type="string", length=30)
     */
    private $nombre;

    /**
     * @var string
     *
     * @ORM\Column(name="descripcion", type="string", length=150)
     */
    private $descripcion;

   
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
     * Set codigo
     *
     * @param string $codigo
     * @return competencia
     */
    public function setCodigo($codigo)
    {
        $this->codigo = $codigo;

        return $this;
    }

    /**
     * Get codigo
     *
     * @return string 
     */
    public function getCodigo()
    {
        return $this->codigo;
    }

    /**
     * Set nombre
     *
     * @param string $nombre
     * @return competencia
     */
    public function setNombre($nombre)
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * Get nombre
     *
     * @return string 
     */
    public function getNombre()
    {
        return $this->nombre;
    }

    /**
     * Set descripcion
     *
     * @param string $descripcion
     * @return competencia
     */
    public function setDescripcion($descripcion)
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    /**
     * Get descripcion
     *
     * @return string 
     */
    public function getDescripcion()
    {
        return $this->descripcion;
    }

 


    /**
     * Set auditoria
     *
     * @param \ich\TestBundle\Entity\Auditoria $auditoria
     * @return Competencia
     */
    public function setAuditoria(\ich\TestBundle\Entity\Auditoria $auditoria = null)
    {
        $this->auditoria = $auditoria;

        return $this;
    }

    /**
     * Get auditoria
     *
     * @return \ich\TestBundle\Entity\Auditoria 
     */
    public function getAuditoria()
    {
        return $this->auditoria;
    }
}
