<?php

namespace App\Service;

use App\Entity\ColaCorreo;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\Usuario;
use App\Repository\ColaCorreoRepository;
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
        string $destinatario,
        string $asunto,
        string $plantilla,
        array $contexto = [],
        ?Entidad $entidad = null,
        ?Usuario $usuario = null,
    ): ColaCorreo {
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
        if (!$usuario->getEmail()) {
            return;
        }

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

    public function enqueueUserEmailChanged(
        Usuario $usuario,
        string $emailAnterior,
        string $emailNuevo,
        string $appUri,
    ): void {
        $destinatarios = [];

        foreach ([$emailAnterior, $emailNuevo, $usuario->getEmail()] as $destinatario) {
            $normalizado = strtolower(trim($destinatario));
            if ($normalizado === '') {
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
                $plantilla = $this->normalizeTemplateName($item->getPlantilla());
                // Se añade contexto visual común para que todos los correos compartan marca,
                // y el logo de la entidad solo se use cuando exista y sea resoluble.
                $html = $this->twig->render(
                    $plantilla,
                    $this->buildRenderContext($item->getEntidad(), $item->getContexto())
                );

                $email = (new Email())
                    ->from($this->mailerFrom)
                    ->to($item->getDestinatario())
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

