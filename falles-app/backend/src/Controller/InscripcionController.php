<?php

namespace App\Controller;

use App\Entity\Inscripcion;
use App\Entity\Usuario;
use App\Repository\InscripcionRepository;
use App\Service\InscripcionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class InscripcionController extends AbstractController
{
    public function __construct(
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly InscripcionService $inscripcionService
    ) {}

    /**
     * List user's inscriptions.
     */
    #[Route('/inscripciones/mias', name: 'api_inscripciones_mias', methods: ['GET'])]
    public function mias(): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        
        $inscripciones = $this->inscripcionRepository->findByUsuario($user);

        $data = array_map(fn(Inscripcion $inscripcion) => [
            'id' => $inscripcion->getId(),
            'codigo' => $inscripcion->getCodigo(),
            'evento' => [
                'id' => $inscripcion->getEvento()->getId(),
                'titulo' => $inscripcion->getEvento()->getTitulo(),
                'fechaEvento' => $inscripcion->getEvento()->getFechaEvento()->format('Y-m-d'),
                'lugar' => $inscripcion->getEvento()->getLugar(),
            ],
            'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
            'estadoPago' => $inscripcion->getEstadoPago()->value,
            'importeTotal' => $inscripcion->getImporteTotal(),
            'importePagado' => $inscripcion->getImportePagado(),
            'metodoPago' => $inscripcion->getMetodoPago()?->value,
            'fechaPago' => $inscripcion->getFechaPago()?->format('c'),
            'createdAt' => $inscripcion->getCreatedAt()->format('c'),
        ], $inscripciones);

        return $this->json(['hydra:member' => $data]);
    }

    /**
     * Get inscription detail.
     */
    #[Route('/inscripciones/{id}', name: 'api_inscripciones_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        
        $inscripcion = $this->inscripcionRepository->find($id);

        if (!$inscripcion) {
            return $this->json(['error' => 'Inscripción no encontrada'], 404);
        }

        // Verify ownership
        if ($inscripcion->getUsuario()->getId() !== $user->getId() 
            && !in_array('ROLE_ADMIN_ENTIDAD', $user->getRoles())
            && !in_array('ROLE_SUPERADMIN', $user->getRoles())) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        return $this->json([
            'id' => $inscripcion->getId(),
            'codigo' => $inscripcion->getCodigo(),
            'evento' => [
                'id' => $inscripcion->getEvento()->getId(),
                'titulo' => $inscripcion->getEvento()->getTitulo(),
                'fechaEvento' => $inscripcion->getEvento()->getFechaEvento()->format('Y-m-d'),
                'horaInicio' => $inscripcion->getEvento()->getHoraInicio()?->format('H:i'),
                'lugar' => $inscripcion->getEvento()->getLugar(),
            ],
            'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
            'estadoPago' => $inscripcion->getEstadoPago()->value,
            'importeTotal' => $inscripcion->getImporteTotal(),
            'importePagado' => $inscripcion->getImportePagado(),
            'metodoPago' => $inscripcion->getMetodoPago()?->value,
            'referenciaPago' => $inscripcion->getReferenciaPago(),
            'fechaPago' => $inscripcion->getFechaPago()?->format('c'),
            'observaciones' => $inscripcion->getObservaciones(),
            'createdAt' => $inscripcion->getCreatedAt()->format('c'),
            'lineas' => array_map(fn($linea) => [
                'id' => $linea->getId(),
                'nombrePersonaSnapshot' => $linea->getNombrePersonaSnapshot(),
                'tipoPersonaSnapshot' => $linea->getTipoPersonaSnapshot(),
                'franjaComidaSnapshot' => $linea->getFranjaComidaSnapshot(),
                'tipoRelacionEconomicaSnapshot' => $linea->getTipoRelacionEconomicaSnapshot(),
                'nombreMenuSnapshot' => $linea->getNombreMenuSnapshot(),
                'esDePagoSnapshot' => $linea->isEsDePagoSnapshot(),
                'precioUnitario' => $linea->getPrecioUnitario(),
                'estadoLinea' => $linea->getEstadoLinea()->value,
                'observaciones' => $linea->getObservaciones(),
            ], $inscripcion->getLineas()->toArray()),
        ]);
    }

    /**
     * Cancel inscription.
     */
    #[Route('/inscripciones/{id}/cancelar', name: 'api_inscripciones_cancelar', methods: ['POST'])]
    public function cancelar(int $id): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        
        $inscripcion = $this->inscripcionRepository->find($id);

        if (!$inscripcion) {
            return $this->json(['error' => 'Inscripción no encontrada'], 404);
        }

        // Verify ownership
        if ($inscripcion->getUsuario()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Solo puedes cancelar tus propias inscripciones'], 403);
        }

        // Can't cancel if already cancelled
        if ($inscripcion->getEstadoInscripcion()->value === 'cancelada') {
            return $this->json(['error' => 'La inscripción ya está cancelada'], 400);
        }

        $this->inscripcionService->cancelarInscripcion($inscripcion);

        return $this->json([
            'id' => $inscripcion->getId(),
            'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
            'mensaje' => 'Inscripción cancelada correctamente',
        ]);
    }
}
