<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Service\ReporteParticipantesPdfService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventoReporteParticipantesProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly ReporteParticipantesPdfService $pdfService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        $eventoId = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
        $evento   = $eventoId !== null ? $this->eventoRepository->find($eventoId) : null;

        if ($evento === null) {
            throw new NotFoundHttpException('Evento no encontrado.');
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este evento.');
        }

        $pdf      = $this->pdfService->generarPdf($evento);
        $filename = $this->buildFilename($evento->getTitulo(), $evento->getId());

        return new Response(
            $pdf,
            Response::HTTP_OK,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length'      => strlen($pdf),
            ],
        );
    }

    private function buildFilename(string $titulo, string $id): string
    {
        $normalized = mb_strtolower(trim($titulo));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $id;
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? $id;
        $normalized = trim($normalized, '-');

        return sprintf('participantes-%s.pdf', $normalized ?: $id);
    }
}
