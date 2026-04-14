<?php

namespace App\Service;

/**
 * CodeGeneratorService
 * 
 * Generates unique, secure registration codes for entities.
 */
class CodeGeneratorService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 12;

    /**
     * Generate a secure random registration code.
     * Format: 12 alphanumeric characters (no confusing chars like 0, O, 1, I, L)
     */
    public function generateRegistroCode(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        
        return $code;
    }

    /**
     * Generate a unique inscription code.
     * Format: PREFIX-YYYY-NNNNN
     * Example: FALLA-2025-00001
     */
    public function generateInscripcionCode(string $prefix, int $sequentialNumber): string
    {
        $year = date('Y');
        $number = str_pad((string) $sequentialNumber, 5, '0', STR_PAD_LEFT);
        
        return sprintf('%s-%s-%s', strtoupper($prefix), $year, $number);
    }
}
