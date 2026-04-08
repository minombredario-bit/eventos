<?php

namespace App\Service;

use App\Entity\MenuEvento;
use App\Entity\Usuario;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\TipoMenuEnum;
use App\Enum\EstadoValidacionEnum;

class PriceCalculatorService
{
    /**
     * Compatibilidad legacy (ES):
     * Mantiene firma histórica calcularPrecio(MenuEvento, Usuario).
     */
    public function calcularPrecio(MenuEvento $menu, Usuario $usuario): float
    {
        return $this->calculatePrice($usuario, $menu);
    }

    /**
     * Calcula el precio para una persona en un menú específico.
     * 
     * Reglas (según REQUIREMENTS.md):
     * - Si esDePago = false → precio = 0
     * - Si el usuario NO está validada → precio externo
     * - Si el usuario  es INTERNO → precioAdultoInterno o precioInfantil
     * - Si el usuario  es EXTERNO o INVITADO → precioAdultoExterno o precioInfantil
     * - Si el menú es INFANTIL → precioInfantil siempre
     * - Si el menú es ADULTO → precio adulto
     * - Si no existe precio específico → precioBase
     */
    public function calculatePrice(Usuario $usuario, MenuEvento $menu): float
    {
        // Si el menú no es de pago, precio 0
        if (!$menu->isEsDePago()) {
            return 0.0;
        }

        // Determinar el precio según el tipo de menú
        $menuTipo = $menu->getTipoMenu();
        
        // Si el menú es infantil, siempre se aplica precio infantil
        if ($menuTipo === TipoMenuEnum::INFANTIL) {
            $precioInfantil = $menu->getPrecioInfantil();
            if ($precioInfantil !== null) {
                return $precioInfantil;
            }
            return (float) $menu->getPrecioBase();
        }

        // Para menús adultos o especiales
        // Si el usuario  no está validada, aplica precio externo
        if (!$this->isPersonaValidated($usuario)) {
            $precioExterno = $menu->getPrecioAdultoExterno();
            if ($precioExterno !== null) {
                return $precioExterno;
            }
            return (float) $menu->getPrecioBase();
        }

        // Persona validada - determinar si es interno o externo
        $relacionEconomica = $usuario->getTipoUsuarioEconomico();
        
        if ($relacionEconomica === TipoRelacionEconomicaEnum::INTERNO) {
            $precioInterno = $menu->getPrecioAdultoInterno();
            if ($precioInterno !== null) {
                return $precioInterno;
            }
        }

        // Para externos o si no hay precio interno específico
        $precioExterno = $menu->getPrecioAdultoExterno();
        if ($precioExterno !== null) {
            return $precioExterno;
        }

        // Fallback al precio base
        return (float) $menu->getPrecioBase();
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
     * [ ['menu' => MenuEvento, 'persona' => Usuario], ... ]
     *
     * También soporta líneas de dominio con métodos getEstadoLinea/getPrecioUnitario.
     */
    public function calcularTotal(array $lineas): float
    {
        $total = 0.0;

        foreach ($lineas as $linea) {
            // Formato legacy de tests
            if (is_array($linea) && isset($linea['menu'], $linea['persona'])
                && $linea['menu'] instanceof MenuEvento
                && $linea['persona'] instanceof Usuario
            ) {
                $total += $this->calcularPrecio($linea['menu'], $linea['persona']);
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
