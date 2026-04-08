# Skill: Backend — Symfony 7.3 + API Platform 4

Lee este archivo completo antes de generar cualquier código de backend.

---

## Stack y versiones

| Paquete | Versión |
|---|---|
| PHP | 8.3+ |
| Symfony | 7.x |
| API Platform | 4.x |
| LexikJWTAuthenticationBundle | 3.x |
| PhpSpreadsheet | 2.x |
| DomPDF | 2.x |
| Doctrine ORM | 3.x |
| PHPUnit | 11.x |

---

## Estructura de directorios

```
backend/src/
├── Entity/               # Entidades Doctrine
├── Enum/                 # PHP 8.1 backed enums
├── Repository/           # Repositorios custom
├── Service/              # Lógica de negocio (PriceCalculator, CensoImporter, etc.)
├── Controller/           # Controllers no-API-Platform (reportes, imports)
├── ApiResource/          # State processors y providers custom
├── Security/             # Voters y authenticators
├── EventSubscriber/      # Suscriptores de eventos Symfony/Doctrine
├── DataFixtures/         # Fixtures de desarrollo
└── Dto/                  # DTOs para inputs/outputs de API Platform
```

---

## Entidades principales

Genera cada entidad en `src/Entity/`. Usa atributos PHP, no YAML ni XML.

### Convenciones de entidad

```php
<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Enum\EstadoValidacionEnum;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'usuario')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['usuario:read']],
    denormalizationContext: ['groups' => ['usuario:write']],
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new GetCollection(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Post(security: "is_granted('ROLE_SUPERADMIN')"),
        new Patch(security: "is_granted('EDIT', object)"),
    ]
)]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['usuario:read'])]
    private ?string $id = null;

    // ... propiedades

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

### Enums (uno por archivo en src/Enum/)

```php
<?php

namespace App\Enum;

enum EstadoValidacionEnum: string
{
    case PENDIENTE_VALIDACION = 'pendiente_validacion';
    case VALIDADO = 'validado';
    case RECHAZADO = 'rechazado';
    case BLOQUEADO = 'bloqueado';

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE_VALIDACION => 'Pendiente de validación',
            self::VALIDADO => 'Validado',
            self::RECHAZADO => 'Rechazado',
            self::BLOQUEADO => 'Bloqueado',
        };
    }
}
```

Enums a crear:

- `TipoEntidadEnum` (FALLA, COMPARSA, PENYA, HERMANDAD, ASOCIACION, CLUB, OTRO)
- `TipoEventoEnum` (ALMUERZO, COMIDA, MERIENDA, CENA, OTRO)
- `TipoPersonaEnum` (ADULTO, INFANTIL)
- `TipoRelacionEconomicaEnum` (INTERNO, EXTERNO, INVITADO)
- `EstadoValidacionEnum` (PENDIENTE_VALIDACION, VALIDADO, RECHAZADO, BLOQUEADO)
- `TipoMenuEnum` (ADULTO, INFANTIL, ESPECIAL, LIBRE)
- `EstadoEventoEnum` (BORRADOR, PUBLICADO, CERRADO, FINALIZADO, CANCELADO)
- `EstadoInscripcionEnum` (PENDIENTE, CONFIRMADA, CANCELADA, LISTA_ESPERA)
- `EstadoPagoEnum` (NO_REQUIERE_PAGO, PENDIENTE, PARCIAL, PAGADO, DEVUELTO, CANCELADO)
- `MetodoPagoEnum` (EFECTIVO, TRANSFERENCIA, BIZUM, TPV, ONLINE, MANUAL)
- `EstadoLineaInscripcionEnum` (PENDIENTE, CONFIRMADA, CANCELADA)
- `CensadoViaEnum` (EXCEL, MANUAL, INVITACION)

---

## Servicios críticos

### PriceCalculatorService

Este es el servicio más importante del sistema. Reside en `src/Service/PriceCalculatorService.php`.

Reglas que debe implementar:

```
1. Si esDePago = false → precio = 0.00
2. Si esDePago = true:
   a. Si tipoMenu = INFANTIL → aplicar precioInfantil (sin importar tipo de persona)
   b. Si tipoMenu = ADULTO o ESPECIAL:
      - Si tipoRelacionEconomica = INTERNO y estadoValidacion = VALIDADO → precioAdultoInterno
      - Cualquier otro caso → precioAdultoExterno
   c. Fallback: precioBase si el precio específico es null
3. Nunca retornar null; siempre retornar decimal(8,2)
```

```php
<?php

namespace App\Service;

use App\Entity\MenuEvento;
use App\Entity\PersonaFamiliar;
use App\Enum\TipoMenuEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\EstadoValidacionEnum;

class PriceCalculatorService
{
    public function calcularPrecio(MenuEvento $menu, PersonaFamiliar $persona): float
    {
        if (!$menu->isEsDePago()) {
            return 0.00;
        }

        if ($menu->getTipoMenu() === TipoMenuEnum::INFANTIL) {
            return (float) ($menu->getPrecioInfantil() ?? $menu->getPrecioBase());
        }

        $esInterno = $persona->getTipoRelacionEconomica() === TipoRelacionEconomicaEnum::INTERNO
            && $persona->getEstadoValidacion() === EstadoValidacionEnum::VALIDADO;

        if ($esInterno) {
            return (float) ($menu->getPrecioAdultoInterno() ?? $menu->getPrecioBase());
        }

        return (float) ($menu->getPrecioAdultoExterno() ?? $menu->getPrecioBase());
    }

    public function calcularTotalInscripcion(array $lineas): float
    {
        return array_reduce($lineas, fn($carry, $linea) => $carry + $linea->getPrecioUnitario(), 0.00);
    }
}
```

### CensoImporterService

Reside en `src/Service/CensoImporterService.php`. Usa PhpSpreadsheet.

Responsabilidades:

- leer el Excel fila por fila
- normalizar strings (trim, lowercase para matching)
- validar columnas obligatorias (nombre, apellidos)
- crear entidades `CensoEntrada` para la entidad
- devolver estadísticas: total leídas, insertadas, con errores

Columnas esperadas en el Excel (orden flexible, detectar por cabecera):

```
nombre | apellidos | email | dni | parentesco | tipo_persona | tipo_relacion
```

### CensoMatcherService

Reside en `src/Service/CensoMatcherService.php`.

```php
public function buscarCoincidencia(Entidad $entidad, string $email, ?string $dni): MatchResult
{
    // 1. Buscar por email (case-insensitive, sin tildes)
    // 2. Si no hay resultado, buscar por DNI si se proporcionó
    // 3. Si hay más de una coincidencia → MatchResult::MULTIPLE
    // 4. Si hay exactamente una → MatchResult::FOUND con la entidad
    // 5. Si no hay ninguna → MatchResult::NOT_FOUND
}
```

### InscripcionService

Reside en `src/Service/InscripcionService.php`.

Responsabilidades:

- validar que el evento está abierto
- validar que cada persona pertenece al usuario autenticado
- validar que cada menú pertenece al evento y está activo
- validar que no hay duplicado de persona en el evento
- calcular precio de cada línea usando `PriceCalculatorService`
- crear snapshots en cada `InscripcionLinea`
- calcular `importeTotal`
- determinar `estadoPago` automáticamente
- generar código único de inscripción

### CredencialService

Reside en `src/Service/CredencialService.php`.

Responsabilidades:

- verificar que la inscripción está confirmada
- verificar que el evento requiere credencial
- verificar que la hora actual (UTC, tomada del servidor) está dentro de la ventana configurada
- verificar que el pago no bloquea la credencial
- generar token temporal firmado (válido para esa ventana temporal)

---

## Endpoints personalizados (fuera de API Platform CRUD)

Usa controllers Symfony normales para operaciones que no encajan en REST puro.

```php
// src/Controller/Admin/ValidarUsuarioController.php

#[Route('/api/admin/usuarios/{id}/validar', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN_ENTIDAD')]
public function validar(Usuario $usuario, EntityManagerInterface $em): JsonResponse
{
    // 1. Comprobar que el usuario pertenece a la entidad del admin (voter)
    // 2. Cambiar estadoValidacion a VALIDADO
    // 3. Registrar validadoPor y fechaValidacion
    // 4. Guardar
    // 5. Retornar 200 con el usuario serializado
}
```

---

## Seguridad y voters

Crear voters en `src/Security/Voter/`:

- `EntidadVoter` — verifica que el recurso pertenece a la entidad del admin autenticado
- `InscripcionVoter` — verifica que la inscripción pertenece al usuario autenticado
- `PersonaFamiliarVoter` — verifica que el familiar pertenece al usuario autenticado

Ejemplo de voter:

```php
<?php

namespace App\Security\Voter;

use App\Entity\Evento;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EntidadVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['VIEW', 'EDIT', 'DELETE'])
            && $subject instanceof Evento;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var Evento $evento */
        $evento = $subject;

        return match($attribute) {
            'VIEW' => true, // eventos publicados son visibles para todos
            'EDIT', 'DELETE' => $this->puedeEditar($evento, $user),
            default => false,
        };
    }

    private function puedeEditar(Evento $evento, Usuario $user): bool
    {
        return $user->getEntidad() === $evento->getEntidad()
            && in_array('ROLE_ADMIN_ENTIDAD', $user->getRoles());
    }
}
```

---

## Importación del censo (Excel)

Columnas requeridas en el Excel de censo:

| Columna | Obligatoria | Valor por defecto |
|---|---|---|
| nombre | sí | — |
| apellidos | sí | — |
| email | no | null |
| dni | no | null |
| parentesco | no | "otro" |
| tipo_persona | no | "adulto" |
| tipo_relacion | no | "interno" |

El endpoint de importación debe:

1. recibir el archivo multipart/form-data
2. validar que es .xlsx o .xls
3. leer la cabecera y mapear columnas por nombre (no por posición)
4. procesar fila a fila, capturando errores por fila sin abortar el proceso
5. retornar un resumen: `{ total: 150, insertadas: 148, errores: [{ fila: 23, motivo: "..." }] }`

---

## Generación de reportes

### Excel (PhpSpreadsheet)

```php
// src/Service/Reporte/ReporteMenuService.php

public function generarExcel(Evento $evento): Response
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Cabecera
    $sheet->fromArray(['Menú', 'Adultos', 'Infantiles', 'Total'], null, 'A1');

    // Datos agrupados por menú
    // ...

    $writer = new Xlsx($spreadsheet);
    $response = new StreamedResponse(fn() => $writer->save('php://output'));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', 'attachment; filename="reporte-menu.xlsx"');

    return $response;
}
```

### PDF (DomPDF)

```php
// src/Service/Reporte/ReportePdfService.php

public function generarPdf(Evento $evento, string $tipoReporte): Response
{
    $html = $this->twig->render('reporte/' . $tipoReporte . '.html.twig', [
        'evento' => $evento,
        'datos' => $this->getDatos($evento, $tipoReporte),
    ]);

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    return new Response(
        $dompdf->output(),
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte.pdf"',
        ]
    );
}
```

---

## Fixtures de desarrollo

Crear en `src/DataFixtures/`:

- `FallaFixtures` — 2 entidades de ejemplo
- `CensoFixtures` — 20 entradas de censo por entidad
- `UsuarioFixtures` — superadmin, 2 admins de entidad, 10 usuarios por entidad
- `EventoFixtures` — 5 eventos por entidad con distintos estados
- `MenuFixtures` — 2-3 menús por evento
- `InscripcionFixtures` — inscripciones variadas

Usar `Alice` (hautelook/alice-bundle) si se prefiere YAML, o AppFixtures con dependencias entre fixtures.

---

## Tests

Estructura de tests:

```
backend/tests/
├── Unit/
│   ├── Service/PriceCalculatorServiceTest.php
│   ├── Service/CensoMatcherServiceTest.php
│   └── Service/InscripcionServiceTest.php
├── Functional/
│   ├── Api/RegistroTest.php
│   ├── Api/InscripcionTest.php
│   └── Api/AdminValidacionTest.php
└── bootstrap.php
```

Los tests de servicio son unitarios (sin base de datos). Los tests de API usan `ApiTestCase` de API Platform con base de datos SQLite en memoria.

---

## Migraciones

```bash
# Generar migración tras cambiar entidades
php bin/console doctrine:migrations:diff

# Aplicar migraciones
php bin/console doctrine:migrations:migrate

# Estado actual
php bin/console doctrine:migrations:status
```

Nunca editar migraciones ya ejecutadas. Crear siempre una nueva.

---

## Checklist antes de hacer PR

- [ ] Todos los precios se calculan en `PriceCalculatorService`, nunca en el controller
- [ ] Los endpoints de admin comprueban que el recurso pertenece a la entidad del usuario (voter)
- [ ] Los endpoints de superadmin requieren `ROLE_SUPERADMIN`
- [ ] Los snapshots de `InscripcionLinea` se rellenan en el momento de la inscripción
- [ ] La hora del servidor se usa para validar credenciales, nunca la del cliente
- [ ] Las migraciones están generadas y revisadas
- [ ] Los tests unitarios de servicios críticos están actualizados
