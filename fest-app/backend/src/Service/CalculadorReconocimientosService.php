<?php

namespace App\Service;

use App\Entity\Entidad;
use App\Entity\Reconocimiento;
use App\Entity\Usuario;
use App\Enum\TipoEntidadEnum;
use App\Repository\ReconocimientoRepository;
use App\Repository\UsuarioReconocimientoRepository;
use App\Repository\UsuarioTemporadaCargoRepository;

class CalculadorReconocimientosService
{
    public function __construct(
        private readonly CalculadorAntiguedadService $calculadorAntiguedadService,
        private readonly ReconocimientoRepository $reconocimientoRepository,
        private readonly UsuarioReconocimientoRepository $usuarioReconocimientoRepository,
        private readonly UsuarioTemporadaCargoRepository $usuarioTemporadaCargoRepository,
    ) {
    }

    /**
     * @return list<Reconocimiento>
     */
    public function getReconocimientosPosibles(Entidad $entidad): array
    {
        if (!$entidad->isUsaReconocimiento()) {
            return [];
        }

        return $this->reconocimientoRepository->findActivosByEntidad($entidad);
    }

    /**
     * @return list<Reconocimiento>
     */
    public function getReconocimientosConseguidos(Usuario $usuario, Entidad $entidad): array
    {
        if (!$entidad->isUsaReconocimiento()) {
            return [];
        }

        $items = $this->usuarioReconocimientoRepository->findByUsuarioAndEntidad($usuario, $entidad);

        return array_map(
            static fn ($item) => $item->getReconocimiento(),
            $items
        );
    }

    public function getMejorReconocimientoAlcanzable(Usuario $usuario, Entidad $entidad): ?Reconocimiento
    {
        if (!$entidad->isUsaReconocimiento()) {
            return null;
        }

        $resumen = $this->buildResumenEvaluacion($usuario, $entidad);
        $reconocimientos = $this->reconocimientoRepository->findActivosByEntidad($entidad);

        $mejor = null;

        foreach ($reconocimientos as $reconocimiento) {
            if ($this->cumpleReconocimiento($reconocimiento, $resumen, $entidad)) {
                $mejor = $reconocimiento;
            }
        }

        return $mejor;
    }

    public function getSiguienteReconocimientoPendiente(Usuario $usuario, Entidad $entidad): ?Reconocimiento
    {
        if (!$entidad->isUsaReconocimiento()) {
            return null;
        }

        $resumen = $this->buildResumenEvaluacion($usuario, $entidad);
        $reconocimientos = $this->reconocimientoRepository->findActivosByEntidad($entidad);

        foreach ($reconocimientos as $reconocimiento) {
            $yaLoTiene = $this->usuarioReconocimientoRepository
                ->existsUsuarioReconocimiento($usuario, $reconocimiento);

            if ($yaLoTiene) {
                continue;
            }

            if (!$this->cumpleReconocimiento($reconocimiento, $resumen, $entidad)) {
                continue;
            }

            if (!$reconocimiento->isRequiereAnterior()) {
                return $reconocimiento;
            }

            $anterior = $this->getReconocimientoAnterior($reconocimientos, $reconocimiento);
            if ($anterior === null) {
                return $reconocimiento;
            }

            $tieneAnterior = $this->usuarioReconocimientoRepository
                ->existsUsuarioReconocimiento($usuario, $anterior);

            if ($tieneAnterior) {
                return $reconocimiento;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     resumenAntiguedad: array,
     *     resumenInfantil: array{
     *         temporadasInfantiles:int,
     *         temporadasInfantilesEspeciales:int,
     *         temporadasPresidenteInfantil:int,
     *         temporadasFalleraMayorInfantil:int
     *     },
     *     mejorReconocimientoAlcanzable: array{id: string|null, codigo: string, nombre: string, tipo: string, orden: int}|null,
     *     siguienteReconocimientoPendiente: array{id: string|null, codigo: string, nombre: string, tipo: string, orden: int}|null,
     *     reconocimientosObtenidos: list<array{id: string|null, codigo: string, nombre: string, tipo: string, orden: int}>
     * }
     */
    public function getResumenReconocimientos(Usuario $usuario, Entidad $entidad): array
    {
        $resumenEvaluacion = $this->buildResumenEvaluacion($usuario, $entidad);

        if (!$entidad->isUsaReconocimiento()) {
            return [
                'resumenAntiguedad' => $resumenEvaluacion['resumenAntiguedad'],
                'resumenInfantil' => $resumenEvaluacion['resumenInfantil'],
                'mejorReconocimientoAlcanzable' => null,
                'siguienteReconocimientoPendiente' => null,
                'reconocimientosObtenidos' => [],
            ];
        }

        $mejor = $this->getMejorReconocimientoAlcanzable($usuario, $entidad);
        $siguiente = $this->getSiguienteReconocimientoPendiente($usuario, $entidad);
        $obtenidos = $this->getReconocimientosConseguidos($usuario, $entidad);

        return [
            'resumenAntiguedad' => $resumenEvaluacion['resumenAntiguedad'],
            'resumenInfantil' => $resumenEvaluacion['resumenInfantil'],
            'mejorReconocimientoAlcanzable' => $mejor ? $this->normalizarReconocimiento($mejor) : null,
            'siguienteReconocimientoPendiente' => $siguiente ? $this->normalizarReconocimiento($siguiente) : null,
            'reconocimientosObtenidos' => array_map(
                fn (Reconocimiento $r): array => $this->normalizarReconocimiento($r),
                $obtenidos
            ),
        ];
    }

    /**
     * @param array{
     *     resumenAntiguedad: array{
     *         temporadasComputables:int,
     *         temporadasDirectivo:int,
     *         aniosExtra:float,
     *         antiguedadPonderada:float
     *     },
     *     resumenInfantil: array{
     *         temporadasInfantiles:int,
     *         temporadasInfantilesEspeciales:int,
     *         temporadasPresidenteInfantil:int,
     *         temporadasFalleraMayorInfantil:int
     *     }
     * } $resumen
     */
    public function cumpleReconocimiento(
        Reconocimiento $reconocimiento,
        array $resumen,
        Entidad $entidad
    ): bool {
        return match ($reconocimiento->getTipo()) {
            Reconocimiento::TIPO_ANTIGUEDAD => $this->cumpleReconocimientoAntiguedad($reconocimiento, $resumen),
            Reconocimiento::TIPO_DIRECTIVO => $this->cumpleReconocimientoDirectivo($reconocimiento, $resumen),
            Reconocimiento::TIPO_INFANTIL => $this->cumpleReconocimientoInfantil($reconocimiento, $resumen, $entidad),
            default => false,
        };
    }

    /**
     * @return array{
     *     resumenAntiguedad: array,
     *     resumenInfantil: array{
     *         temporadasInfantiles:int,
     *         temporadasInfantilesEspeciales:int,
     *         temporadasPresidenteInfantil:int,
     *         temporadasFalleraMayorInfantil:int
     *     }
     * }
     */
    private function buildResumenEvaluacion(Usuario $usuario, Entidad $entidad): array
    {
        $resumenAntiguedad = $this->calculadorAntiguedadService->getResumenAntiguedad($usuario, $entidad);

        $resumenInfantil = [
            'temporadasInfantiles' => $this->usuarioTemporadaCargoRepository
                ->countTemporadasInfantilesUsuarioEnEntidad($usuario, $entidad),
            'temporadasInfantilesEspeciales' => $this->usuarioTemporadaCargoRepository
                ->countTemporadasInfantilesEspecialesUsuarioEnEntidad($usuario, $entidad),
            'temporadasPresidenteInfantil' => $this->usuarioTemporadaCargoRepository
                ->countTemporadasPorCodigoCargoUsuarioEnEntidad($usuario, $entidad, 'PRESIDENTE_INFANTIL'),
            'temporadasFalleraMayorInfantil' => $this->usuarioTemporadaCargoRepository
                ->countTemporadasPorCodigoCargoUsuarioEnEntidad($usuario, $entidad, 'FALLERA_MAYOR_INFANTIL'),
        ];

        return [
            'resumenAntiguedad' => $resumenAntiguedad,
            'resumenInfantil' => $resumenInfantil,
        ];
    }

    /**
     * @param array{
     *     resumenAntiguedad: array{
     *         antiguedadPonderada:float
     *     }
     * } $resumen
     */
    private function cumpleReconocimientoAntiguedad(Reconocimiento $reconocimiento, array $resumen): bool
    {
        $min = $reconocimiento->getMinAntiguedad();

        if ($min === null) {
            return false;
        }

        return (float) $resumen['resumenAntiguedad']['antiguedadPonderada'] >= $min;
    }

    /**
     * @param array{
     *     resumenAntiguedad: array{
     *         temporadasDirectivo:int
     *     }
     * } $resumen
     */
    private function cumpleReconocimientoDirectivo(Reconocimiento $reconocimiento, array $resumen): bool
    {
        $min = $reconocimiento->getMinAntiguedadDirectivo();

        if ($min === null) {
            return false;
        }

        return (float) $resumen['resumenAntiguedad']['temporadasDirectivo'] >= $min;
    }

    /**
     * @param array{
     *     resumenInfantil: array{
     *         temporadasInfantiles:int,
     *         temporadasInfantilesEspeciales:int,
     *         temporadasPresidenteInfantil:int,
     *         temporadasFalleraMayorInfantil:int
     *     }
     * } $resumen
     */
    private function cumpleReconocimientoInfantil(
        Reconocimiento $reconocimiento,
        array $resumen,
        Entidad $entidad
    ): bool {
        $tipo = $entidad->getTipoEntidad();
        if ($tipo === null || $tipo->getCodigo() !== TipoEntidadEnum::FALLA->value) {
            return false;
        }

        $codigo = $reconocimiento->getCodigo();

        return match ($codigo) {
            'DISTINCIO_PRESIDENT_INFANTIL' =>
                $reconocimiento->getMinAntiguedad() !== null
                && $resumen['resumenInfantil']['temporadasPresidenteInfantil'] >= $reconocimiento->getMinAntiguedad(),

            'DISTINCIO_FALLERA_MAJOR_INFANTIL' =>
                $reconocimiento->getMinAntiguedad() !== null
                && $resumen['resumenInfantil']['temporadasFalleraMayorInfantil'] >= $reconocimiento->getMinAntiguedad(),

            'DISTINTIU_COURE',
            'DISTINTIU_ARGENT',
            'DISTINTIU_OR' =>
                $reconocimiento->getMinAntiguedad() !== null
                && $resumen['resumenInfantil']['temporadasInfantiles'] >= $reconocimiento->getMinAntiguedad(),

            default => false,
        };
    }

    /**
     * @param list<Reconocimiento> $reconocimientos
     */
    private function getReconocimientoAnterior(array $reconocimientos, Reconocimiento $actual): ?Reconocimiento
    {
        $anterior = null;

        foreach ($reconocimientos as $reconocimiento) {
            if ($reconocimiento->getOrden() >= $actual->getOrden()) {
                break;
            }

            $anterior = $reconocimiento;
        }

        return $anterior;
    }

    /**
     * @return array{id: string|null, codigo: string, nombre: string, tipo: string, orden: int}
     */
    private function normalizarReconocimiento(Reconocimiento $reconocimiento): array
    {
        return [
            'id' => $reconocimiento->getId(),
            'codigo' => $reconocimiento->getCodigo(),
            'nombre' => $reconocimiento->getNombre(),
            'tipo' => $reconocimiento->getTipo(),
            'orden' => $reconocimiento->getOrden(),
        ];
    }
}
