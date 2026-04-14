<?php

namespace App\Service;

use App\Entity\ActividadEvento;
use App\Entity\Usuario;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\TipoActividadEnum;
use App\Enum\EstadoValidacionEnum;

class PriceCalculatorService
{
    /**
     * Compatibilidad legacy (ES):
     * Mantiene firma histórica calcularPrecio(ActividadEvento, Usuario).
     */
    public function calcularPrecio(ActividadEvento $actividad, Usuario $usuario): float
    {
        return $this->calculatePrice($usuario, $actividad);
    }

    /**
     * Calcula el precio para una persona en un actividad específica.
     *
     * Reglas (según REQUIREMENTS.md):
     * - Si esDePago = false → precio = 0
     * - Si el usuario NO está validada → precio externo
     * - Si el usuario es INTERNO → precioAdultoInterno o precioInfantil
     * - Si el usuario es EXTERNO o INVITADO → precioAdultoExterno o precioInfantil
     * - Si la actividad es INFANTIL → precioInfantil siempre
     * - Si la actividad es ADULTO → precio adulto
     * - Si no existe precio específico → precioBase
     */
    public function calculatePrice(Usuario $usuario, ActividadEvento $actividad): float
    {
        // Si el actividad no es de pago, precio 0
        if (!$actividad->isEsDePago()) {
            return 0.0;
        }

        // Determinar el precio según el tipo de actividad
        $actividadTipo = $actividad->getTipoActividad();

        // Si el actividad es infantil, siempre se aplica precio infantil
        if ($actividadTipo === TipoActividadEnum::INFANTIL) {
            $precioInfantil = $actividad->getPrecioInfantil();
            if ($precioInfantil !== null) {
                return $precioInfantil;
            }
            return (float) $actividad->getPrecioBase();
        }

        // Para actividads adultos o especiales
        // Si el usuario  no está validada, aplica precio externo
        if (!$this->isPersonaValidated($usuario)) {
            $precioExterno = $actividad->getPrecioAdultoExterno();
            if ($precioExterno !== null) {
                return $precioExterno;
            }
            return (float) $actividad->getPrecioBase();
        }

        // Persona validada - determinar si es interno o externo
        $relacionEconomica = $usuario->getTipoUsuarioEconomico();

        if ($relacionEconomica === TipoRelacionEconomicaEnum::INTERNO) {
            $precioInterno = $actividad->getPrecioAdultoInterno();
            if ($precioInterno !== null) {
                return $precioInterno;
            }
        }

        // Para externos o si no hay precio interno específico
        $precioExterno = $actividad->getPrecioAdultoExterno();
        if ($precioExterno !== null) {
            return $precioExterno;
        }

        // Fallback al precio base
        return (float) $actividad->getPrecioBase();
    }

    /**
     * Verifica si una persona está validada para precios internos.
     */
    private function isPersonaValidated(Usuario $usuario): bool
    {
        return $usuario->getEstadoValidacion() === EstadoValidacionEnum::VALIDADO;
    }

    /**
     * Calcula el importe total de una inscripción sumando todas sus líneas.
     */
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

    /**
     * Compatibilidad legacy (ES):
     * Acepta líneas en formato de test legacy:
     * [ ['actividad' => ActividadEvento, 'persona' => Usuario], ... ]
     *
     * También soporta líneas de dominio con métodos getEstadoLinea/getPrecioUnitario.
     */
    public function calcularTotal(array $lineas): float
    {
        $total = 0.0;

        foreach ($lineas as $linea) {
            // Formato legacy de tests
            if (is_array($linea) && isset($linea['actividad'], $linea['persona'])
                && $linea['actividad'] instanceof ActividadEvento
                && $linea['persona'] instanceof Usuario
            ) {
                $total += $this->calcularPrecio($linea['actividad'], $linea['persona']);
                continue;
            }

            // Formato de dominio actual
            if (is_object($linea)
                && method_exists($linea, 'getEstadoLinea')
                && method_exists($linea, 'getPrecioUnitario')
            ) {
                if ($linea->getEstadoLinea()->value !== 'cancelada') {
                    $total += $linea->getPrecioUnitario();
                }
            }
        }

        return $total;
    }
}
