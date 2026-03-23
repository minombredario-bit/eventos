<?php

namespace App\Service;

use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Evento;
use App\Entity\PersonaFamiliar;
use App\Entity\Usuario;
use App\Entity\MenuEvento;
use App\Repository\InscripcionRepository;
use App\Repository\EventoRepository;
use App\Repository\PersonaFamiliarRepository;
use App\Repository\MenuEventoRepository;
use App\Repository\UsuarioRepository;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InscripcionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PriceCalculatorService $priceCalculator,
        private InscripcionRepository $inscripcionRepository,
        private EventoRepository $eventoRepository,
        private PersonaFamiliarRepository $personaFamiliarRepository,
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
     * - Cada persona solo puede inscribirse una vez al evento
     * - Los menús deben pertenecer al evento y estar activos
     */
    public function crearInscripcion(
        string $eventoId,
        string $usuarioId,
        array $lineasData,
    ): Inscripcion {
        // 1. Verificar que el evento existe y está abierto
        $evento = $this->eventoRepository->find($eventoId);
        if (!$evento) {
            throw new BadRequestHttpException('Evento no encontrado');
        }

        if (!$evento->isPublicado()) {
            throw new BadRequestHttpException('El evento no está publicado');
        }

        if (!$evento->estaInscripcionAbierta()) {
            throw new BadRequestHttpException('Las inscripciones no están abiertas');
        }

        // 2. Verificar que no existe ya una inscripción para este usuario-evento
        $inscripcionExistente = $this->inscripcionRepository->findOneByUsuarioAndEvento($usuarioId, $eventoId);
        if ($inscripcionExistente) {
            throw new BadRequestHttpException('Ya tienes una inscripción para este evento');
        }

        // 3. Obtener el usuario
        $usuario = $this->usuarioRepository->find($usuarioId);
        if (!$usuario) {
            throw new BadRequestHttpException('Usuario no encontrado');
        }

        // 4. Crear la inscripción
        $inscripcion = new Inscripcion();
        $inscripcion->setEvento($evento);
        $inscripcion->setEntidad($evento->getEntidad());
        $inscripcion->setUsuario($usuario);
        
        // Generar código único
        $codigo = $this->generarCodigo($evento);
        $inscripcion->setCodigo($codigo);

        // 5. Procesar las líneas
        foreach ($lineasData as $lineaData) {
            $personaId = $lineaData['persona_id'] ?? null;
            $menuId = $lineaData['menu_id'] ?? null;
            $observaciones = $lineaData['observaciones'] ?? null;

            if (!$personaId || !$menuId) {
                throw new BadRequestHttpException('Se requiere persona y menú');
            }

            $persona = $this->personaFamiliarRepository->find($personaId);
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

            // Verificar que la persona no está ya inscrita en este evento
            $this->verificarPersonaNoDuplicada($usuarioId, $eventoId, $personaId);

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

        // 6. Calcular totales
        $importeTotal = $this->priceCalculator->calculateTotal(
            $inscripcion->getLineas()->toArray()
        );
        $inscripcion->setImporteTotal($importeTotal);
        $inscripcion->actualizarEstadoPago();

        // 7. Guardar
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
    private function verificarPersonaNoDuplicada(string $usuarioId, string $eventoId, string $personaId): void
    {
        // Buscar si la persona ya está en alguna línea de este evento
        $existe = $this->inscripcionRepository->personaYaInscrita($usuarioId, $eventoId, $personaId);
        if ($existe) {
            throw new BadRequestHttpException(
                'Esta persona ya está inscrita en el evento'
            );
        }
    }

    /**
     * Cancela una inscripción.
     */
    public function cancelarInscripcion(Inscripcion $inscripcion): void
    {
        $inscripcion->setEstadoInscripcion(EstadoInscripcionEnum::CANCELADA);
        $this->entityManager->flush();
    }
}
