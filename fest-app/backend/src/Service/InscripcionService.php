<?php

namespace App\Service;

use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Evento;
use App\Entity\Invitado;
use App\Entity\ActividadEvento;
use App\Entity\Usuario;
use App\Enum\EstadoEventoEnum;
use App\Repository\InscripcionRepository;
use App\Repository\EventoRepository;
use App\Repository\InvitadoRepository;
use App\Repository\RelacionUsuarioRepository;
use App\Repository\ActividadEventoRepository;
use App\Repository\UsuarioRepository;
use App\Enum\FranjaComidaEnum;
use App\Enum\EstadoInscripcionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InscripcionService
{
    public const ERROR_CODE_INSCRIPCION_CERRADA = 'INSCRIPCION_CERRADA_POR_CADUCIDAD';
    public const ERROR_MESSAGE_INSCRIPCION_CERRADA = 'La inscripción está cerrada por caducidad de la fecha límite';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PriceCalculatorService $priceCalculator,
        private EmailQueueService $emailQueueService,
        private InscripcionRepository $inscripcionRepository,
        private EventoRepository $eventoRepository,
        private RelacionUsuarioRepository $relacionUsuarioRepository,
        private ActividadEventoRepository $actividadEventoRepository,
        private InvitadoRepository $invitadoRepository,
        private UsuarioRepository $usuarioRepository,
    ) {}

    /**
     * Crea o amplía una inscripción para un usuario en un evento.
     */
    public function crearInscripcion(
        string|Evento $eventoInput,
        string|Usuario $usuarioInput,
        array $lineasData,
    ): Inscripcion {
        if ($lineasData === []) {
            throw new BadRequestHttpException('Se requiere al menos una línea de inscripción');
        }

        $evento = $this->resolveEvento($eventoInput);
        if ($evento === null) {
            throw new BadRequestHttpException('Evento no encontrado');
        }

        if ($evento->getEstado() !== EstadoEventoEnum::PUBLICADO) {
            throw new BadRequestHttpException('El evento no está publicado');
        }

        if (!$evento->estaInscripcionAbierta()) {
            throw new UnprocessableEntityHttpException(self::ERROR_MESSAGE_INSCRIPCION_CERRADA);
        }

        $usuario = $this->resolveUsuario($usuarioInput);
        if ($usuario === null) {
            throw new BadRequestHttpException('Usuario no encontrado');
        }

        $inscripcion = $this->inscripcionRepository->findOneByUsuarioAndEvento($usuario->getId(), $evento->getId());

        if ($inscripcion === null) {
            $inscripcion = new Inscripcion();
            $inscripcion->setEvento($evento);
            $inscripcion->setEntidad($evento->getEntidad());
            $inscripcion->setUsuario($usuario);
            $inscripcion->setCodigo($this->generarCodigo($evento));
            $this->entityManager->persist($inscripcion);
        }

        $lineasRegistradasPorParticipanteYFranja = [];

        foreach ($lineasData as $lineaData) {
            $usuarioReference = $lineaData['usuario_id']
                ?? $lineaData['usuario']
                ?? $lineaData['persona']
                ?? null;

            $participanteId = $this->extractResourceId($usuarioReference);
            $actividadId    = $this->extractResourceId($lineaData['actividad'] ?? null);
            $observaciones  = $lineaData['observaciones'] ?? null;

            if (!$participanteId || !$actividadId) {
                throw new BadRequestHttpException('Se requiere usuario/invitado y actividad');
            }

            $isInvitado = $this->isInvitadoReference($usuarioReference);

            if ($isInvitado && !$evento->isPermiteInvitados()) {
                throw new BadRequestHttpException('Este evento no permite invitados');
            }

            [$usuarioParticipante, $invitado] = $this->resolveParticipante(
                $participanteId, $isInvitado, $evento, $usuario,
            );

            $actividad = $this->actividadEventoRepository->find($actividadId);

            if (!$actividad) {
                throw new BadRequestHttpException('Actividad no encontrada');
            }

            if ($actividad->getEvento()->getId() !== $evento->getId()) {
                throw new BadRequestHttpException('La actividad no pertenece a este evento');
            }

            if (!$actividad->isActivo()) {
                throw new BadRequestHttpException('La actividad seleccionada no está activa');
            }

            if ($isInvitado && !$actividad->isPermiteInvitados()) {
                throw new BadRequestHttpException('La actividad seleccionada no permite invitados');
            }

            $tipoPersona = $this->resolveParticipanteTipoPersona($invitado, $usuarioParticipante);

            if (!$actividad->esCompatibleConTipoPersona($tipoPersona)) {
                throw new BadRequestHttpException('La actividad seleccionada no es compatible con el tipo de persona');
            }

            $franjaComida       = $actividad->getFranjaComida();
            $origenParticipante = $isInvitado ? 'invitado' : 'usuario';
            $claveLinea         = sprintf('%s|%s|%s', $origenParticipante, $participanteId, $franjaComida->value);

            if (isset($lineasRegistradasPorParticipanteYFranja[$claveLinea])) {
                throw new BadRequestHttpException('No puedes seleccionar más de una actividad por participante en la misma franja');
            }
            $lineasRegistradasPorParticipanteYFranja[$claveLinea] = true;

            $existingLine = $isInvitado
                ? $this->inscripcionRepository->findLineaActivaInvitadoEnFranja($usuario->getId(), $evento->getId(), $participanteId, $franjaComida)
                : $this->inscripcionRepository->findLineaActivaUsuarioEnFranja($usuario->getId(), $evento->getId(), $participanteId, $franjaComida);

            if ($existingLine) {
                if ($existingLine->isPagada()) {
                    throw new BadRequestHttpException('No puedes modificar una línea ya pagada');
                }

                $existingLine->setActividad($actividad);
                $existingLine->setPrecioUnitario(
                    $this->calcularPrecioParticipante($tipoPersona->value, $isInvitado, $actividad),
                );
                $existingLine->setObservaciones($observaciones);
                $existingLine->crearSnapshot();

                $inscripcion = $existingLine->getInscripcion();
                continue;
            }

            $linea = new InscripcionLinea();
            $linea->setInscripcion($inscripcion);
            $linea->setActividad($actividad);
            $linea->setObservaciones($observaciones);
            $linea->setPagada(false);
            $linea->setPrecioUnitario(
                $this->calcularPrecioParticipante($tipoPersona->value, $isInvitado, $actividad),
            );

            if ($isInvitado) {
                $linea->setInvitado($invitado);
            } else {
                $linea->setUsuario($usuarioParticipante);
            }

            $linea->crearSnapshot();
            $inscripcion->addLinea($linea);
        }

        $inscripcion->setImporteTotal(
            $this->priceCalculator->calculateTotal($inscripcion->getLineas()->toArray()),
        );
        $inscripcion->actualizarEstadoPago();
        $this->actualizarEstadoInscripcionSegunImporte($inscripcion);

        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'apuntado');

        return $inscripcion;
    }

    public function cancelarInscripcion(Inscripcion $inscripcion): void
    {
        if (!$inscripcion->getEvento()->estaInscripcionAbierta()) {
            throw new UnprocessableEntityHttpException(self::ERROR_MESSAGE_INSCRIPCION_CERRADA);
        }

        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'borrado');
    }

    public function cancelarLineaInscripcion(Inscripcion $inscripcion, InscripcionLinea $linea): void
    {
        if ($linea->getInscripcion()->getId() !== $inscripcion->getId()) {
            throw new BadRequestHttpException('La línea no pertenece a la inscripción indicada');
        }

        if (!$inscripcion->getEvento()->estaInscripcionAbierta()) {
            throw new UnprocessableEntityHttpException(self::ERROR_MESSAGE_INSCRIPCION_CERRADA);
        }

        if ($linea->isPagada()) {
            throw new BadRequestHttpException('No puedes eliminar una línea ya pagada');
        }

        $inscripcion->removeLinea($linea);
        $this->entityManager->remove($linea);

        $inscripcion->setImporteTotal($inscripcion->calcularImporteTotal());
        $inscripcion->actualizarEstadoPago();

        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'actualizado');
    }

    // ── Helpers privados ──────────────────────────────────────────────────

    /**
     * Espejo exacto de getActivityPrice() en TypeScript:
     *
     *   isAdult → isGuest ? precioAdultoExterno : precioAdultoInterno
     *   !isAdult → isGuest ? precioInfantilExterno : precioInfantil
     */
    private function calcularPrecioParticipante(
        string $tipoPersona,
        bool $isInvitado,
        ActividadEvento $actividad,
    ): float {
        if (!$actividad->isEsDePago()) {
            return 0.0;
        }

        $isAdult = $tipoPersona !== 'infantil';

        if ($isAdult) {
            return $isInvitado
                ? (float) ($actividad->getPrecioAdultoExterno()   ?? $actividad->getPrecioBase())
                : (float) ($actividad->getPrecioAdultoInterno()   ?? $actividad->getPrecioBase());
        }

        return $isInvitado
            ? (float) ($actividad->getPrecioInfantilExterno() ?? $actividad->getPrecioInfantil() ?? $actividad->getPrecioBase())
            : (float) ($actividad->getPrecioInfantil()         ?? $actividad->getPrecioBase());
    }

    /**
     * @return array{0: Usuario|null, 1: Invitado|null}
     */
    private function resolveParticipante(
        string $participanteId,
        bool $isInvitado,
        Evento $evento,
        Usuario $usuario,
    ): array {
        if ($isInvitado) {
            $invitado = $this->invitadoRepository->findActiveByIdAndEventoAndHouseholdUsuario(
                $participanteId, $evento, $usuario,
            );
            if (!$invitado) {
                throw new BadRequestHttpException('Invitado no encontrado');
            }
            return [null, $invitado];
        }

        $usuarioParticipante = $participanteId === $usuario->getId()
            ? $usuario
            : $this->relacionUsuarioRepository->findRelacionadoByUsuarioYRelacionadoId($usuario, $participanteId);

        if (!$usuarioParticipante) {
            throw new BadRequestHttpException('Usuario no encontrado o no vinculado a tu cuenta');
        }

        return [$usuarioParticipante, null];
    }

    private function resolveParticipanteTipoPersona(?Invitado $invitado, ?Usuario $usuario): mixed
    {
        return $invitado?->getTipoPersona() ?? $usuario->getTipoPersona();
    }

    private function generarCodigo(Evento $evento): string
    {
        $prefix = strtoupper(substr($evento->getEntidad()->getSlug(), 0, 3));
        $year   = date('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        return "{$prefix}-{$year}-{$random}";
    }

    private function actualizarEstadoInscripcionSegunImporte(Inscripcion $inscripcion): void
    {
        if (abs($inscripcion->getImporteTotal()) < 0.00001) {
            $inscripcion->setEstadoInscripcion(EstadoInscripcionEnum::CONFIRMADA);
        }
    }

    private function isInvitadoReference(mixed $value): bool
    {
        return is_string($value) && str_starts_with(trim($value), '/api/invitados/');
    }

    private function resolveEvento(string|Evento $input): ?Evento
    {
        return $input instanceof Evento ? $input : $this->eventoRepository->find($input);
    }

    private function resolveUsuario(string|Usuario $input): ?Usuario
    {
        return $input instanceof Usuario ? $input : $this->usuarioRepository->find($input);
    }

    private function extractResourceId(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (str_contains($value, '/')) {
            $parts = explode('/', trim($value, '/'));
            return end($parts) ?: null;
        }

        return $value;
    }
}
