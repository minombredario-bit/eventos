<?php

namespace App\Service;

use App\Entity\ActividadEvento;
use App\Entity\Usuario;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoPersonaEnum;

class PriceCalculatorService
{
    public function calcularPrecio(ActividadEvento $actividad, Usuario $usuario): float
    {
        return $this->calculatePrice($usuario, $actividad);
    }

    public function calculatePrice(Usuario $usuario, ActividadEvento $actividad): float
    {
        if (!$actividad->isEsDePago()) {
            return 0.0;
        }

        $isAdult = $usuario->getTipoPersona() !== TipoPersonaEnum::INFANTIL;
        $isInternal = $this->isPersonaValidated($usuario);

        if ($isAdult) {
            return $isInternal
                ? (float) ($actividad->getPrecioAdultoInterno() ?? $actividad->getPrecioBase())
                : (float) ($actividad->getPrecioAdultoExterno() ?? $actividad->getPrecioBase());
        }

        return $isInternal
            ? (float) ($actividad->getPrecioInfantil() ?? $actividad->getPrecioBase())
            : (float) ($actividad->getPrecioInfantilExterno() ?? $actividad->getPrecioInfantil() ?? $actividad->getPrecioBase());
    }

    public function calculatePriceForParticipant(
        string $tipoPersona,
        bool $isGuest,
        ActividadEvento $actividad,
    ): float {
        if (!$actividad->isEsDePago()) {
            return 0.0;
        }

        $isAdult = $tipoPersona !== TipoPersonaEnum::INFANTIL->value;

        if ($isAdult) {
            return $isGuest
                ? (float) ($actividad->getPrecioAdultoExterno() ?? $actividad->getPrecioBase())
                : (float) ($actividad->getPrecioAdultoInterno() ?? $actividad->getPrecioBase());
        }

        return $isGuest
            ? (float) ($actividad->getPrecioInfantilExterno() ?? $actividad->getPrecioInfantil() ?? $actividad->getPrecioBase())
            : (float) ($actividad->getPrecioInfantilInterno() ?? $actividad->getPrecioInfantil() ?? $actividad->getPrecioBase());
    }

    private function isPersonaValidated(Usuario $usuario): bool
    {
        return $usuario->getEstadoValidacion() === EstadoValidacionEnum::VALIDADO;
    }

    public function calculateTotal(array $lineas): float
    {
        $total = 0.0;

        foreach ($lineas as $linea) {
            if ($linea->getEstadoLinea()->value !== 'cancelada') {
                $total += $linea->getPrecioUnitario();
            }
        }

        return $total;
    }

    public function calcularTotal(array $lineas): float
    {
        $total = 0.0;

        foreach ($lineas as $linea) {
            if (
                is_array($linea)
                && isset($linea['actividad'], $linea['persona'])
                && $linea['actividad'] instanceof ActividadEvento
                && $linea['persona'] instanceof Usuario
            ) {
                $total += $this->calcularPrecio($linea['actividad'], $linea['persona']);
                continue;
            }

            if (
                is_object($linea)
                && method_exists($linea, 'getEstadoLinea')
                && method_exists($linea, 'getPrecioUnitario')
                && $linea->getEstadoLinea()->value !== 'cancelada'
            ) {
                $total += $linea->getPrecioUnitario();
            }
        }

        return $total;
    }
}
