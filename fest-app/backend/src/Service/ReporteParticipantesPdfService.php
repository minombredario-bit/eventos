<?php

namespace App\Service;

use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\Usuario;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class ReporteParticipantesPdfService
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function generarPdf(Evento $evento): string
    {
        $personas     = $this->collectPersonas($evento);
        $porActividad = $this->agruparPorActividad($personas);

        $html = $this->twig->render('pdf/reporte_participantes.html.twig', [
            'evento'       => $evento,
            'personas'     => $personas,
            'porActividad' => $porActividad,
            'adultos'      => count(array_filter($personas, fn($p) => strtolower($p['tipo']) === 'adulto')),
            'infantiles'   => count(array_filter($personas, fn($p) => strtolower($p['tipo']) === 'infantil')),
            'total'        => count($personas),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return list<array{
     *     nombreCompleto: string,
     *     tipo: string,
     *     actividad: string,
     *     franja: string|null,
     *     grupoRelacionKey: string
     * }>
     */
    private function collectPersonas(Evento $evento): array
    {
        $personas = [];

        foreach ($evento->getInscripciones() as $inscripcion) {
            foreach ($inscripcion->getLineas() as $linea) {
                $actividad = $linea->getActividad();
                $usuario = method_exists($linea, 'getUsuario') ? $linea->getUsuario() : null;

                $personas[] = [
                    'nombreCompleto'    => $linea->getNombrePersonaSnapshot() ?? '-',
                    'tipo'              => $linea->getTipoPersonaSnapshot() ?? '-',
                    'actividad'         => $linea->getNombreActividadSnapshot() ?? ($actividad?->getNombre() ?? '-'),
                    'franja'            => $actividad?->getFranjaComida()?->value,
                    'grupoRelacionKey'  => $usuario ? $this->buildGrupoRelacionKey($usuario) : 'zzz_' . ($linea->getNombrePersonaSnapshot() ?? uniqid()),
                ];
            }
        }

        usort($personas, static function (array $a, array $b): int {
            return strcmp($a['grupoRelacionKey'], $b['grupoRelacionKey'])
                ?: strcmp($a['tipo'], $b['tipo'])
                    ?: strcmp($a['nombreCompleto'], $b['nombreCompleto']);
        });

        return $personas;
    }

    private function buildGrupoRelacionKey(Usuario $usuario): string
    {
        $ids = [$usuario->getId()];

        foreach ($usuario->getRelacionados() as $relacion) {
            $origen = $relacion->getUsuarioOrigen();
            $destino = $relacion->getUsuarioDestino();

            if ($origen?->getId()) {
                $ids[] = $origen->getId();
            }

            if ($destino?->getId()) {
                $ids[] = $destino->getId();
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        sort($ids);

        return implode('_', $ids);
    }

    /**
     * @param list<array{actividad: string, franja: string|null}> $personas
     * @return array<string, array{actividad:string, franja:string|null, total:int}>
     */
    private function agruparPorActividad(array $personas): array
    {
        $grupos = [];

        foreach ($personas as $p) {
            $key = $p['actividad']; // si quieres evitar colisiones entre misma actividad con distinta franja, te lo adapto

            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'actividad' => $p['actividad'],
                    'franja'    => $p['franja'],
                    'total'     => 0,
                ];
            }

            $grupos[$key]['total']++;
        }

        uasort(
            $grupos,
            static fn(array $a, array $b) => $b['total'] <=> $a['total']
        );

        return $grupos;
    }
}
