<?php

namespace App\Service;

use App\Entity\CensoEntrada;
use App\Entity\Entidad;
use App\Repository\CensoEntradaRepository;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEconomicaEnum;

class CensoMatcherService
{
    public function __construct(
        private readonly CensoEntradaRepository $censoRepository
    ) {}

    /**
     * Match result types
     */
    public const MATCH_FOUND = 'found';
    public const MATCH_NOT_FOUND = 'not_found';
    public const MATCH_MULTIPLE = 'multiple';

    /**
     * Find a census entry match for a user trying to register.
     * 
     * @param Entidad $entidad The entity to search in
     * @param string $email User's email
     * @param string|null $dni User's DNI (optional, used as fallback)
     * @return array{result: string, entrada: ?CensoEntrada}
     */
    public function buscarCoincidencia(Entidad $entidad, string $email, ?string $dni = null): array
    {
        // First try email match
        $entrada = $this->buscarPorEmail($email, $entidad);
        
        if ($entrada !== null) {
            return [
                'result' => self::MATCH_FOUND,
                'entrada' => $entrada,
            ];
        }

        // If no email match, try DNI if provided
        if ($dni !== null && $dni !== '') {
            $entrada = $this->buscarPorDni($dni, $entidad);
            
            if ($entrada !== null) {
                return [
                    'result' => self::MATCH_FOUND,
                    'entrada' => $entrada,
                ];
            }
        }

        return [
            'result' => self::MATCH_NOT_FOUND,
            'entrada' => null,
        ];
    }

    /**
     * Find census entries by email (case-insensitive, normalized)
     */
    private function buscarPorEmail(string $email, Entidad $entidad): ?CensoEntrada
    {
        // Try exact email match first
        $entrada = $this->censoRepository->findOneBy([
            'entidad' => $entidad,
            'email' => strtolower(trim($email)),
            'procesado' => false,
        ]);

        if ($entrada !== null) {
            return $entrada;
        }

        // Try normalized email (without dots, plus addressing, etc.)
        $normalizedEmail = $this->normalizarEmail($email);
        
        return $this->censoRepository->findByEmailNormalizado($normalizedEmail, $entidad);
    }

    /**
     * Find census entries by DNI
     */
    private function buscarPorDni(string $dni, Entidad $entidad): ?CensoEntrada
    {
        return $this->censoRepository->findByDni($dni, $entidad);
    }

    /**
     * Normalize email for matching:
     * - lowercase
     * - trim whitespace
     * - remove plus addressing (e.g., john+tag@gmail.com -> john@gmail.com)
     * - normalize Gmail dots (e.g., john.doe@gmail.com -> johndoe@gmail.com)
     */
    public function normalizarEmail(string $email): string
    {
        $email = strtolower(trim($email));
        
        // Remove plus addressing
        if (str_contains($email, '+')) {
            $email = preg_replace('/\+.+@/', '@', $email);
        }
        
        // Normalize Gmail dots
        if (str_contains($email, '@gmail.com') || str_contains($email, '@googlemail.com')) {
            $localPart = explode('@', $email)[0];
            $localPart = str_replace('.', '', $localPart);
            $email = $localPart . '@gmail.com';
        }
        
        return $email;
    }
}
