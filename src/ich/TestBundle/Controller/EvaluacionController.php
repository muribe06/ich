<?php

namespace ich\TestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\Date;
use ich\TestBundle\Entity\Cuestionario;
use ich\TestBundle\Entity\Evaluacion;
use ich\TestBundle\Entity\CopiaCompetencia;
use ich\TestBundle\Entity\CopiaFactor;
use ich\TestBundle\Entity\CopiaPregunta;
use ich\TestBundle\Entity\CopiaOpcionRespuesta;
use ich\TestBundle\Form\CopiaPreguntaType;

class EvaluacionController extends Controller {

	public function newStep1Action(Request $request) {
		
		$em = $this->getDoctrine ()->getManager ();
			
		$defaultData = array ();
		
		$form = $this->createEvaluarForm($defaultData);

		$query = $em->createQuery ( "SELECT c.apellido, c.nombre, c.nroCandidato FROM ichTestBundle:Candidato c" );
		$candidatos = $query->getResult ();

		return $this->render ( 'ichTestBundle:Evaluacion:add1.html.twig', array ('candidatos' => json_encode($candidatos),
				'form' => $form->createView ()
		) );	
	}
	
	
	private function createEvaluarForm($array){
		
		$form = $this->createFormBuilder ( $array )->add ( 'candidatos', CollectionType::class, array (
				'entry_type' => NumberType::class,
				'by_reference' => false,
				'allow_add' => true
		) )->add ( 'send', SubmitType::class )
		->setAction ( $this->generateUrl ( 'ich_evaluacion_newStep2' ) )
		->setMethod ( 'POST' )
		->getForm ();
		
		return $form;
	}
	
	
	
	
	public function newStep2Action(Request $request) {
		
		$co = $this->getDoctrine ()->getManager ();

			$defaultData = array ();
			
			$form = $this->createEvaluarForm($defaultData);

			$form->handleRequest ( $request );
			
			if ($form->isValid ()) {
				$data = $form->getData ();
	
				$this->get('session')->set('nroCandidatos',$data);

				
				//BUSCAR PUESTOS CON AL MENOS UNA COMPETENCIA ASIGNADA
				$dql = "SELECT p.id idPuesto, p.nombre nombrePuesto, e.nombre nombreEmpresa
				FROM ichTestBundle:Puesto p JOIN ichTestBundle:Empresa e
				WHERE e.id = IDENTITY(p.empresa) and p.id IN
				(SELECT IDENTITY(pc.puesto) FROM ichTestBundle:Puesto_Competencia pc WHERE pc.activa = true)";
				$query = $co->createQuery ( $dql );
				
				$puestos= $query->getResult();

				return $this->render ( 'ichTestBundle:Evaluacion:add2.html.twig', array ('puestos' => json_encode($puestos)) );
					
			}
			
			
			$form = $this->createEvaluarForm($defaultData);
			
			//ERROR EN FORMULARIO - VOLVER A SOLICITAR CANDIDATOS
			return $this->render ( 'ichTestBundle:Evaluacion:add1.html.twig', array (
					'form' => $form->createView () 
			) );
			
	}
	
	
	
	public function buscarCandidatosActivosAction(Request $request) {
		
		$data = $request->request->get ( 'nrosCandidato' );
		
		$co = $this->getDoctrine ()->getManager ();
		
		$query = $co->createQuery ( "SELECT c
					FROM ichTestBundle:Candidato c JOIN ichTestBundle:Cuestionario cu
					where cu.candidato = c and cu.estado = 0
					" );
		
		$datosCandidatosActivos = array ();
		// DEVUELVE ARRAY CON ARRAYS DE CANDIDATO
		$candidatosActivos = $query->getResult ();
		
		foreach ( $candidatosActivos as $candidato ) {

			for($i=0, $total = count ( $data ); $i < $total; $i ++) {
				
				if ($candidato->getNroCandidato() == $data [$i])
					$datosCandidatosActivos [] = array (
					'apellido' => $candidato->getApellido (),
					'nombre' => $candidato->getNombre ());
			}
		}
		
		return new JsonResponse ( $datosCandidatosActivos );
	}
	
	
	
	
	public function newStep3Action($id) {
		
		$em = $this->getDoctrine ()->getManager ();
			
		$puesto = $em->getRepository ( 'ichTestBundle:Puesto' )->find ( $id );
			
		if ($puesto->getAuditoria() != NULL) {
			throw $this->createNotFoundException ( 'El Puesto ha sido eliminado.' );
		}
			
		$this->get('session')->set('idPuesto',$id);
			
		$query = $em->createQuery ( "SELECT c.nombre
    	FROM ichTestBundle:Puesto_Competencia pc JOIN ichTestBundle:Competencia c
    	WHERE pc.puesto = :p and pc.competencia = c and pc.activa = true and c.auditoria is NULL and c.id NOT IN
		(SELECT distinct c2.id
    	FROM ichTestBundle:Puesto_Competencia pc2 JOIN ichTestBundle:Competencia c2
    	WHERE pc2.puesto = :p and pc2.competencia = c2 and c2.auditoria is NULL and c2.id in (
		SELECT distinct c3.id
		FROM ichTestBundle:Competencia c3 JOIN ichTestBundle:Factor f
		WHERE c3 = f.competencia and f.id IN (
		SELECT distinct f2.id
		FROM ichTestBundle:Factor f2 JOIN ichTestBundle:Pregunta pr
		WHERE pr.factor = f2 and f2.auditoria is NULL and pr.auditoria is NULL
		GROUP BY f2.id
		HAVING count(distinct pr.id) >= 2)))" )->setParameter ( 'p', $puesto );

		$competenciasInvalidas = $query->getResult ();
		$competencias =  array();

		if(count($competenciasInvalidas) == 0){

			$query = $em->createQuery ( 'SELECT c.nombre, c.codigo, c.descripcion, pc.ponderacion
    			FROM ichTestBundle:Puesto_Competencia pc JOIN ichTestBundle:Competencia c
    			WHERE IDENTITY(pc.puesto) = :p and pc.activa = true and pc.competencia = c and c.auditoria is NULL' )
				->setParameter ( 'p', $puesto->getId() );
			
			$competencias = $query->getResult ();

			$this->get ( 'session' )->set ( 'competencias', $competencias );
		
		}
	
		return $this->render ( 'ichTestBundle:Evaluacion:add3.html.twig', array (
			'competencias' => json_encode($competencias),
			'competenciasInvalidas' => $competenciasInvalidas,
			'nombrePuesto' => $puesto->getNombre()
				) );
		
	}
	
	
	public function newStep4Action() {
		
		$em = $this->getDoctrine ()->getManager ();
		
		$nroCandidatos = $this->get ( 'session' )->get ( 'nroCandidatos' );
			
		$claveNroCandidatos = array ();
		
		$candidatosSeleccionados = array ();
		
		for($i=0, $total = count ( $nroCandidatos ['candidatos'] ); $i < $total; $i ++) {
		
			$candidato = $em->getRepository ( 'ichTestBundle:Candidato' )->find ( $nroCandidatos ['candidatos'] [$i] );

			if (!$candidato)
			throw $this->createNotFoundException ( "Candidato Nro ".' '.$nroCandidatos ['candidatos'] [$i].''." no encontrado.");

			if($candidato->getTipoDocumento() == 1)
				$tipo = "DNI";
				else if($candidato->getTipoDocumento() == 2)
					$tipo = "LE";
					else if($candidato->getTipoDocumento() == 3)
						$tipo = "LC";
						else if($candidato->getTipoDocumento() == 4)
							$tipo = "PP";
		
			$random = random_bytes(4);
			$clave = bin2hex($random);
		
			$candidatosSeleccionados [] = array (
				'apellido' => $candidato->getApellido(),
				'nombre' => $candidato->getNombre(),
				'tipoDocumento' => $tipo,
				'documento' => $candidato->getNroDocumento(),
				'clave'  => $clave
			);
		
			$claveNroCandidatos  [] = array (
				'nroCandidato' => $nroCandidatos ['candidatos'] [$i],
				'clave'  => $clave
			);
		}
			
		$this->get('session')->set('claveNroCandidatos',$claveNroCandidatos);
			
		return $this->render ( 'ichTestBundle:Evaluacion:add4.html.twig', array (
			'candidatosSeleccionados' => json_encode($candidatosSeleccionados)
		) );

	}
	
	
	private function comprobarCandidatosActivos($nroCandidatos){
		
	$em = $this->getDoctrine ()->getManager ();
		
	$query = $em->createQuery ( "SELECT c.nroCandidato
					FROM ichTestBundle:Candidato c JOIN ichTestBundle:Cuestionario cu
					where cu.candidato = c and cu.estado = 0
					" );
	
	$candidatosSeleccionadosActivos = array ();
	// DEVUELVE ARRAY CON ARRAYS DE CANDIDATO
	$candidatosActivos = $query->getResult ();
	
	foreach ( $candidatosActivos as $candidato ) {
		$i = 0;
	
		for($i, $total = count ( $nroCandidatos ['candidatos'] ); $i < $total; $i ++) {
			if ($candidato ['nroCandidato'] == $nroCandidatos ['candidatos'] [$i])
				$candidatosSeleccionadosActivos [] = $candidato ['nroCandidato'];
		}
	}
	
	$datosCandidatosActivos = array ();
	
	if (count ( $candidatosSeleccionadosActivos ) != 0) {

		foreach ( $candidatosSeleccionadosActivos as $nroCandidato ) {
	
			$candidato = $em->getRepository ( 'ichTestBundle:Candidato' )->find ( $nroCandidato );
	
			$datosCandidatosActivos [] = array (
					'apellido' => $candidato->getApellido (),
					'nombre' => $candidato->getNombre ()
			);
		}
	}
	
	return $datosCandidatosActivos;
	
}

	
	public function newStep5Action(Request $request) {
		
		$em = $this->getDoctrine ()->getManager ();
		
		$nroCandidatos = $this->get ( 'session' )->get ( 'nroCandidatos' );
		
		$candidatos = array ();
		
		//COMPROBAR SI HAY CANDIDATOS ACTIVOS ENTRE LOS SELECCIONADOS PARA SER EVALUADOS
		if(count($candidatosSeleccionadosActivos = $this->comprobarCandidatosActivos($nroCandidatos)) != 0){
			$response = new JsonResponse(null,500);
			$response->setData($candidatosSeleccionadosActivos);
			return $response;
		}
		else //OBTENER ENTIDADES DE CANDIDATOS SELECCIONADOS
			{
				for($i= 0, $total = count ( $nroCandidatos ['candidatos'] ); $i < $total; $i ++) {
			
					$candidato = $em->getRepository ( 'ichTestBundle:Candidato' )->find ( $nroCandidatos ['candidatos'] [$i] );
					if (!$candidato) {
						$response = new JsonResponse(null,500);
						$response->setData('Candidato seleccionado no encontrado.');
						return $response;
					}
					
					$candidatos [] = $candidato;
				}
			}
	
			
		$idPuesto = $this->get('session')->get('idPuesto');
		
		$puesto = $em->getRepository ( 'ichTestBundle:Puesto' )->find ( $idPuesto );
		
		if ($puesto->getAuditoria() != NULL) {
			$response = new JsonResponse(null,500);
			$response->setData('El Puesto seleccionado ha sido eliminado.');
			return $response;
		}
		
		

		$arrayCompetenciasTemporal = $this->get ( 'session' )->get ( 'competencias' );
		
		//COMPETENCIAS VALIDAS PARA SER EVALUADAS
		$query = $em->createQuery ( "SELECT c.nombre, c.codigo, c.descripcion, pc.ponderacion
    			FROM ichTestBundle:Puesto_Competencia pc JOIN ichTestBundle:Competencia c
    			WHERE IDENTITY(pc.puesto) = :p and pc.competencia = c and pc.activa = true and c.auditoria is NULL and c.id IN
				(SELECT distinct c2.id
    			FROM ichTestBundle:Puesto_Competencia pc2 JOIN ichTestBundle:Competencia c2
    			WHERE pc2.puesto = :p and pc2.competencia = c2 and c2.auditoria is NULL and c2.id in (
				SELECT distinct c3.id
				FROM ichTestBundle:Competencia c3 JOIN ichTestBundle:Factor f
				WHERE c3 = f.competencia and f.id IN (
				SELECT distinct f2.id
				FROM ichTestBundle:Factor f2 JOIN ichTestBundle:Pregunta pr
				WHERE pr.factor = f2 and f2.auditoria is NULL and pr.auditoria is NULL
				GROUP BY f2.id
				HAVING count(distinct pr.id) >= 2)))" )->setParameter ( 'p', $puesto->getId () );
		
		$arrayCompetenciasValidas = $query->getResult ();
		
		//VERIFICAR SI COMPETENCIAS PREVIAMENTE VALIDADAS COINCIDEN CON ACTUALES

		if(count ($arrayCompetenciasValidas) == count ($arrayCompetenciasTemporal)){
			foreach($arrayCompetenciasValidas as $competenciaValida)
			{
				$esValida = false;

				foreach($arrayCompetenciasTemporal as $competenciaTemporal){
					if($competenciaTemporal['codigo'] == $competenciaValida['codigo'])
						$esValida = true;
				}

				if (!$esValida){
					$response = new JsonResponse(null,500);
					$response->setData('Existe al menos una Competencia que ya no cumple los requisitos para ser evaluada.');
					return $response;
				}	
			}
		}
		else{
			$response = new JsonResponse(null,500);
			$response->setData('Se detectaron cambios en Competencias del Puesto seleccionado.');
			return $response;
		}	

		
		//VERIFICAR SI EXISTEN PARAMETROS DE CONFIGURACIÓN
		if(!$this->container->hasParameter('ichTestBundle.preguntasPorBloque'))
		{
			$response = new JsonResponse(null,500);
			$response->setData('ParÃ¡metro preguntasPorBloque requerido no disponible.');
			return $response;
		}
		else if(!$this->container->hasParameter('ichTestBundle.tiempoMaxCuestionario'))
		{
			$response = new JsonResponse(null,500);
			$response->setData('ParÃ¡metro tiempoMaxCuestionario requerido no disponible.');
			return $response;
		}
		else if(!$this->container->hasParameter('ichTestBundle.tiempoMaxActivoCuestionario'))
		{
			$response = new JsonResponse(null,500);
			$response->setData('ParÃ¡metro tiempoMaxActivoCuestionario requerido no disponible.');
			return $response;
		}
		else if(!$this->container->hasParameter('ichTestBundle.cantMaxAccesosCuestionario'))
		{
			$response = new JsonResponse(null,500);
			$response->setData('ParÃ¡metro cantMaxAccesosCuestionario requerido no disponible.');
			return $response;
		}
		
		
		//CREAR EVALUACIÓN
		
		$evaluacion = new Evaluacion();
		
		$evaluacion->setPuesto($puesto);
		
		$evaluacion->setFechaCreacion(new \DateTime());
		
		$evaluacion->setNombre("Ev_".''."P_".''.$puesto->getNombre().''."_".''.bin2hex(random_bytes(2)));
		
		$claveNroCandidatos = $this->get('session')->get('claveNroCandidatos');
		
		foreach ( $candidatos as $candidato ) {
				
			$cuestionario = new Cuestionario();
			
			for($i=0, $total = count ($claveNroCandidatos); $i < $total; $i ++) {
				if ($candidato->getNroCandidato() == $claveNroCandidatos[$i]['nroCandidato'])
					$cuestionario->setClave($claveNroCandidatos[$i]['clave']);
			}
				
			$cuestionario->setEstado(0);
			
			$cuestionario->setCantAccesos(0);
			
			$cantMaxAccesos = $this->container->getParameter('ichTestBundle.cantMaxAccesosCuestionario');

			$cuestionario->setCantMaxAccesos($cantMaxAccesos);
			
			$tiempoMax = $this->container->getParameter('ichTestBundle.tiempoMaxCuestionario');

			$cuestionario->setTiempoMax($tiempoMax);

			$tiempoMaxActivo = $this->container->getParameter('ichTestBundle.tiempoMaxActivoCuestionario');
			
			$cuestionario->setTiempoMaxActivo($tiempoMaxActivo);
			
			$cuestionario->setPuntajeTotal(0);
			
			$cuestionario->setCandidato($candidato);
			
			$cuestionario->setEvaluacion($evaluacion);
			
			$evaluacion->addCuestionario($cuestionario);	
		}
		
		$em->persist ( $evaluacion );
		
		$em->flush ();
		
		
		$cuestionarios = $evaluacion->getCuestionarios();
			
		foreach ( $cuestionarios as $cuestionario ) {
			
			foreach ( $arrayCompetenciasValidas as $competencia ) {
				
				$copiaCompetencia = new CopiaCompetencia ();
				
				$copiaCompetencia->setCodigo ( $competencia ['codigo'] );
				
				$copiaCompetencia->setDescripcion ( $competencia ['descripcion'] );
				
				$copiaCompetencia->setNombre ( $competencia ['nombre'] );
				
				$copiaCompetencia->setPonderacion ( $competencia ['ponderacion'] );
				
				$copiaCompetencia->setCuestionario ( $cuestionario );
				
				$cuestionario->addCopiaCompetencia ( $copiaCompetencia );
			}
			
			$em->persist ( $cuestionario );
		}
		
		$em->flush ();
		
		
		
		$cuestionarios = $em->getRepository ( 'ichTestBundle:Cuestionario' )->findBy ( array (
				'evaluacion' => $evaluacion 
		) );
		
		foreach ( $cuestionarios as $cuestionario ) {
			
			foreach ( $cuestionario->getCopiaCompetencias () as $copiaCompetencia ) {
				
				$competencia = $em->getRepository ( 'ichTestBundle:Competencia' )->findOneBy ( array ('codigo' => $copiaCompetencia->getCodigo ()) );
				
				$factores = $competencia->getFactores ();
				
				foreach ( $factores as $factor ) {
					
					if (count ( $factor->getPreguntas () ) >= 2) {
						
						$copiaFactor = new CopiaFactor ();
						
						$copiaFactor->setCodigo ( $factor->getCodigo () );
						
						$copiaFactor->setDescripcion ( $factor->getDescripcion () );
						
						$copiaFactor->setNombre ( $factor->getNombre () );
						
						$copiaFactor->setNroOrden ( $factor->getNroOrden () );
						
						$copiaFactor->setCopiaCompetencia ( $copiaCompetencia );
						
						$copiaCompetencia->addCopiaFactore ( $copiaFactor );
					}
				}
				
				$em->persist ( $copiaCompetencia );
			}
		}
		
		$em->flush ();
		
		
		
		foreach ( $cuestionarios as $cuestionario ) {
			
			foreach ( $cuestionario->getCopiaCompetencias () as $copiaCompetencia ) {
				
				$competencia = $em->getRepository ( 'ichTestBundle:Competencia' )->findOneBy ( array (
						'codigo' => $copiaCompetencia->getCodigo () 
				) );
				
				$copiaFactores = $copiaCompetencia->getCopiaFactores ();
				$factores = $competencia->getFactores ();
				$factorActual;
				
				foreach ( $copiaFactores as $copiaFactor ) {
					
					foreach ( $factores as $factor ) {
						if ($factor->getCodigo () == $copiaFactor->getCodigo ()) {
							$factorActual = $factor;
							break;
						}
					}
					
					$arrayPreguntas = $em->getRepository ( 'ichTestBundle:Pregunta' )->findBy ( array ('factor' =>  $factorActual) );

					//SELECCIONAR 2 PREGUNTAS AL AZAR
					shuffle ($arrayPreguntas );
					
					for($i = 0; $i < 2; $i ++) {
						
						$copiaPregunta = new CopiaPregunta ();
						
						$copiaPregunta->setCodigo ( $arrayPreguntas [$i]->getCodigo () );
						
						$copiaPregunta->setPregunta ( $arrayPreguntas [$i]->getPregunta () );
						
						$copiaPregunta->setDescripcion ( $arrayPreguntas [$i]->getDescripcion () );
						
						$copiaPregunta->setCopiaFactor ( $copiaFactor );
						
						$copiaFactor->addCopiaPregunta ( $copiaPregunta );
					}
					
					$em->persist ( $copiaFactor );
				}
			}
		}
		
		$em->flush ();
		
	
		foreach ( $cuestionarios as $cuestionario ) {
			
			//GUARDAR COPIA PREGUNTAS PARA MEZCLARLAS Y DIVIDIRLAS POR BLOQUES
			$copiaPreguntasCuestionario = array();
			
			foreach ( $cuestionario->getCopiaCompetencias () as $copiaCompetencia ) {
				
				$competencia = $em->getRepository ( 'ichTestBundle:Competencia' )->findOneBy ( array (
						'codigo' => $copiaCompetencia->getCodigo () 
				) );
				
				$copiaFactores = $copiaCompetencia->getCopiaFactores ();
				$factores = $competencia->getFactores ();
				$factorActual;
				
				foreach ( $copiaFactores as $copiaFactor ) {
					
					foreach ( $factores as $factor ) {
						if ($factor->getCodigo () == $copiaFactor->getCodigo ()) {
							$factorActual = $factor;
							break;
						}
					}
					
					$copiaPreguntas = $copiaFactor->getCopiaPreguntas ();
					$preguntas = $factorActual->getPreguntas ();
					$preguntaActual;
					
					foreach ( $copiaPreguntas as $copiaPregunta ) {
						
						foreach ( $preguntas as $pregunta ) {
							if ($pregunta->getCodigo () == $copiaPregunta->getCodigo ()) {
								$preguntaActual = $pregunta;
								break;
							}
						}
						
						$preguntaOpcionesRespuesta = $preguntaActual->getOpcionesRespuesta ();
						
						foreach ( $preguntaOpcionesRespuesta as $preguntaOpcionRespuesta ) {
							
							$opcionRespuesta = $preguntaOpcionRespuesta->getOpcionRespuesta ();
							
							$copiaOpcionRespuesta = new CopiaOpcionRespuesta ();
							
							$copiaOpcionRespuesta->setDescripcion ( $opcionRespuesta->getDescripcion () );
							
							$copiaOpcionRespuesta->setOrdenEvaluacion ( $opcionRespuesta->getOrdenEvaluacion () );
							
							$copiaOpcionRespuesta->setPonderacion ( $preguntaOpcionRespuesta->getPonderacion () );
							
							$copiaOpcionRespuesta->setCopiaPregunta ( $copiaPregunta );
							
							$copiaPregunta->addCopiaOpcionesRespuestum ( $copiaOpcionRespuesta );
						}
						
						$em->persist ( $copiaPregunta );
						
						$copiaPreguntasCuestionario[] = $copiaPregunta;
		
					}
				}
			}
			
			//ASIGNAR A COPIA PREGUNTAS NRO ORDEN Y NRO BLOQUE ALEATORIAMENTE 
			shuffle ( $copiaPreguntasCuestionario );
			
			$preguntasPorBloque = $this->container->getParameter('ichTestBundle.preguntasPorBloque');
			
			$bloqueActual = 1;
			
			$preguntasBloqueActual = 0;
				
			for($i = 0, $total = count ($copiaPreguntasCuestionario); $i < $total; $i ++) {
			
				if($preguntasPorBloque == $preguntasBloqueActual){
					$preguntasBloqueActual = 0;
					$bloqueActual++;
				}
				
				$copiaPregunta = $copiaPreguntasCuestionario[$i];
				
				$preguntasBloqueActual++;
				
				$copiaPregunta->setNroOrden($preguntasBloqueActual);
				
				$copiaPregunta->setNroBloque($bloqueActual);
				
				$em->persist ( $copiaPregunta );
				
			}
				
		}
		
		$em->flush ();
		
		//GENERAR NOMBRE PLANILLA EXCEL
		$nombre_xls = date_format ( new \datetime (), 'Y-m-d-H-i' ) . '' . "-" . '' . $puesto->getNombre ();
		
		$this->get ( 'session' )->remove ( 'competencias' );
		
		$this->get ( 'session' )->remove ( 'nroCandidatos' );
		
		$this->get ( 'session' )->remove ( 'idPuesto' );
		
		$this->get ( 'session' )->remove ( 'claveNroCandidatos' );
		
		return new JsonResponse ( $nombre_xls );
	}




	public function verificarEstadoCuestionarioAction(Request $request, $id, $esUltimoBloque) {

		$em = $this->getDoctrine ()->getManager ();
		
		$cuestionario = $em->getRepository ( 'ichTestBundle:Cuestionario' )->find ( $id );
		
		if (! $cuestionario) {
			throw $this->createNotFoundException ( 'Cuestionario no encontrado.' );
		}
		
		$estadoCuestionario = $cuestionario->getEstado ();
		$method = $request->getMethod ();

		if ($estadoCuestionario == 2){
			if ($cuestionario->getCantAccesos () > $cuestionario->getCantMaxAccesos ())
				return $this->render ( 'ichTestBundle:Evaluacion:notificacion.html.twig', array (
					'mensaje' => "Ha superado la cantidad máxima de accesos permitidos.
					Acceso denegado." 
				) );
			else
				return $this->render ( 'ichTestBundle:Evaluacion:notificacion.html.twig', array (
					'mensaje' => "Ha caducado el tiempo asignado para completar el cuestionario. Acceso denegado." 
				) );
		}
			
		else if($estadoCuestionario == 1)
			return $this->render ( 'ichTestBundle:Evaluacion:notificacion.html.twig', array (
					'mensaje' => "El cuestionario ha sido completado. Acceso denegado." 
			) );

		//REQUEST GET, ENTONCES ACABA DE INGRESAR AL CUESTIONARIO
		if ($method == 'GET' && $cuestionario->getComienzoEn ()) {
			$cantAccesos = $cuestionario->getCantAccesos ();
			
			$cuestionario->setCantAccesos ( $cantAccesos + 1 );
		
		if ($cuestionario->getCantAccesos () > $cuestionario->getCantMaxAccesos ()) {
			$cuestionario->setEstado ( 2 );
			
			$cuestionario->setEstadoEn ( new \DateTime () );
			
			$em->persist ( $cuestionario );
			
			$em->flush ();
			
			return $this->render ( 'ichTestBundle:Evaluacion:notificacion.html.twig', array (
					'mensaje' => "Ha superado la cantidad máxima de accesos permitidos.
					Acceso denegado." 
			) );
		}

			$em->persist ( $cuestionario );
			
			$em->flush ();

		}


		if (! $cuestionario->getComienzoEn ())
			return $this->redirectToRoute ( 'ich_evaluacion_ingresoCuestionario', array (
					'cuestionarioId' => $id 
			) );
		else {
			// VERIFICAR SI SE HA SUPERADO EL TIEMPO MAXIMO PARA COMPLETAR EL CUESTIONARIO
			$fechaHoraActual = new \DateTime ();
		
			$fechaHoraComienzoCuestionario = $cuestionario->getComienzoEn ();
		
			$tiempoTranscurrido = $fechaHoraComienzoCuestionario->diff ( $fechaHoraActual );
		
			$horasMinutosFloat = ($tiempoTranscurrido->format ( '%d' ) * 24) + $tiempoTranscurrido->format ( '%h' ) + ($tiempoTranscurrido->format ( '%i' ) / 100);
		
			if ($horasMinutosFloat >= $cuestionario->getTiempoMax ()) {
				$cuestionario->setEstado ( 2 );
			
				$cuestionario->setEstadoEn ( new \DateTime () );
			
				$em->persist ( $cuestionario );
			
				$em->flush ();
			
				return $this->render ( 'ichTestBundle:Evaluacion:notificacion.html.twig', array (
						'mensaje' => "Ha caducado el tiempo asignado para completar el cuestionario. Acceso denegado." 
				) );
			}
		}
		
		if ($method == 'POST') {

			if($estadoCuestionario == 0){

			$fechaHoraActual = new \DateTime ();
			
			$fechaHoraEstadoCuestionario = $cuestionario->getEstadoEn ();
			
			$tiempoTranscurrido = $fechaHoraEstadoCuestionario->diff ( $fechaHoraActual );
			
			$horasMinutosFloat = ($tiempoTranscurrido->format ( '%d' ) * 24) + $tiempoTranscurrido->format ( '%h' ) + ($tiempoTranscurrido->format ( '%i' ) / 100);
			
				if ($horasMinutosFloat >= $cuestionario->getTiempoMaxActivo ()) {
				
					$cuestionario->setEstado ( 3 );
				
					$cuestionario->setEstadoEn ( new \DateTime () );
				
					$em->persist ( $cuestionario );
				
					$em->flush ();
				
					return $this->render ( 'ichTestBundle:Evaluacion:notificacion.html.twig', array (
						'mensaje' => "Ha superado el tiempo de actividad permitido. Por favor, vuelva a ingresar." 
					) );
				}

			}

				$this->get('session')->set('request',$request); 

				return $this->redirectToRoute ( 'ich_evaluacion_gestionarBloqueCuestionario', array (
					'idCuestionario' => $id, 'esUltimoBloque' => $esUltimoBloque
				) );


		} //SI ACABA DE INGRESAR SE RENUEVA EL ESTADO ACTIVO
		else if($method == 'GET'){

			if($estadoCuestionario == 0){

				$cuestionario->setEstadoEn ( new \DateTime () );
			
				$em->persist ( $cuestionario );
			
				$em->flush ();
			}
			else if ($estadoCuestionario == 3) {

				$cuestionario->setEstado ( 0 );
			
				$cuestionario->setEstadoEn ( new \DateTime () );
			
				$em->persist ( $cuestionario );
			
				$em->flush ();
			}
		}
		
		return $this->redirectToRoute ( 'ich_evaluacion_recuperarUltimoBloqueCuestionario', array (
				'idCuestionario' => $id 
		) );
	}
	

	public function recuperarUltimoBloqueCuestionarioAction($idCuestionario) {

		$em = $this->getDoctrine ()->getManager ();
		
		$cuestionario = $em->getRepository ( 'ichTestBundle:Cuestionario' )->find ( $idCuestionario );
		
		if (! $cuestionario) {
			throw $this->createNotFoundException ( 'Cuestionario no encontrado.' );
		}

		$copiaPreguntasCuestionario = array ();
		$bloqueMenor = 0;
		$bloqueMayor = 0;

		// OBTENER NRO DE BLOQUE A RESPONDER Y TODAS LAS PREGUNTAS NO RESPONDIDAS
		foreach ( $cuestionario->getCopiaCompetencias () as $copiaCompetencia ) {
			
			foreach ( $copiaCompetencia->getCopiaFactores () as $copiaFactor ) {
				
				foreach ( $copiaFactor->getCopiaPreguntas () as $copiaPregunta ) {
					
					$seleccionada = false;
					
					foreach ( $copiaPregunta->getCopiaOpcionesRespuesta () as $copiaOpcionRespuesta ) {
						
						if ($copiaOpcionRespuesta->getSeleccionada () != NULL && $copiaOpcionRespuesta->getSeleccionada () == true)
							$seleccionada = true;
					}
					
					if (! $seleccionada){

					if ($bloqueMayor == 0)
						$bloqueMayor = $copiaPregunta->getNroBloque ();

					else if ($copiaPregunta->getNroBloque () > $bloqueMayor)
						$bloqueMayor = $copiaPregunta->getNroBloque ();

					if ($bloqueMenor == 0)
						$bloqueMenor = $copiaPregunta->getNroBloque ();

					else if ($copiaPregunta->getNroBloque () < $bloqueMenor)
						$bloqueMenor = $copiaPregunta->getNroBloque ();
					
					
						$copiaPreguntasCuestionario [] = $copiaPregunta;
					}
				}
			}
		}

		$copiasPreguntasBloqueMenor = array ();
		
		$copiasPreguntasByNroOrden = array (
				'copiaPreguntas' => array () 
		);
		
		// OBTENER PREGUNTAS NO RESPONDIDAS QUE PERTENECEN AL NRO DE BLOQUE A RESPONDER Y ORDENARLAS POR NRO ORDEN
		foreach ( $copiaPreguntasCuestionario as $copiaPreguntaCuestionario ) {
			
			if ($copiaPreguntaCuestionario->getNroBloque () == $bloqueMenor)
				$copiasPreguntasBloqueMenor [] = $copiaPreguntaCuestionario;
		}
		

		for($i = 1, $totalPreguntas = count ( $copiasPreguntasBloqueMenor ); $i <= $totalPreguntas; $i ++) {
			
			foreach ( $copiasPreguntasBloqueMenor as $copiaPreguntaBloqueMenor ) {
				
				if ($copiaPreguntaBloqueMenor->getNroOrden () == $i) {

					$copiasPreguntasByNroOrden ['copiaPreguntas'] [] = array (
							'id' => $copiaPreguntaBloqueMenor->getId (),
							'pregunta' => $copiaPreguntaBloqueMenor->getPregunta ()
					);
				}
			}
		}

		
		
		
		/*
		 print_r ( $form->getData () );
		 throw $this->createNotFoundException ( count ( $form->getData () ['copiaPreguntas'] ) );
		 
		*/
		 if($bloqueMayor == $bloqueMenor){

		 	$form = $this->createBloqueCuestionarioForm ( $copiasPreguntasByNroOrden, $idCuestionario, 1);

		 	return $this->render ( 'ichTestBundle:Evaluacion:completarUltimoBloqueCuestionario.html.twig', array (
				'form' => $form->createView (), 'preguntasNoRespondidas' => array() 
			) );
		 }
		 else{

			$form = $this->createBloqueCuestionarioForm ( $copiasPreguntasByNroOrden, $idCuestionario, 0);
			
			return $this->render ( 'ichTestBundle:Evaluacion:completarCuestionario.html.twig', array (
				'form' => $form->createView (), 'preguntasNoRespondidas' => array() 
			) );
		}
	}

	
	
	private function createBloqueCuestionarioForm($copiasPreguntasByNroOrden, $idCuestionario, $esUltimoBloque){
		
	$form = $this->createFormBuilder ( $copiasPreguntasByNroOrden )->add ( 'copiaPreguntas', CollectionType::class, array (
			'entry_type' => CopiaPreguntaType::class,
			'allow_add' => true,
			'by_reference' => false
	) )->add ( 'send', SubmitType::class )->setAction ( $this->generateUrl ( 'ich_evaluacion_verificarEstadoCuestionario', array (
			'id' => $idCuestionario, 'esUltimoBloque' => $esUltimoBloque
	) ) )->setMethod ( 'POST' )->getForm ();
	
	return $form;
	
	}
	
	
	public function ingresoCuestionarioAction($cuestionarioId){
		
		if(!$this->container->hasParameter('ichTestBundle.instruccionesCuestionario'))
			throw $this->createNotFoundException ( 'ParÃ¡metro instruccionesCuestionario requerido no disponible.' );

		$instrucciones = $this->container->getParameter('ichTestBundle.instruccionesCuestionario');

		$em = $this->getDoctrine ()->getManager ();
		
		$cuestionario = $em->getRepository ( 'ichTestBundle:Cuestionario' )->find ( $cuestionarioId );
		
		if (! $cuestionario) {
			throw $this->createNotFoundException ( 'Cuestionario no encontrado.' );
		}


		$condiciones = array('tiempoMax' => $this->floatTimeToString($cuestionario->getTiempoMax ()),
							 'tiempoMaxActivo'  => $this->floatTimeToString($cuestionario->getTiempoMaxActivo()),
							 'cantAccesos' => ($cuestionario->getCantMaxAccesos()-1)
			);

		
		return $this->render ( 'ichTestBundle:Evaluacion:instrucciones.html.twig', array (
				'idCuestionario' => $cuestionarioId, 'instrucciones' => $instrucciones, 'condiciones' => $condiciones 
			) );
	}


	private function floatTimeToString($timeFloat){

		$hour = floor($timeFloat);
		$minutes = ($timeFloat - $hour)*100; 
		if($minutes < 10)
		$minutes = "0".$minutes;
		return $hour.":".$minutes;
	}	

	public function gestionarBloqueCuestionarioAction($idCuestionario, $esUltimoBloque){

		
		$request = $this->get('session')->get('request'); 

		$em = $this->getDoctrine ()->getManager ();

		$form = $this->createBloqueCuestionarioForm(null, $idCuestionario, $esUltimoBloque);

		$form->handlerequest($request);

		if($form->isValid()){
		
		$datosBloque = $form->getData(); 

		if($this->bloqueFueRespondido($datosBloque)){

			foreach($datosBloque['copiaPreguntas'] as $copiaPregunta){

				$idOpcionSeleccionada = $copiaPregunta['copiaOpcionesRespuesta']->getId();

				$copiaOpcionRespuesta = $em->getRepository ( 'ichTestBundle:CopiaOpcionRespuesta' )->find ( $idOpcionSeleccionada );
				
				if (! $copiaOpcionRespuesta)
					throw $this->createNotFoundException ( 'Opción de Respuesta no encontrada.' );
				
				$copiaOpcionRespuesta->setSeleccionada(true);

				$em->persist ($copiaOpcionRespuesta );

			}

			$em->flush ();

			if($esUltimoBloque){

				if($this->finalizarCuestionario($idCuestionario))
					return $this->render ( 'ichTestBundle:Evaluacion:notificacion.html.twig', array (
						'mensaje' => "Cuestionario finalizado con éxito." 
					) );
			}

			else
				return $this->redirectToRoute ( 'ich_evaluacion_recuperarUltimoBloqueCuestionario', array (
				'idCuestionario' => $idCuestionario 
				) );
		}

		else{

			$preguntasNoRespondidas = $this->getPreguntasNoRespondidas($datosBloque);

			if($esUltimoBloque)
				return $this->render ( 'ichTestBundle:Evaluacion:completarUltimoBloqueCuestionario.html.twig', array (
				'form' => $form->createView (), 'preguntasNoRespondidas' => $preguntasNoRespondidas 
				) );
			else
			return $this->render ( 'ichTestBundle:Evaluacion:completarCuestionario.html.twig', array (
				'form' => $form->createView (), 'preguntasNoRespondidas' => $preguntasNoRespondidas
			) );
		} 

		}

	}


	private function finalizarCuestionario($idCuestionario){

		$em = $this->getDoctrine ()->getManager ();

		$cuestionario = $em->getRepository ( 'ichTestBundle:Cuestionario' )->find ( $idCuestionario );
		
		if (! $cuestionario) {
			throw $this->createNotFoundException ( 'Cuestionario no encontrado.' );
		}


		// OBTENER NRO DE BLOQUE A RESPONDER Y TODAS LAS PREGUNTAS NO RESPONDIDAS

		$puntajeMaximoCuestionario = 0;

		$puntajeCuestionario = 0;

		foreach ( $cuestionario->getCopiaCompetencias () as $copiaCompetencia ) {
			
			$puntajeCopiaCompetencia = 0;

			$cantCopiasFactor = 0;

			$puntajeCopiasFactor = 0;

			foreach ( $copiaCompetencia->getCopiaFactores () as $copiaFactor ) {
				
				$puntajeCopiaFactor = 0;

				$puntajeCopiasPregunta = 0;

				foreach ( $copiaFactor->getCopiaPreguntas () as $copiaPregunta ) {
					
					foreach ( $copiaPregunta->getCopiaOpcionesRespuesta () as $copiaOpcionRespuesta ) {
						
						if ($copiaOpcionRespuesta->getSeleccionada () != NULL && $copiaOpcionRespuesta->getSeleccionada () == true)
							$puntajeCopiasPregunta = $copiaOpcionRespuesta->getPonderacion() + $puntajeCopiasPregunta;
					}
					
				}
					$cantCopiasFactor++;

					$puntajeCopiaFactor = $puntajeCopiasPregunta / 2.00;

					$puntajeCopiasFactor = $puntajeCopiaFactor + $puntajeCopiasFactor;

					$copiaFactor->setPuntajeObtenido($puntajeCopiaFactor);

					$em->persist($copiaFactor);
			}

			$puntajeCopiaCompetencia = $puntajeCopiasFactor / $cantCopiasFactor;

			$copiaCompetencia->setPuntajeObtenido($puntajeCopiaCompetencia);

			$em->persist($copiaCompetencia);

			$puntajeMaximoCuestionario = $copiaCompetencia->getPonderacion() + $puntajeMaximoCuestionario;

			$puntajeCuestionario = (($copiaCompetencia->getPonderacion() * $puntajeCopiaCompetencia)/10) + $puntajeCuestionario;
		
		}

		$cuestionario->setPuntajeTotal(($puntajeCuestionario*100)/$puntajeMaximoCuestionario);

		$cuestionario->setEstado ( 1 );
			
		$cuestionario->setEstadoEn ( new \DateTime () );

		$em->persist($cuestionario);

		$em->flush();

		return true;

	}



	private function bloqueFueRespondido($datosBloque){

		foreach($datosBloque['copiaPreguntas'] as $copiaPregunta){

			if(NULL == $copiaPregunta['copiaOpcionesRespuesta'])
				return false;
		}

		return true;
		}



	private function getPreguntasNoRespondidas($datosBloque){

		$preguntasNoRespondidas = array();

		for($i = 0, $totalPreguntas = count ( $datosBloque['copiaPreguntas'] ); $i < $totalPreguntas; $i ++) {

			if(NULL == $datosBloque['copiaPreguntas'][$i]['copiaOpcionesRespuesta'])
				$preguntasNoRespondidas[] = $i+1;
		}

		return $preguntasNoRespondidas;
		}


	public function registrarComienzoCuestionarioAction($idCuestionario){

		$em = $this->getDoctrine ()->getManager ();

		$cuestionario = $em->getRepository ( 'ichTestBundle:Cuestionario' )->find ( $idCuestionario );
		
		if (! $cuestionario) {
			throw $this->createNotFoundException ( 'Cuestionario no encontrado.' );
		}


		$cuestionario->setComienzoEn ( new \DateTime () );

		$cuestionario->setEstado ( 0 );
			
		$cuestionario->setEstadoEn ( new \DateTime () );
			
		$em->persist ( $cuestionario );
			
		$em->flush ();
			
		return $this->redirectToRoute ( 'ich_evaluacion_verificarEstadoCuestionario', array (
			'id' => $idCuestionario, 'esUltimoBloque' => 0
			) );

	}
}