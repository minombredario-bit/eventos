<?php

namespace App\Service;

use App\Entity\ColaCorreo;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\Usuario;
use App\Repository\ColaCorreoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class EmailQueueService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ColaCorreoRepository $colaCorreoRepository,
        private readonly Environment $twig,
    ) {}

    public function enqueue(
        string $destinatario,
        string $asunto,
        string $plantilla,
        array $contexto = [],
        ?Entidad $entidad = null,
        ?Usuario $usuario = null,
    ): ColaCorreo {
        $item = new ColaCorreo();
        $item->setDestinatario($destinatario);
        $item->setAsunto($asunto);
        $item->setPlantilla($plantilla);
        $item->setContexto($contexto);
        $item->setEntidad($entidad);
        $item->setUsuario($usuario);

        $this->entityManager->persist($item);

        return $item;
    }

    public function enqueueUserWelcome(Usuario $usuario, string $plainPassword, string $appUri): void
    {
        $this->enqueue(
            $usuario->getEmail(),
            'Alta de usuario en la aplicación',
            'email/user_welcome.html.twig',
            [
                'nombre' => $usuario->getNombre(),
                'email' => $usuario->getEmail(),
                'password' => $plainPassword,
                'appUri' => $appUri,
            ],
            $usuario->getEntidad(),
            $usuario,
        );
    }

    public function enqueueEventoCreado(Evento $evento): void
    {
        foreach ($evento->getEntidad()->getUsuarios() as $usuario) {
            if (!$usuario->isActivo()) {
                continue;
            }

            $this->enqueue(
                $usuario->getEmail(),
                'Nuevo evento disponible: ' . $evento->getTitulo(),
                'email/evento_creado.html.twig',
                [
                    'nombre' => $usuario->getNombre(),
                    'titulo' => $evento->getTitulo(),
                    'fechaEvento' => $evento->getFechaEvento()->format('Y-m-d'),
                    'descripcion' => $evento->getDescripcion(),
                ],
                $evento->getEntidad(),
                $usuario,
            );
        }
    }

    public function enqueueInscripcionCambio(Inscripcion $inscripcion, string $accion): void
    {
        $usuario = $inscripcion->getUsuario();
        $evento = $inscripcion->getEvento();

        $this->enqueue(
            $usuario->getEmail(),
            'Actualización de inscripción: ' . $evento->getTitulo(),
            'email/inscripcion_cambio.html.twig',
            [
                'nombre' => $usuario->getNombre(),
                'accion' => $accion,
                'evento' => $evento->getTitulo(),
                'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
                'estadoPago' => $inscripcion->getEstadoPago()->value,
                'importeTotal' => $inscripcion->getImporteTotal(),
                'importePagado' => $inscripcion->getImportePagado(),
            ],
            $usuario->getEntidad(),
            $usuario,
        );
    }

    /**
     * Procesa mensajes pendientes y los marca como enviados.
     * El envío SMTP real queda desacoplado: la cola guarda todo lo necesario.
     */
    public function processPending(int $limit = 50): int
    {
        $procesados = 0;
        $pendientes = $this->colaCorreoRepository->findPendientes($limit);

        foreach ($pendientes as $item) {
            try {
                // Renderizamos para validar plantilla/contexto antes de marcar como enviado.
                $this->twig->render($item->getPlantilla(), $item->getContexto());
                $item->setEstado(ColaCorreo::ESTADO_ENVIADO);
                $item->setEnviadoAt(new \DateTimeImmutable());
                $item->setUltimoError(null);
                $procesados++;
            } catch (\Throwable $e) {
                $item->incrementarIntentos();
                $item->setEstado(ColaCorreo::ESTADO_ERROR);
                $item->setUltimoError(substr($e->getMessage(), 0, 2000));
            }
        }

        $this->entityManager->flush();

        return $procesados;
    }
}

