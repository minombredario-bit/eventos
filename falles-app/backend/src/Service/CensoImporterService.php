<?php

namespace App\Service;

use App\Entity\CensoEntrada;
use App\Entity\Entidad;
use App\Repository\CensoEntradaRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEconomicaEnum;

class CensoImporterService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CensoEntradaRepository $censoRepository
    ) {}

    /**
     * Import census data from an Excel file.
     * 
     * @param string $filePath Path to the uploaded Excel file
     * @param Entidad $entidad The entity this census belongs to
     * @param string $temporada The season/year for this census
     * @return array{total: int, insertadas: int, errores: array<int, string>}
     */
    public function importar(string $filePath, Entidad $entidad, string $temporada): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        
        if (empty($rows)) {
            return [
                'total' => 0,
                'insertadas' => 0,
                'errores' => ['El archivo está vacío'],
            ];
        }

        // First row is header
        $header = array_map(fn($cell) => strtolower(trim((string)$cell)), $rows[0]);
        $headerMap = $this->mapearColumnas($header);
        
        $resultado = [
            'total' => 0,
            'insertadas' => 0,
            'errores' => [],
        ];

        // Process rows starting from row 2
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNumber = $i + 1;
            $resultado['total']++;

            try {
                $this->procesarFila($row, $headerMap, $entidad, $temporada);
                $resultado['insertadas']++;
            } catch (\Exception $e) {
                $resultado['errores'][$rowNumber] = $e->getMessage();
            }
        }

        $this->entityManager->flush();
        
        return $resultado;
    }

    /**
     * Map column names to their indices
     * Expected columns: nombre, apellidos, email, dni, parentesco, tipo_persona, tipo_relacion
     */
    private function mapearColumnas(array $header): array
    {
        $mapping = [
            'nombre' => null,
            'apellidos' => null,
            'email' => null,
            'dni' => null,
            'parentesco' => null,
            'tipo_persona' => null,
            'tipo_relacion' => null,
        ];

        foreach ($header as $index => $columnName) {
            // Handle common variations
            $normalized = $this->normalizarNombreColumna($columnName);
            
            if (isset($mapping[$normalized])) {
                $mapping[$normalized] = $index;
            }
            
            // Additional aliases
            if (in_array($normalized, ['name', 'nom', 'nombrecompleto'])) {
                $mapping['nombre'] = $index;
            }
            if (in_array($normalized, ['surname', 'apellido', 'apellidos'])) {
                $mapping['apellidos'] = $index;
            }
            if (in_array($normalized, ['correo', 'mail'])) {
                $mapping['email'] = $index;
            }
            if (in_array($normalized, ['documento', 'documentoid', 'nif'])) {
                $mapping['dni'] = $index;
            }
            if (in_array($normalized, ['relacion', 'relationship'])) {
                $mapping['tipo_relacion'] = $index;
            }
        }

        return $mapping;
    }

    /**
     * Normalize column name for matching
     */
    private function normalizarNombreColumna(string $name): string
    {
        // Remove accents and special chars
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $name = preg_replace('/[^a-z_]/', '', strtolower($name));
        return $name;
    }

    /**
     * Process a single row from the Excel file
     */
    private function procesarFila(array $row, array $headerMap, Entidad $entidad, string $temporada): void
    {
        // Get required fields
        $nombre = $this->getCellValue($row, $headerMap['nombre']);
        $apellidos = $this->getCellValue($row, $headerMap['apellidos']);

        if (empty($nombre) || empty($apellidos)) {
            throw new \InvalidArgumentException('Falta el campo nombre o apellidos');
        }

        $censoEntrada = new CensoEntrada();
        $censoEntrada->setEntidad($entidad);
        $censoEntrada->setNombre(trim($nombre));
        $censoEntrada->setApellidos(trim($apellidos));
        $censoEntrada->setTemporada($temporada);

        // Optional fields
        $email = $this->getCellValue($row, $headerMap['email']);
        if ($email) {
            $censoEntrada->setEmail($email);
        }

        $dni = $this->getCellValue($row, $headerMap['dni']);
        if ($dni) {
            $censoEntrada->setDni($dni);
        }

        $parentesco = $this->getCellValue($row, $headerMap['parentesco']) ?: 'otro';
        $censoEntrada->setParentesco($this->normalizarParentesco($parentesco));

        // Tipo persona (adulto/infantil)
        $tipoPersona = $this->getCellValue($row, $headerMap['tipo_persona']) ?: 'adulto';
        $censoEntrada->setTipoPersona($this->parseTipoPersona($tipoPersona));

        // Tipo relación económica (interno/externo/invitado)
        $tipoRelacion = $this->getCellValue($row, $headerMap['tipo_relacion']) ?: 'interno';
        $censoEntrada->setTipoRelacionEconomica($this->parseTipoRelacion($tipoRelacion));

        $this->entityManager->persist($censoEntrada);
    }

    /**
     * Get cell value safely
     */
    private function getCellValue(array $row, ?int $index): ?string
    {
        if ($index === null || !isset($row[$index])) {
            return null;
        }
        
        $value = $row[$index];
        if ($value === null || $value === '') {
            return null;
        }
        
        return trim((string)$value);
    }

    /**
     * Normalize parentesco value
     */
    private function normalizarParentesco(string $value): string
    {
        $normalized = strtolower(trim($value));
        
        $aliases = [
            'titular' => 'titular',
            'pareja' => 'pareja',
            'conyuge' => 'pareja',
            'esposa' => 'pareja',
            'esposo' => 'pareja',
            'hijo' => 'hijo',
            'hija' => 'hijo',
            'hija' => 'hijo',
            'familiar' => 'familiar',
            'otro' => 'otro',
        ];

        return $aliases[$normalized] ?? 'otro';
    }

    /**
     * Parse tipo persona enum
     */
    private function parseTipoPersona(string $value): TipoPersonaEnum
    {
        $normalized = strtolower(trim($value));
        
        if (str_contains($normalized, 'infantil') || str_contains($normalized, 'niñ')) {
            return TipoPersonaEnum::INFANTIL;
        }
        
        return TipoPersonaEnum::ADULTO;
    }

    /**
     * Parse tipo relación económica enum
     */
    private function parseTipoRelacion(string $value): TipoRelacionEconomicaEnum
    {
        $normalized = strtolower(trim($value));
        
        if (str_contains($normalized, 'extern')) {
            return TipoRelacionEconomicaEnum::EXTERNO;
        }
        
        if (str_contains($normalized, 'invitad')) {
            return TipoRelacionEconomicaEnum::INVITADO;
        }
        
        return TipoRelacionEconomicaEnum::INTERNO;
    }
}
