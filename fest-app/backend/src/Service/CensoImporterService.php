<?php

namespace App\Service;

use App\Entity\Entidad;
use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use App\Enum\CensadoViaEnum;
use App\Enum\MetodoPagoEnum;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEnum;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CensoImporterService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailQueueService $emailQueueService,
        private readonly string $defaultUri,
    ) {}

    public function importar(
        string $filePath,
        Entidad $entidad,
        string $temporada
    ): array {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (empty($rows)) {
            return [
                'total' => 0,
                'insertadas' => 0,
                'actualizadas' => 0,
                'relaciones' => 0,
                'errores' => ['El archivo está vacío'],
                'passwords_excel' => null,
            ];
        }

        $header = array_map(
            fn ($cell) => $this->normalizarNombreColumna((string) $cell),
            $rows[0]
        );

        $headerMap = $this->mapearColumnas($header);

        $resultado = [
            'total' => 0,
            'insertadas' => 0,
            'actualizadas' => 0,
            'relaciones' => 0,
            'relaciones_eliminadas' => 0,
            'errores' => [],
            'passwords_excel' => null,
        ];

        $passwordRows = [];
        $usuariosPorFila = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNumber = $i + 1;

            if ($this->filaVacia($row)) {
                continue;
            }

            $resultado['total']++;

            try {
                $procesado = $this->procesarFila($row, $headerMap, $entidad, $this->defaultUri);
                $usuariosPorFila[$i] = $procesado['usuario'];

                if ($procesado['created']) {
                    $resultado['insertadas']++;
                } else {
                    $resultado['actualizadas']++;
                }

                if ($procesado['password'] !== null) {
                    $passwordRows[] = [
                        'nombre_completo' => $procesado['nombre_completo'],
                        'email' => $procesado['email'],
                        'password' => $procesado['password'],
                    ];
                }
            } catch (\Throwable $e) {
                $resultado['errores'][$rowNumber] = $e->getMessage();
            }
        }

        $this->entityManager->flush();

        $familias = [];
        $amistades = [];

        foreach ($usuariosPorFila as $i => $usuario) {
            $row = $rows[$i];

            $grupoFamiliar = $this->normalizarGrupo(
                $this->getCellValue($row, $headerMap['grupo_familiar'])
            );

            if ($grupoFamiliar !== null) {
                $familias[$grupoFamiliar][] = $usuario;
            }

            $grupoAmistad = $this->normalizarGrupo(
                $this->getCellValue($row, $headerMap['grupo_amistad'])
            );

            if ($grupoAmistad !== null) {
                $amistades[$grupoAmistad][] = $usuario;
            }
        }

        $relFamilia = $this->sincronizarRelacionesPorGrupos(
            $familias,
            TipoRelacionEnum::FAMILIAR,
            array_values($usuariosPorFila)
        );

        $relAmistad = $this->sincronizarRelacionesPorGrupos(
            $amistades,
            TipoRelacionEnum::AMISTAD,
            array_values($usuariosPorFila)
        );

        $resultado['relaciones'] = $relFamilia['creadas'] + $relAmistad['creadas'];
        $resultado['relaciones_eliminadas'] = $relFamilia['eliminadas'] + $relAmistad['eliminadas'];

        $this->entityManager->flush();

        if (!empty($passwordRows)) {
            $resultado['passwords_excel'] = $this->generarExcelPasswords($passwordRows);
        }

        return $resultado;
    }

    public function exportar(array $usuarios): string
    {
        $gruposFamiliares = $this->generarCodigosGrupo($usuarios, TipoRelacionEnum::FAMILIAR, 'fam');
        $gruposAmistad    = $this->generarCodigosGrupo($usuarios, TipoRelacionEnum::AMISTAD,  'ami');

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

        $sheet->fromArray([
            'codigo_usuario',
            'Nombre',
            'Apellidos',
            'direccion',
            'dni',
            'movil',
            'fecha_nacimiento',
            'email',
            'debe_cambiar_password',
            'fecha_baja_censo',
            'motivo_baja_censo',
            'antiguedad',
            'grupo_familiar',
            'grupo_amistad',
        ], null, 'A1');

        $row = 2;

        foreach ($usuarios as $usuario) {
            $userId = (string) $usuario->getId();

            $sheet->fromArray([
                $userId,
                $usuario->getNombre(),
                $usuario->getApellidos(),
                $usuario->getDireccion(),
                $usuario->getDocumentoIdentidad(),
                $usuario->getTelefono(),
                $usuario->getFechaNacimiento()?->format('d/m/Y'),
                $usuario->getEmail(),
                $usuario->isDebeCambiarPassword() ? 1 : 0,
                $usuario->getFechaBajaCenso()?->format('d/m/Y'),
                $usuario->getMotivoBajaCenso(),
                $usuario->getAntiguedad(),
                $gruposFamiliares[$userId] ?? '',
                $gruposAmistad[$userId]    ?? '',
            ], null, 'A' . $row);

            $row++;
        }

        foreach (range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'usuarios_entidad_'
            . date('Ymd_His')
            . '.xlsx';

        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }

    public function exportarCumples(array $usuarios): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Cumpleaños');

        $sheet->setCellValue('A1', 'Nombre completo');
        $sheet->setCellValue('B1', 'Fecha nacimiento');
        $sheet->setCellValue('C1', 'Cumple este año');

        $row = 2;

        foreach ($usuarios as $usuario) {
            $nombreCompleto = method_exists($usuario, 'getNombreCompleto') && $usuario->getNombreCompleto()
                ? $usuario->getNombreCompleto()
                : trim(($usuario->getNombre() ?? '') . ' ' . ($usuario->getApellidos() ?? ''));

            $fechaNacimiento = $usuario->getFechaNacimiento();

            $sheet->setCellValue("A{$row}", $nombreCompleto);
            $sheet->setCellValue("B{$row}", $fechaNacimiento?->format('d/m/Y') ?? '');
            $sheet->setCellValue("C{$row}", $this->calcularEdadCumpleAnioActual($fechaNacimiento));

            $row++;
        }

        foreach (range('A', 'C') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        $filePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'usuarios_cumples_'
            . date('Ymd_His')
            . '.xlsx';

        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }

    private function procesarFila(array $row, array $headerMap, Entidad $entidad, string $appUri): array
    {
        $nombre = $this->normalizarTextoPersona(
            $this->getCellValue($row, $headerMap['nombre'])
        );

        $apellidos = $this->normalizarTextoPersona(
            $this->getCellValue($row, $headerMap['apellidos'])
        );

        if (!$nombre || !$apellidos) {
            throw new \InvalidArgumentException('Falta nombre o apellidos');
        }

        $email = $this->normalizarEmail($this->getCellValue($row, $headerMap['email']));
        $dni = $this->normalizarDni($this->getCellValue($row, $headerMap['dni']));

        $fechaNacimiento = $this->parseFecha(
            $this->getCellValue($row, $headerMap['fecha_nacimiento'])
        );

        $fechaBajaCenso = $this->parseFecha(
            $this->getCellValue($row, $headerMap['fecha_baja_censo'])
        );

        $motivoBajaCenso = $this->getCellValue($row, $headerMap['motivo_baja_censo']);

        $debeCambiarPasswordExcel = $this->parseBooleanNullable(
            $this->getCellValue($row, $headerMap['debe_cambiar_password'])
        );

        $codigoUsuario = $this->getCellValue($row, $headerMap['codigo_usuario']);

        $usuario = $this->buscarUsuario(
            entidad: $entidad,
            codigoUsuario: $codigoUsuario,
            nombre: $nombre,
            apellidos: $apellidos,
            dni: $dni
        );

        $isNew = false;
        $plainPassword = null;

        if (!$usuario instanceof Usuario) {
            $usuario = new Usuario();
            $usuario->setEntidad($entidad);
            $usuario->setNombre(trim($nombre));
            $usuario->setApellidos(trim($apellidos));
            $usuario->setRoles(['ROLE_USER']);

            $this->entityManager->persist($usuario);
            $isNew = true;
        }

        if (!$isNew && $usuario->getEntidad()?->getId() !== $entidad->getId()) {
            throw new \InvalidArgumentException('El usuario ya pertenece a otra entidad');
        }

        $usuario->setEntidad($entidad);
        $usuario->setNombre(trim($nombre));
        $usuario->setApellidos(trim($apellidos));
        $usuario->setCensadoVia(CensadoViaEnum::EXCEL);
        $usuario->setDireccion(
            $this->normalizarTextoPersona(
                $this->getCellValue($row, $headerMap['direccion'])
            )
        );
        $usuario->setTelefono($this->getCellValue($row, $headerMap['movil']));
        $usuario->setFechaNacimiento($fechaNacimiento);
        $usuario->setTipoPersona($this->calcularTipoPersona($fechaNacimiento));

        if ($dni !== null) {
            $usuario->setDocumentoIdentidad($dni);
        }

        if ($email !== null) {
            $usuario->setEmail($email);
        }

        if ($usuario->getFechaAltaCenso() === null && $fechaBajaCenso === null) {
            $usuario->setFechaAltaCenso(new \DateTimeImmutable());
        }

        if ($fechaBajaCenso !== null) {
            $usuario->setFechaBajaCenso($fechaBajaCenso);
            $usuario->setMotivoBajaCenso($motivoBajaCenso);
            $usuario->setActivo(false);
        } else {
            $usuario->setFechaBajaCenso(null);
            $usuario->setMotivoBajaCenso(null);
            $usuario->setActivo(true);
        }

        $debeGenerarPassword = $isNew || $debeCambiarPasswordExcel === true;

        if ($debeGenerarPassword) {
            $plainPassword = $this->generateTemporaryPassword();

            $usuario->setPassword(
                $this->passwordHasher->hashPassword($usuario, $plainPassword)
            );

            $usuario->setDebeCambiarPassword(true);
            $usuario->setPasswordActualizadaAt(null);
        } elseif ($debeCambiarPasswordExcel === false) {
            $usuario->setDebeCambiarPassword(false);
        }

        $antiguedad = $this->getCellValue($row, $headerMap['antiguedad']);
        $usuario->setAntiguedad(
            $antiguedad !== null && is_numeric($antiguedad)
                ? (int) $antiguedad
                : null
        );

        $formaPago = $this->getCellValue($row, $headerMap['forma_pago']);
        $usuario->setFormaPagoPreferida($this->parseMetodoPago($formaPago));

        if ($plainPassword !== null && $email !== null) {
            $this->emailQueueService->enqueueUserWelcome($usuario, $plainPassword, $appUri);
        }

        return [
            'created' => $isNew,
            'usuario' => $usuario,
            'nombre_completo' => trim($nombre . ' ' . $apellidos),
            'email' => $email,
            'password' => $plainPassword,
        ];
    }

    private function crearRelacionesCompletas(array $usuarios, TipoRelacionEnum $tipoRelacion): int
    {
        $usuarios = array_values(array_filter($usuarios, fn ($u) => $u instanceof Usuario));

        $total = count($usuarios);

        if ($total < 2) {
            return 0;
        }

        if ($total > 10) {
            throw new \RuntimeException(
                sprintf('Grupo de relación demasiado grande: %d usuarios. Máximo permitido: 10.', $total)
            );
        }

        $creadas = 0;

        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                $creadas += $this->crearRelacionSiNoExiste($usuarios[$i], $usuarios[$j], $tipoRelacion);
                $creadas += $this->crearRelacionSiNoExiste($usuarios[$j], $usuarios[$i], $tipoRelacion);
            }
        }

        return $creadas;
    }

    private function crearRelacionSiNoExiste(
        Usuario $usuarioOrigen,
        Usuario $usuarioDestino,
        TipoRelacionEnum $tipoRelacion
    ): int {
        if ($usuarioOrigen->getId() === $usuarioDestino->getId()) {
            return 0;
        }

        $existente = $this->entityManager
            ->getRepository(RelacionUsuario::class)
            ->findOneBy([
                'usuarioOrigen' => $usuarioOrigen,
                'usuarioDestino' => $usuarioDestino,
                'tipoRelacion' => $tipoRelacion,
            ]);

        if ($existente instanceof RelacionUsuario) {
            return 0;
        }

        $relacion = new RelacionUsuario();
        $relacion->setUsuarioOrigen($usuarioOrigen);
        $relacion->setUsuarioDestino($usuarioDestino);
        $relacion->setTipoRelacion($tipoRelacion);

        $this->entityManager->persist($relacion);

        return 1;
    }

    private function buscarUsuario(
        Entidad $entidad,
        ?string $codigoUsuario,
        string $nombre,
        string $apellidos,
        ?string $dni
    ): ?Usuario {
        if ($codigoUsuario !== null && trim($codigoUsuario) !== '') {
            $usuario = $this->usuarioRepository->find($codigoUsuario);

            if ($usuario instanceof Usuario) {
                if ($usuario->getEntidad()->getId() !== $entidad->getId()) {
                    throw new \InvalidArgumentException('El codigo_usuario pertenece a otra entidad');
                }

                return $usuario;
            }
        }

        if ($dni !== null) {
            $usuario = $this->usuarioRepository->findOneBy([
                'entidad' => $entidad,
                'documentoIdentidad' => $dni,
            ]);

            if ($usuario instanceof Usuario) {
                return $usuario;
            }
        }

        return $this->usuarioRepository->findOneBy([
            'entidad' => $entidad,
            'nombre' => trim($nombre),
            'apellidos' => trim($apellidos),
        ]);
    }

    private function mapearColumnas(array $header): array
    {
        $mapping = [
            'codigo_usuario' => null,
            'nombre' => null,
            'apellidos' => null,
            'direccion' => null,
            'email' => null,
            'dni' => null,
            'movil' => null,
            'fecha_nacimiento' => null,
            'antiguedad' => null,
            'forma_pago' => null,
            'debe_cambiar_password' => null,
            'fecha_baja_censo' => null,
            'motivo_baja_censo' => null,
            'grupo_familiar' => null,
            'grupo_amistad' => null,
        ];

        foreach ($header as $index => $columnName) {
            if (in_array($columnName, ['codigo_usuario', 'codigousuario', 'uuid', 'id'], true)) {
                $mapping['codigo_usuario'] = $index;
                continue;
            }

            if (in_array($columnName, ['nombre', 'name', 'nom'], true)) {
                $mapping['nombre'] = $index;
                continue;
            }

            if (in_array($columnName, ['apellidos', 'apellido', 'surname', 'surnames'], true)) {
                $mapping['apellidos'] = $index;
                continue;
            }

            if (in_array($columnName, ['direccion', 'address', 'domicilio'], true)) {
                $mapping['direccion'] = $index;
                continue;
            }

            if (in_array($columnName, ['email', 'mail', 'correo', 'correoelectronico'], true)) {
                $mapping['email'] = $index;
                continue;
            }

            if (in_array($columnName, ['dni', 'nif', 'nie', 'documento', 'documentoid'], true)) {
                $mapping['dni'] = $index;
                continue;
            }

            if (in_array($columnName, ['movil', 'mobile', 'telefono', 'phone', 'tel'], true)) {
                $mapping['movil'] = $index;
                continue;
            }

            if (in_array($columnName, ['fechanacimiento', 'fecha_nacimiento', 'nacimiento', 'birthdate'], true)) {
                $mapping['fecha_nacimiento'] = $index;
                continue;
            }

            if (in_array($columnName, ['antiguedad', 'anyosocio', 'anosocio'], true)) {
                $mapping['antiguedad'] = $index;
                continue;
            }

            if (in_array($columnName, ['formapago', 'metodopago', 'forma_pago'], true)) {
                $mapping['forma_pago'] = $index;
                continue;
            }

            if (in_array($columnName, ['debecambiarpassword', 'debe_cambiar_password', 'cambiarpassword', 'cambiar_contrasena', 'cambiarcontrasena', 'cambiar_cor', 'debe_cambi'], true)) {
                $mapping['debe_cambiar_password'] = $index;
                continue;
            }

            if (in_array($columnName, ['fechabajacenso', 'fecha_baja_censo', 'fecha_baja_', 'fechabaja', 'baja'], true)) {
                $mapping['fecha_baja_censo'] = $index;
                continue;
            }

            if (in_array($columnName, ['motivobajacenso', 'motivo_baja_censo', 'motivo_baja', 'motivobaja'], true)) {
                $mapping['motivo_baja_censo'] = $index;
                continue;
            }

            if (in_array($columnName, ['grupo_familiar', 'grupofamiliar', 'grupo_fan', 'grupofan', 'familia', 'familiar'], true)) {
                $mapping['grupo_familiar'] = $index;
                continue;
            }

            if (in_array($columnName, ['grupo_amistad', 'grupoamistad', 'grupo_am', 'grupoam', 'amistad', 'amigos'], true)) {
                $mapping['grupo_amistad'] = $index;
            }
        }

        return $mapping;
    }

    private function generarExcelPasswords(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Usuarios creados');
        $sheet->setCellValue('A1', 'nombrecompleto');
        $sheet->setCellValue('B1', 'email');
        $sheet->setCellValue('C1', 'password');

        $i = 2;

        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $i, $row['nombre_completo']);
            $sheet->setCellValue('B' . $i, $row['email']);
            $sheet->setCellValue('C' . $i, $row['password']);
            $i++;
        }

        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'usuarios_passwords_'
            . date('Ymd_His')
            . '.xlsx';

        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }

    private function getCellValue(array $row, ?int $index): ?string
    {
        if ($index === null || !array_key_exists($index, $row)) {
            return null;
        }

        $value = $row[$index];

        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        // Limpia espacios raros de Excel: NBSP, zero-width, tabs, saltos, etc.
        $value = str_replace(["\xc2\xa0", "\u{00A0}", "\u{200B}", "\u{FEFF}"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);

        if ($value === '' || in_array(strtolower($value), ['null', 'nil', 'none', '-'], true)) {
            return null;
        }

        return $value;
    }

    private function normalizarNombreColumna(string $name): string
    {
        $name = trim($name);
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $name = strtolower((string) $name);

        return preg_replace('/[^a-z_]/', '', $name) ?? '';
    }

    private function normalizarGrupo(?string $grupo): ?string
    {
        if ($grupo === null) {
            return null;
        }

        // Limpia caracteres invisibles de Excel
        $grupo = str_replace(["\xc2\xa0", "\u{00A0}", "\u{200B}", "\u{FEFF}", "\t", "\r", "\n"], ' ', $grupo);
        $grupo = preg_replace('/\s+/u', '', $grupo);  // elimina TODOS los espacios
        $grupo = strtolower((string) $grupo);

        if ($grupo === '' || in_array($grupo, ['null', 'nil', 'none', '-'], true)) {
            return null;
        }

        return $grupo;
    }

    private function normalizarEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }

    private function normalizarDni(?string $dni): ?string
    {
        if ($dni === null || trim($dni) === '') {
            return null;
        }

        return strtoupper(str_replace([' ', '-', '.'], '', trim($dni)));
    }

    private function parseFecha(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd/m/y', 'd-m-y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }

    private function parseBooleanNullable(?string $value): ?bool
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'si', 'sí', 'true', 'yes', 'y' => true,
            '0', 'no', 'false', 'n' => false,
            default => null,
        };
    }

    private function parseMetodoPago(?string $value): ?MetodoPagoEnum
    {
        if ($value === null || trim($value) === '') {
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

    private function generateTemporaryPassword(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(6)), 0, 10)) . '!';
    }

    private function filaVacia(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function calcularTipoPersona(?\DateTimeImmutable $fechaNacimiento): TipoPersonaEnum
    {
        if (!$fechaNacimiento instanceof \DateTimeImmutable) {
            return TipoPersonaEnum::ADULTO;
        }

        $edad = $fechaNacimiento->diff(new \DateTimeImmutable('today'))->y;

        return match (true) {
            $edad <= 13 => TipoPersonaEnum::INFANTIL,
            $edad < 18 => TipoPersonaEnum::CADETE,
            default => TipoPersonaEnum::ADULTO,
        };
    }

    private function normalizarTextoPersona(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(["\xc2\xa0", "\u{00A0}", "\u{200B}", "\u{FEFF}"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function sincronizarRelacionesPorGrupos(
        array $grupos,
        TipoRelacionEnum $tipoRelacion,
        array $usuariosImportados
    ): array {
        $usuariosImportados = array_values(array_filter(
            $usuariosImportados,
            fn ($u) => $u instanceof Usuario
        ));

        $usuariosImportadosIds = [];

        foreach ($usuariosImportados as $usuario) {
            $usuariosImportadosIds[(string) $usuario->getId()] = true;
        }

        $relacionesDeseadas = [];
        $creadas = 0;
        $eliminadas = 0;

        foreach ($grupos as $grupo => $usuarios) {
            $usuarios = array_values(array_filter($usuarios, fn ($u) => $u instanceof Usuario));
            $total = count($usuarios);

            if ($total < 2) {
                continue;
            }

            if ($total > 10) {
                throw new \RuntimeException(
                    sprintf('Grupo %s demasiado grande: %d usuarios. Máximo permitido: 10.', $grupo, $total)
                );
            }

            for ($i = 0; $i < $total; $i++) {
                for ($j = $i + 1; $j < $total; $j++) {
                    $origenId = (string) $usuarios[$i]->getId();
                    $destinoId = (string) $usuarios[$j]->getId();

                    if ($origenId === $destinoId) {
                        continue;
                    }

                    $relacionesDeseadas[$origenId . '|' . $destinoId . '|' . $tipoRelacion->value] = [
                        'origen' => $usuarios[$i],
                        'destino' => $usuarios[$j],
                    ];

                    $relacionesDeseadas[$destinoId . '|' . $origenId . '|' . $tipoRelacion->value] = [
                        'origen' => $usuarios[$j],
                        'destino' => $usuarios[$i],
                    ];
                }
            }
        }

        $relacionesExistentes = $this->entityManager
            ->getRepository(RelacionUsuario::class)
            ->findBy(['tipoRelacion' => $tipoRelacion]);

        $existentesIndex = [];

        foreach ($relacionesExistentes as $relacion) {
            if (!$relacion instanceof RelacionUsuario) {
                continue;
            }

            $origen = $relacion->getUsuarioOrigen();
            $destino = $relacion->getUsuarioDestino();

            if (!$origen instanceof Usuario || !$destino instanceof Usuario) {
                continue;
            }

            $origenId = (string) $origen->getId();
            $destinoId = (string) $destino->getId();

            // Solo sincronizamos relaciones de usuarios incluidos en este Excel.
            if (!isset($usuariosImportadosIds[$origenId]) && !isset($usuariosImportadosIds[$destinoId])) {
                continue;
            }

            $key = $origenId . '|' . $destinoId . '|' . $tipoRelacion->value;
            $existentesIndex[$key] = $relacion;
        }

        foreach ($relacionesDeseadas as $key => $data) {
            if (isset($existentesIndex[$key])) {
                continue;
            }

            $relacion = new RelacionUsuario();
            $relacion->setUsuarioOrigen($data['origen']);
            $relacion->setUsuarioDestino($data['destino']);
            $relacion->setTipoRelacion($tipoRelacion);

            $this->entityManager->persist($relacion);
            $creadas++;
        }

        foreach ($existentesIndex as $key => $relacion) {
            if (isset($relacionesDeseadas[$key])) {
                continue;
            }

            $this->entityManager->remove($relacion);
            $eliminadas++;
        }

        return [
            'creadas' => $creadas,
            'eliminadas' => $eliminadas,
        ];
    }

    public function generarCodigosGrupo(array $usuarios, TipoRelacionEnum $tipo, string $prefix): array
    {
        $ids = array_map(fn (Usuario $u) => $u->getId(), $usuarios);
        $idsSet = array_flip($ids);

        $relaciones = $this->entityManager
            ->getRepository(RelacionUsuario::class)
            ->findBy(['tipoRelacion' => $tipo]);

        $graph = [];

        foreach ($ids as $id) {
            $graph[$id] = [];
        }

        foreach ($relaciones as $relacion) {
            $origen = $relacion->getUsuarioOrigen();
            $destino = $relacion->getUsuarioDestino();

            if (!$origen || !$destino) {
                continue;
            }

            $origenId = $origen->getId();
            $destinoId = $destino->getId();

            if (!isset($idsSet[$origenId], $idsSet[$destinoId])) {
                continue;
            }

            $graph[$origenId][] = $destinoId;
            $graph[$destinoId][] = $origenId;
        }

        $visited = [];
        $result = [];
        $counter = 1;

        foreach ($ids as $id) {
            if (isset($visited[$id])) {
                continue;
            }

            $component = [];
            $stack = [$id];
            $visited[$id] = true;

            while ($stack) {
                $current = array_pop($stack);
                $component[] = $current;

                foreach ($graph[$current] as $next) {
                    if (!isset($visited[$next])) {
                        $visited[$next] = true;
                        $stack[] = $next;
                    }
                }
            }

            if (count($component) < 2) {
                continue;
            }

            $codigo = sprintf('%s%03d', $prefix, $counter++);

            foreach ($component as $userId) {
                $result[$userId] = $codigo;
            }
        }

        return $result;
    }

    private function calcularEdadCumpleAnioActual(?\DateTimeInterface $fechaNacimiento): ?int
    {
        if (!$fechaNacimiento instanceof \DateTimeInterface) {
            return null;
        }

        return (int) (new \DateTimeImmutable('today'))->format('Y')
            - (int) $fechaNacimiento->format('Y');
    }
}
