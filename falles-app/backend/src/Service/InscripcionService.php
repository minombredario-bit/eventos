<?php

namespace App\Service;

use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Evento;
use App\Entity\Usuario;
use App\Repository\InscripcionRepository;
use App\Repository\EventoRepository;
use App\Repository\RelacionUsuarioRepository;
use App\Repository\MenuEventoRepository;
use App\Repository\UsuarioRepository;
use App\Enum\FranjaComidaEnum;
use App\Enum\EstadoLineaInscripcionEnum;
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
        private InscripcionRepository $inscripcionRepository,
        private EventoRepository $eventoRepository,
        private RelacionUsuarioRepository $relacionUsuarioRepository,
        private MenuEventoRepository $menuEventoRepository,
        private UsuarioRepository $usuarioRepository,
    ) {}

    /**
     * Crea una inscripción para un usuario en un evento.
     * 
     * Validaciones (según REQUIREMENTS.md):
     * - El evento debe existir y estar publicado
     * - Las inscripciones deben estar abiertas
     * - UN USUARIO NO PUEDE TENER YA UNA INSCRIPCIÓN PARA ESTE EVENTO
     * - Cada persona solo puede tener una selección por franja de comida
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

        // 2. Verificar que no existe ya una inscripción para este usuario-evento
        $usuario = $this->resolveUsuario($usuarioInput);
        if ($usuario === null) {
            throw new BadRequestHttpException('Usuario no encontrado');
        }

        $inscripcionExistente = $this->inscripcionRepository->findOneByUsuarioAndEvento($usuario->getId(), $evento->getId());
        if ($inscripcionExistente) {
            throw new BadRequestHttpException('Ya tienes una inscripción para este evento');
        }

        // 3. Crear la inscripción
        $inscripcion = new Inscripcion();
        $inscripcion->setEvento($evento);
        $inscripcion->setEntidad($evento->getEntidad());
        $inscripcion->setUsuario($usuario);
        
        // Generar código único
        $codigo = $this->generarCodigo($evento);
        $inscripcion->setCodigo($codigo);

        // 4. Procesar las líneas
        $lineasRegistradasPorPersonaYFranja = [];

        foreach ($lineasData as $lineaData) {
            $personaId = $this->extractResourceId($lineaData['persona_id'] ?? $lineaData['persona'] ?? null);
            $menuId = $this->extractResourceId($lineaData['menu_id'] ?? $lineaData['menu'] ?? null);
            $observaciones = $lineaData['observaciones'] ?? null;

            if (!$personaId || !$menuId) {
                throw new BadRequestHttpException('Se requiere persona y menú');
            }

            $persona = $this->relacionUsuarioRepository->findRelacionadosByUsuario($personaId);
            $menu = $this->menuEventoRepository->find($menuId);

            if (!$persona) {
                throw new BadRequestHttpException('Persona no encontrada');
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

            if ($persona->getUsuarioPrincipal()->getId() !== $usuario->getId()) {
                throw new BadRequestHttpException('La persona seleccionada no pertenece al usuario autenticado');
            }

            if (!$menu->esCompatibleConTipoPersona($persona->getTipoPersona())) {
                throw new BadRequestHttpException('El menú seleccionado no es compatible con el tipo de persona');
            }

            $franjaComida = $menu->getFranjaComida();
            $claveLinea = sprintf('%s|%s', $personaId, $franjaComida->value);
            if (isset($lineasRegistradasPorPersonaYFranja[$claveLinea])) {
                throw new BadRequestHttpException('No puedes seleccionar más de un menú por persona en la misma franja');
            }
            $lineasRegistradasPorPersonaYFranja[$claveLinea] = true;

            // Verificar que la persona no está ya inscrita en este evento para la misma franja
            $this->verificarPersonaNoDuplicadaEnFranja($usuario->getId(), $evento->getId(), $personaId, $franjaComida);

            // Crear la línea
            $linea = new InscripcionLinea();
            $linea->setInscripcion($inscripcion);
            $linea->setPersona($persona);
            $linea->setMenu($menu);
            $linea->setObservaciones($observaciones);

            // Calcular precio usando el servicio
            $precio = $this->priceCalculator->calculatePrice($persona, $menu);
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
        $this->entityManager->persist($inscripcion);
        $this->entityManager->flush();

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
     * Verifica que una persona no esté duplicada en el mismo evento.
     */
    private function verificarPersonaNoDuplicadaEnFranja(
        string $usuarioId,
        string $eventoId,
        string $personaId,
        FranjaComidaEnum $franjaComida,
    ): void
    {
        $existe = $this->inscripcionRepository->personaYaInscritaEnFranja(
            $usuarioId,
            $eventoId,
            $personaId,
            $franjaComida,
        );

        if ($existe) {
            throw new BadRequestHttpException(
                sprintf('Esta persona ya está inscrita en la franja %s para este evento', $franjaComida->label())
            );
        }
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

        if ($inscripcion->getImportePagado() > 0.0) {
            throw new BadRequestHttpException('No puedes cancelar líneas de una inscripción con pagos registrados');
        }

        if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
            throw new BadRequestHttpException('La línea ya está cancelada');
        }

        $linea->setEstadoLinea(EstadoLineaInscripcionEnum::CANCELADA);

        $inscripcion->setImporteTotal($inscripcion->calcularImporteTotal());
        $inscripcion->actualizarEstadoPago();

        $this->entityManager->flush();
    }
}
