<?php

namespace App\Service;

use App\Entity\Entidad;
use App\Entity\TemporadaEntidad;
use App\Entity\Usuario;
use App\Entity\UsuarioTemporadaCargo;
use App\Repository\TemporadaEntidadRepository;
use App\Repository\UsuarioTemporadaCargoRepository;

class CalculadorAntiguedadService
{
    public function __construct(
        private readonly TemporadaEntidadRepository $temporadaEntidadRepository,
        private readonly UsuarioTemporadaCargoRepository $usuarioTemporadaCargoRepository,
    ) {
    }

    public function getTemporadaActual(Entidad $entidad): ?TemporadaEntidad
    {
        return $this->temporadaEntidadRepository->findTemporadaActualDeEntidad($entidad);
    }

    /**
     * @return list<UsuarioTemporadaCargo>
     */
    public function getCargosUsuarioEnTemporada(Usuario $usuario, TemporadaEntidad $temporada): array
    {
        return $this->usuarioTemporadaCargoRepository->findByUsuarioAndTemporada($usuario, $temporada);
    }

    public function getCargoPrincipalActual(Usuario $usuario, Entidad $entidad): ?UsuarioTemporadaCargo
    {
        $temporadaActual = $this->getTemporadaActual($entidad);

        if (!$temporadaActual) {
            return null;
        }

        return $this->usuarioTemporadaCargoRepository
            ->findCargoPrincipalDeUsuarioEnTemporada($usuario, $temporadaActual);
    }

    public function getTemporadasComputables(Usuario $usuario, Entidad $entidad): int
    {
        return $this->usuarioTemporadaCargoRepository
            ->countTemporadasComputablesUsuarioEnEntidad($usuario, $entidad);
    }

    public function getTemporadasComoDirectivo(Usuario $usuario, Entidad $entidad): int
    {
        return $this->usuarioTemporadaCargoRepository
            ->countTemporadasDirectivoUsuarioEnEntidad($usuario, $entidad);
    }

    public function getAniosExtra(Usuario $usuario, Entidad $entidad): float
    {
        return $this->usuarioTemporadaCargoRepository
            ->sumAniosExtraUsuarioEnEntidad($usuario, $entidad);
    }

    public function getAntiguedadPonderada(Usuario $usuario, Entidad $entidad): float
    {
        return $this->usuarioTemporadaCargoRepository
            ->getAntiguedadPonderadaUsuarioEnEntidad($usuario, $entidad);
    }

    /**
     * Resumen completo listo para exponer en API o usar en lógica de negocio.
     *
     * @return array{
     *     entidadId: string|null,
     *     usuarioId: string|null,
     *     temporadaActual: array{id: string|null, codigo: string, nombre: ?string}|null,
     *     cargoPrincipalActual: array{
     *         id: string|null,
     *         cargoId: string|null,
     *         cargoNombre: string,
     *         cargoCodigo: ?string,
     *         principal: bool,
     *         computaAntiguedad: bool,
     *         computaReconocimiento: bool,
     *         aniosExtraAplicados: float
     *     }|null,
     *     temporadasComputables: int,
     *     temporadasDirectivo: int,
     *     aniosExtra: float,
     *     antiguedadPonderada: float
     * }
     */
    public function getResumenAntiguedad(Usuario $usuario, Entidad $entidad): array
    {
        $temporadaActual = $this->getTemporadaActual($entidad);
        $cargoPrincipalActual = $temporadaActual
            ? $this->usuarioTemporadaCargoRepository->findCargoPrincipalDeUsuarioEnTemporada($usuario, $temporadaActual)
            : null;

        $temporadasComputables = $this->getTemporadasComputables($usuario, $entidad);
        $temporadasDirectivo = $this->getTemporadasComoDirectivo($usuario, $entidad);
        $aniosExtra = $this->getAniosExtra($usuario, $entidad);
        $antiguedadPonderada = $temporadasComputables + $aniosExtra;

        return [
            'entidadId' => $entidad->getId(),
            'usuarioId' => $usuario->getId(),
            'temporadaActual' => $temporadaActual ? [
                'id' => $temporadaActual->getId(),
                'codigo' => $temporadaActual->getCodigo(),
                'nombre' => $temporadaActual->getNombre(),
            ] : null,
            'cargoPrincipalActual' => $cargoPrincipalActual ? $this->normalizarCargoTemporada($cargoPrincipalActual) : null,
            'temporadasComputables' => $temporadasComputables,
            'temporadasDirectivo' => $temporadasDirectivo,
            'aniosExtra' => $aniosExtra,
            'antiguedadPonderada' => $antiguedadPonderada,
        ];
    }

    /**
     * @return list<array{
     *     id: string|null,
     *     cargoId: string|null,
     *     cargoNombre: string,
     *     cargoCodigo: ?string,
     *     principal: bool,
     *     computaAntiguedad: bool,
     *     computaReconocimiento: bool,
     *     aniosExtraAplicados: float
     * }>
     */
    public function getCargosActualesNormalizados(Usuario $usuario, Entidad $entidad): array
    {
        $temporadaActual = $this->getTemporadaActual($entidad);

        if (!$temporadaActual) {
            return [];
        }

        $items = $this->getCargosUsuarioEnTemporada($usuario, $temporadaActual);

        return array_map(
            fn (UsuarioTemporadaCargo $item): array => $this->normalizarCargoTemporada($item),
            $items
        );
    }

    /**
     * @return array{
     *     id: string|null,
     *     cargoId: string|null,
     *     cargoNombre: string,
     *     cargoCodigo: ?string,
     *     principal: bool,
     *     computaAntiguedad: bool,
     *     computaReconocimiento: bool,
     *     aniosExtraAplicados: float
     * }
     */
    private function normalizarCargoTemporada(UsuarioTemporadaCargo $item): array
    {
        $cargo = $item->getCargo();

        return [
            'id' => $item->getId(),
            'cargoId' => $cargo?->getId(),
            'cargoNombre' => $cargo?->getNombre() ?? '',
            'cargoCodigo' => $cargo?->getCodigo(),
            'principal' => $item->isPrincipal(),
            'computaAntiguedad' => $item->isComputaAntiguedad(),
            'computaReconocimiento' => $item->isComputaReconocimiento(),
            'aniosExtraAplicados' => $item->getAniosExtraAplicados(),
        ];
    }
}
