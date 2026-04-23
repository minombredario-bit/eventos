<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\InscripcionLinea;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Enum\EstadoLineaInscripcionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SeleccionParticipantesEventoDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof SeleccionParticipanteEvento) {
            return null;
        }

        /** @var Usuario $user */
        $user = $this->security->getUser();
        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        // ── 1. Verificar que el usuario puede eliminar esta selección ─────
        if ($data->getInscritoPorUsuario()->getId() !== $user->getId()
            && $data->getEvento()->getEntidad()->getId() !== $user->getEntidad()->getId()
        ) {
            throw new AccessDeniedHttpException('No tienes permiso para eliminar esta selección.');
        }

        // ── 2. Buscar la Inscripcion que contiene líneas del participante ──
        $inscripcion = $this->resolveInscripcion($data);

        if ($inscripcion !== null) {
            // ── 3. Comprobar que ninguna línea activa del participante está pagada
            foreach ($inscripcion->getLineas() as $linea) {
                if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
                    continue;
                }

                if (!$this->lineaBelongsToSeleccion($linea, $data)) {
                    continue;
                }

                if ($linea->isPagada()) {
                    throw new ConflictHttpException(
                        'No se puede eliminar esta selección porque tiene líneas de inscripción pagadas.'
                    );
                }
            }

            // ── 4. Cancelar las líneas activas del participante ───────────
            foreach ($inscripcion->getLineas() as $linea) {
                if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
                    continue;
                }

                if (!$this->lineaBelongsToSeleccion($linea, $data)) {
                    continue;
                }

                $linea->setEstadoLinea(EstadoLineaInscripcionEnum::CANCELADA);
            }

            // ── 5. Recalcular totales de la inscripción padre ─────────────
            $inscripcion->setImporteTotal($inscripcion->calcularImporteTotal());
            $inscripcion->actualizarEstadoPago();
        }

        // ── 6. Eliminar la selección ──────────────────────────────────────
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }

    /**
     * Busca la Inscripcion del evento que tiene líneas para el participante
     * de esta selección.
     */
    private function resolveInscripcion(SeleccionParticipanteEvento $seleccion): ?\App\Entity\Inscripcion
    {
        foreach ($seleccion->getEvento()->getInscripciones() as $inscripcion) {
            foreach ($inscripcion->getLineas() as $linea) {
                if ($this->lineaBelongsToSeleccion($linea, $seleccion)) {
                    return $inscripcion;
                }
            }
        }

        return null;
    }

    /**
     * Comprueba si una InscripcionLinea pertenece al participante de la selección,
     * usando getUsuario() / getInvitado() que son los métodos reales de la entidad.
     */
    private function lineaBelongsToSeleccion(
        InscripcionLinea $linea,
        SeleccionParticipanteEvento $seleccion,
    ): bool {
        if ($seleccion->getUsuario() !== null) {
            return $linea->getUsuario()?->getId() === $seleccion->getUsuario()->getId();
        }

        if ($seleccion->getInvitado() !== null) {
            return $linea->getInvitado()?->getId() === $seleccion->getInvitado()->getId();
        }

        return false;
    }
}
