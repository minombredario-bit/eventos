<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ActividadEvento;
use App\Entity\Evento;
use App\Enum\CompatibilidadPersonaActividadEnum;
use App\Enum\FranjaComidaEnum;
use App\Enum\TipoActividadEnum;
use App\Repository\ActividadEventoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @implements ProcessorInterface<Evento, Evento>
 */
final class EventoWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface    $em,
        private readonly SluggerInterface          $slugger,
        private readonly ActividadEventoRepository $actividadRepo,
        private readonly ProcessorInterface        $persistProcessor,
        private readonly RequestStack              $requestStack,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Evento
    {
        if (!$data instanceof Evento) {
            throw new BadRequestHttpException('Expected an Evento instance.');
        }
        $user = $this->security->getUser();
        $data->setEntidad($user->getEntidad());
        $this->syncSlug($data, $operation);
        $this->syncActividades($data);

        /** @var Evento $saved */
        $saved = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        return $saved;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function syncSlug(Evento $evento, Operation $operation): void
    {
        if ($operation instanceof Post || !$evento->getSlug()) {
            $evento->setSlug(
                strtolower($this->slugger->slug($evento->getTitulo())->toString())
            );
            return;
        }

        $expectedSlug = strtolower($this->slugger->slug($evento->getTitulo())->toString());
        if ($evento->getSlug() !== $expectedSlug) {
            $evento->setSlug($expectedSlug);
        }
    }

    /**
     * Lee las actividades directamente del JSON del request.
     * API Platform NO deserializa la colección (actividades no está en evento:write),
     * por lo que Doctrine nunca ve entidades con UUIDs falsos.
     *
     * - Con @id  → cargar por UUID, actualizar campos si los envía.
     * - Sin @id  → nueva actividad, persistir.
     * - Ausente del payload → no se toca (no hay borrado silencioso).
     */
    private function syncActividades(Evento $evento): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $body = json_decode($request->getContent(), true);

        if (!isset($body['actividades']) || !is_array($body['actividades'])) {
            return;
        }

        foreach ($body['actividades'] as $data) {
            $iri = $data['id'] ?? null;

            if ($iri !== null) {
                // ── Actividad existente ──────────────────────────────────────
                $uuid = $this->uuidFromIri($iri);
                if ($uuid === null) {
                    throw new BadRequestHttpException(
                        sprintf('IRI inválida: "%s".', $iri)
                    );
                }

                $actividad = $this->actividadRepo->find($uuid);
                if ($actividad === null) {
                    throw new BadRequestHttpException(
                        sprintf('ActividadEvento "%s" no encontrada.', $iri)
                    );
                }

                $this->applyFields($data, $actividad);

            } else {
                // ── Nueva actividad ──────────────────────────────────────────
                $actividad = new ActividadEvento();
                $actividad->setEvento($evento);
                $this->applyFields($data, $actividad);
                // Si no se envió permiteInvitados en payload, heredar del evento
                if (!array_key_exists('permiteInvitados', $data)) {
                    $actividad->setPermiteInvitados($evento->isPermiteInvitados());
                }
                $this->em->persist($actividad);
                $evento->addActividad($actividad);
            }
        }
    }

    /**
     * Extrae el UUID del último segmento de una IRI.
     * "/api/actividad_eventos/0b662202-0df8-4b09-9dea-4e15a4847a9c" → "0b662202-..."
     */
    private function uuidFromIri(string $iri): ?string
    {
        $segment = basename($iri);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $segment)) {
            return $segment;
        }

        return null;
    }

    /**
     * Aplica solo los campos presentes en el payload sobre la entidad.
     * Usa array_key_exists para distinguir "no enviado" de "enviado como null".
     *
     * @param array<string, mixed> $data
     */
    private function applyFields(array $data, ActividadEvento $actividad): void
    {
        if (isset($data['nombre'])) {
            $actividad->setNombre($data['nombre']);
        }
        if (array_key_exists('descripcion', $data)) {
            $actividad->setDescripcion($data['descripcion']);
        }
        if (isset($data['tipoActividad'])) {
            $actividad->setTipoActividad(
                TipoActividadEnum::from($data['tipoActividad'])
            );
        }
        if (isset($data['franjaComida'])) {
            $actividad->setFranjaComida(
                FranjaComidaEnum::from($data['franjaComida'])
            );
        }
        if (isset($data['compatibilidadPersona'])) {
            $actividad->setCompatibilidadPersona(
                CompatibilidadPersonaActividadEnum::from($data['compatibilidadPersona'])
            );
        }
        if (array_key_exists('esDePago', $data)) {
            $actividad->setEsDePago((bool) $data['esDePago']);
        }
        if (array_key_exists('permiteInvitados', $data)) {
            $actividad->setPermiteInvitados((bool) $data['permiteInvitados']);
        }
        if (array_key_exists('precioBase', $data)) {
            $actividad->setPrecioBase((float) $data['precioBase']);
        }
        if (array_key_exists('precioAdultoInterno', $data)) {
            $actividad->setPrecioAdultoInterno(
                $data['precioAdultoInterno'] !== null ? (float) $data['precioAdultoInterno'] : null
            );
        }
        if (array_key_exists('precioAdultoExterno', $data)) {
            $actividad->setPrecioAdultoExterno(
                $data['precioAdultoExterno'] !== null ? (float) $data['precioAdultoExterno'] : null
            );
        }
        if (array_key_exists('precioInfantil', $data)) {
            $actividad->setPrecioInfantil(
                $data['precioInfantil'] !== null ? (float) $data['precioInfantil'] : null
            );
        }
        if (array_key_exists('unidadesMaximas', $data)) {
            $actividad->setUnidadesMaximas(
                $data['unidadesMaximas'] !== null ? (int) $data['unidadesMaximas'] : null
            );
        }
        if (array_key_exists('ordenVisualizacion', $data)) {
            $actividad->setOrdenVisualizacion((int) $data['ordenVisualizacion']);
        }
        if (array_key_exists('activo', $data)) {
            $actividad->setActivo((bool) $data['activo']);
        }
        if (array_key_exists('confirmacionAutomatica', $data)) {
            $actividad->setConfirmacionAutomatica((bool) $data['confirmacionAutomatica']);
        }
        if (array_key_exists('observacionesInternas', $data)) {
            $actividad->setObservacionesInternas($data['observacionesInternas']);
        }

        // Normalizar precios según reglas: si la actividad no es de pago, forzar a 0.
        if (!$actividad->isEsDePago()) {
            $actividad->setPrecioBase(0.0);
            $actividad->setPrecioAdultoInterno(0.0);
            $actividad->setPrecioAdultoExterno(0.0);
            $actividad->setPrecioInfantil(0.0);
            $actividad->setPrecioInfantilExterno(0.0);
        }

        // Si la actividad no permite invitados, forzar precios externos a 0
        if (method_exists($actividad, 'isPermiteInvitados') && !$actividad->isPermiteInvitados()) {
            $actividad->setPrecioAdultoExterno(0.0);
            $actividad->setPrecioInfantilExterno(0.0);
        }
    }
}
