<?php

namespace App\Service;

use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Evento;
use App\Entity\Invitado;
use App\Entity\MenuEvento;
use App\Entity\Usuario;
use App\Repository\InscripcionRepository;
use App\Repository\EventoRepository;
use App\Repository\InvitadoRepository;
use App\Repository\RelacionUsuarioRepository;
use App\Repository\MenuEventoRepository;
use App\Repository\UsuarioRepository;
use App\Enum\FranjaComidaEnum;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\TipoMenuEnum;
use App\Enum\TipoPersonaEnum;
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
        private MenuEventoRepository $menuEventoRepository,
        private InvitadoRepository $invitadoRepository,
        private UsuarioRepository $usuarioRepository,
    ) {}

    /**
     * Crea o amplía una inscripción para un usuario en un evento.
     *
     * Validaciones (según REQUIREMENTS.md):
     * - El evento debe existir y estar publicado
     * - Las inscripciones deben estar abiertas
     * - Si ya existe una inscripción activa del usuario para el evento, se reutiliza
     * - Cada participante solo puede tener una selección por franja de comida
     * - Los menús deben pertenecer al evento y estar activos
     */
    public function crearInscripcion(
        string|Evento $eventoInput,
        string|Usuario $usuarioInput,
        array $lineasData,
    ): Inscripcion {
        if ($lineasData === []) {
            throw new BadRequestHttpException('Se requiere al menos una línea de inscripción');
        }

        // 1. Verificar que el evento existe y está abierto
        $evento = $this->resolveEvento($eventoInput);
        if ($evento === null) {
            throw new BadRequestHttpException('Evento no encontrado');
        }

        if (!$evento->isPublicado()) {
            throw new BadRequestHttpException('El evento no está publicado');
        }

        if (!$evento->estaInscripcionAbierta()) {
            throw new UnprocessableEntityHttpException(self::ERROR_MESSAGE_INSCRIPCION_CERRADA);
        }

        // 2. Resolver usuario e inscripción activa para este usuario-evento
        $usuario = $this->resolveUsuario($usuarioInput);
        if ($usuario === null) {
            throw new BadRequestHttpException('Usuario no encontrado');
        }

        $inscripcionExistente = $this->inscripcionRepository->findOneByUsuarioAndEvento($usuario->getId(), $evento->getId());
        $inscripcion = $inscripcionExistente;

        // 3. Crear la inscripción solo si no existía
        if ($inscripcion === null) {
            $inscripcion = new Inscripcion();
            $inscripcion->setEvento($evento);
            $inscripcion->setEntidad($evento->getEntidad());
            $inscripcion->setUsuario($usuario);

            // Generar código único
            $codigo = $this->generarCodigo($evento);
            $inscripcion->setCodigo($codigo);
            $this->entityManager->persist($inscripcion);
        }

        // 4. Procesar las líneas
        $lineasRegistradasPorParticipanteYFranja = [];

        foreach ($lineasData as $lineaData) {
            $usuarioReference = $lineaData['usuario_id']
                ?? $lineaData['usuario']
                ?? $lineaData['persona']
                ?? null;
            $participanteId = $this->extractResourceId($usuarioReference);
            $menuId = $this->extractResourceId($lineaData['menu_id'] ?? $lineaData['menu'] ?? null);
            $observaciones = $lineaData['observaciones'] ?? null;

            if (!$participanteId || !$menuId) {
                throw new BadRequestHttpException('Se requiere usuario/invitado y menú');
            }

            $isInvitado = $this->isInvitadoReference($usuarioReference);
            $usuarioParticipante = null;
            $invitado = null;

            if ($isInvitado) {
                $invitado = $this->invitadoRepository->findActiveByIdAndEventoAndHouseholdUsuario($participanteId, $evento, $usuario);
            } else {
                $usuarioParticipante = $participanteId === $usuario->getId()
                    ? $usuario
                    : $this->relacionUsuarioRepository->findRelacionadoByUsuarioYRelacionadoId($usuario, $participanteId);
            }

            $menu = $this->menuEventoRepository->find($menuId);

            if ($isInvitado && !$invitado) {
                throw new BadRequestHttpException('Invitado no encontrado');
            }

            if (!$isInvitado && !$usuarioParticipante) {
                throw new BadRequestHttpException('Usuario no encontrado o no vinculado a tu cuenta');
            }

            if (!$menu) {
                throw new BadRequestHttpException('Menú no encontrado');
            }

            // Verificar que el menú pertenece al evento
            if ($menu->getEvento()->getId() !== $evento->getId()) {
                throw new BadRequestHttpException('El menú no pertenece a este evento');
            }

            // Verificar que el menú está activo
            if (!$menu->isActivo()) {
                throw new BadRequestHttpException('El menú seleccionado no está activo');
            }

            $tipoPersonaParticipante = $invitado?->getTipoPersona() ?? TipoPersonaEnum::ADULTO;

            if (!$menu->esCompatibleConTipoPersona($tipoPersonaParticipante)) {
                throw new BadRequestHttpException('El menú seleccionado no es compatible con el tipo de persona');
            }

            $franjaComida = $menu->getFranjaComida();
            $origenParticipante = $isInvitado ? 'invitado' : 'usuario';
            $claveLinea = sprintf('%s|%s|%s', $origenParticipante, $participanteId, $franjaComida->value);
            if (isset($lineasRegistradasPorParticipanteYFranja[$claveLinea])) {
                throw new BadRequestHttpException('No puedes seleccionar más de un menú por participante en la misma franja');
            }
            $lineasRegistradasPorParticipanteYFranja[$claveLinea] = true;

            // Verificar que el participante no está ya inscrito en este evento para la misma franja
            $this->verificarParticipanteNoDuplicadoEnFranja(
                $usuario->getId(),
                $evento->getId(),
                $participanteId,
                $franjaComida,
                $isInvitado,
            );

            // Crear la línea
            $linea = new InscripcionLinea();
            $linea->setInscripcion($inscripcion);
            if ($isInvitado) {
                $linea->setInvitado($invitado);
            } else {
                $linea->setUsuario($usuarioParticipante);
            }
            $linea->setMenu($menu);
            $linea->setObservaciones($observaciones);
            $linea->setPagada(false);

            // Calcular precio usando el servicio
            $precio = $isInvitado
                ? $this->calculatePriceForInvitado($invitado, $menu)
                : $this->priceCalculator->calculatePrice($usuarioParticipante, $menu);
            $linea->setPrecioUnitario($precio);

            // Crear snapshot de datos
            $linea->crearSnapshot();

            $inscripcion->addLinea($linea);
        }

        // 5. Calcular totales
        $importeTotal = $this->priceCalculator->calculateTotal(
            $inscripcion->getLineas()->toArray()
        );
        $inscripcion->setImporteTotal($importeTotal);
        $inscripcion->actualizarEstadoPago();

        // 6. Guardar
        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'apuntado');

        return $inscripcion;
    }

    /**
     * Genera un código único para la inscripción.
     */
    private function generarCodigo(Evento $evento): string
    {
        $prefix = strtoupper(substr($evento->getEntidad()->getSlug(), 0, 3));
        $year = date('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        return "{$prefix}-{$year}-{$random}";
    }

    /**
     * Verifica que un participante no esté duplicado en el mismo evento y franja.
     */
    private function verificarParticipanteNoDuplicadoEnFranja(
        string $usuarioId,
        string $eventoId,
        string $participanteId,
        FranjaComidaEnum $franjaComida,
        bool $isInvitado,
    ): void
    {
        $existe = $isInvitado
            ? $this->inscripcionRepository->invitadoYaInscritoEnFranja($usuarioId, $eventoId, $participanteId, $franjaComida)
            : $this->inscripcionRepository->usuarioYaInscritoEnFranja($usuarioId, $eventoId, $participanteId, $franjaComida);

        if ($existe) {
            throw new BadRequestHttpException(
                sprintf(
                    $isInvitado
                        ? 'Este invitado ya está inscrito en la franja %s para este evento'
                        : 'Este usuario ya está inscrito en la franja %s para este evento',
                    $franjaComida->label(),
                )
            );
        }
    }

    private function isInvitadoReference(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return str_starts_with(trim($value), '/api/invitados/');
    }

    private function calculatePriceForInvitado(Invitado $invitado, MenuEvento $menu): float
    {
        if (!$menu->isEsDePago()) {
            return 0.0;
        }

        if ($menu->getTipoMenu() === TipoMenuEnum::INFANTIL) {
            return (float) ($menu->getPrecioInfantil() ?? $menu->getPrecioBase());
        }

        return (float) ($menu->getPrecioAdultoExterno() ?? $menu->getPrecioBase());
    }

    private function resolveEvento(string|Evento $eventoInput): ?Evento
    {
        if ($eventoInput instanceof Evento) {
            return $eventoInput;
        }

        return $this->eventoRepository->find($eventoInput);
    }

    private function resolveUsuario(string|Usuario $usuarioInput): ?Usuario
    {
        if ($usuarioInput instanceof Usuario) {
            return $usuarioInput;
        }

        return $this->usuarioRepository->find($usuarioInput);
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

    /**
     * Cancela una inscripción.
     */
    public function cancelarInscripcion(Inscripcion $inscripcion): void
    {
        if (!$inscripcion->getEvento()->estaInscripcionAbierta()) {
            throw new UnprocessableEntityHttpException(self::ERROR_MESSAGE_INSCRIPCION_CERRADA);
        }

        $inscripcion->setEstadoInscripcion(EstadoInscripcionEnum::CANCELADA);
        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'borrado');
    }

    /**
     * Cancela una línea de inscripción aplicando reglas de negocio de usuario final.
     */
    public function cancelarLineaInscripcion(Inscripcion $inscripcion, InscripcionLinea $linea): void
    {
        if ($linea->getInscripcion()->getId() !== $inscripcion->getId()) {
            throw new BadRequestHttpException('La línea no pertenece a la inscripción indicada');
        }

        if (!$inscripcion->getEvento()->estaInscripcionAbierta()) {
            throw new UnprocessableEntityHttpException(self::ERROR_MESSAGE_INSCRIPCION_CERRADA);
        }

        if ($linea->isPagada()) {
            throw new BadRequestHttpException('No puedes cancelar una línea ya pagada');
        }

        if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
            throw new BadRequestHttpException('La línea ya está cancelada');
        }

        $linea->setEstadoLinea(EstadoLineaInscripcionEnum::CANCELADA);

        $inscripcion->setImporteTotal($inscripcion->calcularImporteTotal());
        $inscripcion->actualizarEstadoPago();

        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'actualizado');
    }
}
