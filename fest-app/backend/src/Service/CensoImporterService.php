<?php

namespace App\Service;

use App\Entity\Entidad;
use App\Entity\Usuario;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\MetodoPagoEnum;
use App\Repository\UsuarioRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CensoImporterService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailQueueService $emailQueueService,
    ) {}

    /**
     * Import census data from an Excel file.
     *
     * @param string $filePath Path to the uploaded Excel file
     * @param Entidad $entidad The entity this census belongs to
     * @param string $temporada The season/year for this census
     * @return array{total: int, insertadas: int, actualizadas: int, errores: array<int, string>}
     */
    public function importar(string $filePath, Entidad $entidad, string $temporada, string $appUri = 'http://localhost:4200'): array
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
            'actualizadas' => 0,
            'errores' => [],
        ];

        // Process rows starting from row 2
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNumber = $i + 1;
            $resultado['total']++;

            try {
                $wasCreated = $this->procesarFila($row, $headerMap, $entidad, $appUri);
                if ($wasCreated) {
                    $resultado['insertadas']++;
                } else {
                    $resultado['actualizadas']++;
                }
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
            'tipo_relacion' => null,
            'antiguedad' => null,
            'forma_pago' => null,
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
            if (in_array($normalized, ['antiguedad', 'anyosocio', 'anosocio'])) {
                $mapping['antiguedad'] = $index;
            }
            if (in_array($normalized, ['formapago', 'metodopago'])) {
                $mapping['forma_pago'] = $index;
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
    private function procesarFila(array $row, array $headerMap, Entidad $entidad, string $appUri): bool
    {
        // Get required fields
        $nombre = $this->getCellValue($row, $headerMap['nombre']);
        $apellidos = $this->getCellValue($row, $headerMap['apellidos']);

        if (empty($nombre) || empty($apellidos) || empty($email = $this->getCellValue($row, $headerMap['email']))) {
            throw new \InvalidArgumentException('Falta nombre, apellidos o email');
        }

        $normalizedEmail = strtolower(trim($email));
        $usuario = $this->usuarioRepository->findByEmail($normalizedEmail);
        $isNew = false;

        if (!$usuario instanceof Usuario) {
            $usuario = new Usuario();
            $usuario->setRoles(['ROLE_USER']);
            $plainPassword = $this->generateTemporaryPassword();
            $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $plainPassword));
            $this->entityManager->persist($usuario);
            $isNew = true;
        }

        if (!$isNew && $usuario->getEntidad()->getId() !== $entidad->getId()) {
            throw new \InvalidArgumentException('El email ya pertenece a otra entidad');
        }

        $usuario->setEntidad($entidad);
        $usuario->setNombre(trim($nombre));
        $usuario->setApellidos(trim($apellidos));
        $usuario->setEmail($normalizedEmail);
        $usuario->setActivo(true);
        if ($isNew) {
            $usuario->setDebeCambiarPassword(true);
            $usuario->setPasswordActualizadaAt(null);
        }

        $tipoRelacion = $this->getCellValue($row, $headerMap['tipo_relacion']) ?: 'interno';
        $tipoRelacionEnum = $this->parseTipoRelacion($tipoRelacion);
        $usuario->setCensadoVia(CensadoViaEnum::EXCEL);
        if ($usuario->getFechaAltaCenso() === null) {
            $usuario->setFechaAltaCenso(new \DateTimeImmutable());
        }

        $antiguedad = $this->getCellValue($row, $headerMap['antiguedad']);
        $usuario->setAntiguedad($antiguedad !== null && is_numeric($antiguedad) ? (int) $antiguedad : null);

        $formaPago = $this->getCellValue($row, $headerMap['forma_pago']);
        $usuario->setFormaPagoPreferida($this->parseMetodoPago($formaPago));

        if ($isNew) {
            $this->emailQueueService->enqueueUserWelcome($usuario, $plainPassword, $appUri);
        }

        return $isNew;
    }

    private function generateTemporaryPassword(): string
    {
        return 'Tmp' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10)) . '!';
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

    private function parseMetodoPago(?string $value): ?MetodoPagoEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim($value));
        return match (true) {
            str_contains($normalized, 'efect') => MetodoPagoEnum::EFECTIVO,
            str_contains($normalized, 'transf') => MetodoPagoEnum::TRANSFERENCIA,
            str_contains($normalized, 'bizum') => MetodoPagoEnum::BIZUM,
            str_contains($normalized, 'tpv') => MetodoPagoEnum::TPV,
            str_contains($normalized, 'online') => MetodoPagoEnum::ONLINE,
            str_contains($normalized, 'manual') => MetodoPagoEnum::MANUAL,
            default => null,
        };
    }
}
