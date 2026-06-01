<?php

namespace App\Service;

use App\Entity\ColaCorreo;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Usuario;
use App\Repository\ColaCorreoRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailQueueService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ColaCorreoRepository $colaCorreoRepository,
        private readonly Environment $twig,
        private readonly MailerInterface $mailer,
        private readonly string $appUri,
        private readonly string $mailerFrom = 'festapp@festapp.local',
    ) {}

    public function enqueue(
        ?string $destinatario,
        string $asunto,
        string $plantilla,
        array $contexto = [],
        ?Entidad $entidad = null,
        ?Usuario $usuario = null,
    ): ?ColaCorreo {
        $destinatario = $this->normalizeRecipient($destinatario);
        if ($destinatario === null) {
            return null;
        }

        $plantilla = $this->normalizeTemplateName($plantilla);

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
        $destinatario = $this->normalizeRecipient($usuario->getEmail());
        if ($destinatario === null) {
            return;
        }

        $this->enqueue(
            $destinatario,
            'Alta de usuario en la aplicación',
            'email/user_welcome.html.twig',
            [
                'nombre' => $usuario->getNombre(),
                'email' => $destinatario,
                'password' => $plainPassword,
                'appUri' => $appUri,
            ],
            $usuario->getEntidad(),
            $usuario,
        );
    }

    public function enqueueUserEmailChanged(
        Usuario $usuario,
        string $emailAnterior,
        string $emailNuevo,
        string $appUri,
    ): void {
        $destinatarios = [];

        foreach ([$emailAnterior, $emailNuevo, $usuario->getEmail()] as $destinatario) {
            $normalizado = $this->normalizeRecipient($destinatario);
            if ($normalizado === null) {
                continue;
            }

            $destinatarios[$normalizado] = true;
        }

        if ($destinatarios === []) {
            return;
        }

        $this->enqueueRecipients(
            array_keys($destinatarios),
            'Cambio de email en tu cuenta',
            'email/usuario_email_cambiado.html.twig',
            [
                'nombre' => $usuario->getNombre(),
                'emailAnterior' => $emailAnterior,
                'emailNuevo' => $emailNuevo,
                'appUri' => $appUri,
            ],
            $usuario->getEntidad(),
            $usuario,
        );
    }

    public function enqueuePasswordChanged(Usuario $usuario, string $plainPassword, string $appUri): void
    {
        $destinatario = $this->normalizeRecipient($usuario->getEmail());
        if ($destinatario === null) {
            return;
        }

        $this->enqueue(
            $destinatario,
            'Tu contraseña ha sido restablecida',
            'email/password_changed.html.twig',
            [
                'nombre' => $usuario->getNombre(),
                'email' => $destinatario,
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

            $destinatario = $this->normalizeRecipient($usuario->getEmail());
            if ($destinatario === null) {
                continue;
            }

            $this->enqueue(
                $destinatario,
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
        $destinatario = $this->normalizeRecipient($usuario->getEmail());

        if ($destinatario === null) {
            return;
        }

        $evento = $inscripcion->getEvento();
        $accionLabel = $this->resolveInscripcionActionLabel($accion);
        $lineasResumen = $this->buildInscripcionLineSummary($inscripcion);
        $isResumen = $accion === 'apuntado';

        $this->enqueue(
            $destinatario,
            ($isResumen ? 'Resumen de tu inscripción: ' : 'Actualización de inscripción: ') . $evento->getTitulo(),
            'email/inscripcion_cambio.html.twig',
            [
                'nombre' => $usuario->getNombre(),
                'accion' => $accion,
                'accionLabel' => $accionLabel,
                'evento' => $evento->getTitulo(),
                'codigoInscripcion' => $inscripcion->getCodigo(),
                'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
                'estadoPago' => $inscripcion->getEstadoPago()->value,
                'importeTotal' => $inscripcion->getImporteTotal(),
                'importePagado' => $inscripcion->getImportePagado(),
                'moneda' => $inscripcion->getMoneda(),
                'resumenLineas' => $lineasResumen,
                'totalLineas' => count($lineasResumen),
            ],
            $usuario->getEntidad(),
            $usuario,
        );
    }

    /**
     * @param list<string> $destinatarios
     */
    private function enqueueRecipients(
        array $destinatarios,
        string $asunto,
        string $plantilla,
        array $contexto = [],
        ?Entidad $entidad = null,
        ?Usuario $usuario = null,
    ): void {
        foreach ($destinatarios as $destinatario) {
            $this->enqueue($destinatario, $asunto, $plantilla, $contexto, $entidad, $usuario);
        }
    }

    /**
     * Procesa mensajes pendientes: renderiza la plantilla y los envía por SMTP.
     * El transporte SMTP se configura con MAILER_DSN en el entorno.
     */
    public function processPending(int $limit = 50): int
    {
        $procesados = 0;
        $pendientes = $this->colaCorreoRepository->findPendientes($limit);

        foreach ($pendientes as $item) {
            try {
                $destinatario = $this->normalizeRecipient($item->getDestinatario());
                if ($destinatario === null) {
                    $item->incrementarIntentos();
                    $item->setEstado(ColaCorreo::ESTADO_ERROR);
                    $item->setUltimoError('Destinatario vacío o no válido para el envío.');
                    continue;
                }

                $plantilla = $this->normalizeTemplateName($item->getPlantilla());
                // Se añade contexto visual común para que todos los correos compartan marca,
                // y el logo de la entidad solo se use cuando exista y sea resoluble.
                $html = $this->twig->render(
                    $plantilla,
                    $this->buildRenderContext($item->getEntidad(), $item->getContexto())
                );

                $email = (new Email())
                    ->from($this->mailerFrom)
                    ->to($destinatario)
                    ->subject($item->getAsunto())
                    ->html($html);

                $this->mailer->send($email);

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

    private function normalizeTemplateName(string $plantilla): string
    {
        $plantilla = trim($plantilla);

        if ($plantilla === '') {
            return $plantilla;
        }

        if (!str_contains($plantilla, '/')) {
            $plantilla = 'email/' . $plantilla;
        }

        if (!str_ends_with($plantilla, '.twig')) {
            $plantilla .= '.html.twig';
        }

        return $plantilla;
    }

    private function normalizeRecipient(?string $destinatario): ?string
    {
        if ($destinatario === null) {
            return null;
        }

        $destinatario = strtolower(trim($destinatario));

        return $destinatario === '' ? null : $destinatario;
    }

    private function resolveInscripcionActionLabel(string $accion): string
    {
        return match ($accion) {
            'apuntado' => 'Te has apuntado a nuevas actividades',
            'actualizado' => 'Tu inscripción se ha actualizado',
            'pago' => 'Se ha registrado un pago en tu inscripción',
            'borrado' => 'Se han eliminado actividades de tu inscripción',
            default => 'Tu inscripción ha cambiado',
        };
    }

    /**
     * @return list<array{persona: string, actividad: string, franja: string, precioUnitario: float}>
     */
    private function buildInscripcionLineSummary(Inscripcion $inscripcion): array
    {
        $lineas = $inscripcion->getLineas();
        if (!$lineas instanceof Collection || $lineas->isEmpty()) {
            return [];
        }

        $summary = [];

        foreach ($lineas as $linea) {
            if (!$linea instanceof InscripcionLinea) {
                continue;
            }

            if ($linea->getEstadoLinea()->value === 'cancelada') {
                continue;
            }

            $summary[] = [
                'persona' => $linea->getNombrePersonaSnapshot(),
                'actividad' => $linea->getNombreActividadSnapshot(),
                'franja' => $linea->getFranjaComidaSnapshot(),
                'precioUnitario' => $linea->getPrecioUnitario(),
            ];
        }

        usort(
            $summary,
            static fn(array $left, array $right): int => [$left['franja'], $left['persona'], $left['actividad']]
                <=> [$right['franja'], $right['persona'], $right['actividad']]
        );

        return $summary;
    }

    /**
     * @param array<string, mixed> $contexto
     * @return array<string, mixed>
     */
    private function buildRenderContext(?Entidad $entidad, array $contexto = []): array
    {
        $nombreEntidad = $entidad?->getNombre();
        $marca = $nombreEntidad ?: 'Festapp';

        $baseContexto = [
            'appUri' => $this->appUri,
            'entidadNombre' => $nombreEntidad,
            'entidadLogoUrl' => $this->resolveEntityLogoUrl($entidad),
            'marcaInicial' => mb_strtoupper(mb_substr($marca, 0, 1)),
        ];

        return array_replace($baseContexto, $contexto);
    }

    private function resolveEntityLogoUrl(?Entidad $entidad): ?string
    {
        $logo = trim((string) $entidad?->getLogo());

        if ($logo === '') {
            return null;
        }

        if (preg_match('#^(?:https?:)?//#i', $logo) === 1 || str_starts_with($logo, 'data:')) {
            return $logo;
        }

        return rtrim($this->appUri, '/') . '/' . ltrim($logo, '/');
    }
}
