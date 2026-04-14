<#
Bootstraps the project on a new Windows machine.

What it does:
- starts Docker services
- fixes backend permissions inside the PHP container
- installs backend dependencies and runs migrations
- generates JWT keys only if missing (or overwrites with -ForceGenerateJwt)
- creates development users
- installs frontend dependencies
- optionally runs a frontend production build as a smoke test

Usage:
  Set-Location C:\Users\te0162\PhpstormProjects\eventos\falles-app
  .\scripts\dev-setup.ps1

  # faster setup without frontend build smoke test
  .\scripts\dev-setup.ps1 -SkipFrontendBuild

  # regenerate JWT keys only when you really want to replace the existing pair
  .\scripts\dev-setup.ps1 -ForceGenerateJwt
#>

[CmdletBinding()]
param(
    [switch]$ForceGenerateJwt,
    [switch]$SkipFrontendInstall,
    [switch]$SkipFrontendBuild
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:ScriptDir = Split-Path -Path $PSCommandPath -Parent
$script:ProjectRoot = Split-Path -Path $script:ScriptDir -Parent
$script:FrontendRoot = Join-Path $script:ProjectRoot 'frontend'
$script:BackendRoot = Join-Path $script:ProjectRoot 'backend'
$script:ComposeFile = Join-Path $script:ProjectRoot 'docker-compose.yml'
$script:ComposeProject = 'falles-app'

function Assert-CommandExists {
    param([Parameter(Mandatory = $true)][string]$CommandName)

    if (-not (Get-Command $CommandName -ErrorAction SilentlyContinue)) {
        throw "No se encuentra '$CommandName' en PATH. Instálalo antes de continuar."
    }
}

function Invoke-External {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [string]$WorkingDirectory = $script:ProjectRoot
    )

    Push-Location $WorkingDirectory
    try {
        Write-Host ("Running: {0} {1}" -f $FilePath, ($Arguments -join ' '))
        & $FilePath @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "El comando falló con código ${LASTEXITCODE}: ${FilePath} $($Arguments -join ' ')"
        }
    }
    finally {
        Pop-Location
    }
}

function Invoke-Compose {
    param([Parameter(Mandatory = $true)][string[]]$ComposeArguments)

    Invoke-External -FilePath 'docker' -Arguments (@('compose', '-p', $script:ComposeProject, '-f', $script:ComposeFile) + $ComposeArguments) -WorkingDirectory $script:ProjectRoot
}

function Invoke-InPhp {
    param([Parameter(Mandatory = $true)][string]$Command)

    Write-Host "-> inside php: $Command"
    Invoke-Compose @('exec', 'php', 'bash', '-lc', $Command)
}

function Test-InPhp {
    param([Parameter(Mandatory = $true)][string]$Command)

    Push-Location $script:ProjectRoot
    try {
        & docker compose -p $script:ComposeProject -f $script:ComposeFile exec php bash -lc $Command
        return $LASTEXITCODE -eq 0
    }
    finally {
        Pop-Location
    }
}

try {
    Write-Host "[dev-setup] Preparando proyecto en $script:ProjectRoot"

    Assert-CommandExists 'docker'
    if (-not $SkipFrontendInstall -or -not $SkipFrontendBuild) {
        Assert-CommandExists 'npm'
    }

    if (-not (Test-Path $script:BackendRoot)) {
        throw "No existe el directorio backend esperado: $script:BackendRoot"
    }

    if (-not (Test-Path $script:ComposeFile)) {
        throw "No existe docker-compose.yml en la raíz esperada: $script:ComposeFile"
    }

    if (-not (Test-Path $script:FrontendRoot)) {
        throw "No existe el directorio frontend esperado: $script:FrontendRoot"
    }

    Write-Host "[dev-setup] Levantando contenedores"
    Invoke-Compose @('up', '-d')

    Write-Host "[dev-setup] Corrigiendo permisos de var/ en el contenedor PHP"
    Invoke-Compose @('exec', '--user', 'root', 'php', 'bash', '-lc', '/var/www/html/scripts/fix_var_permissions.sh')

    Write-Host "[dev-setup] Instalando dependencias PHP"
    Invoke-InPhp 'composer install --no-interaction --prefer-dist --optimize-autoloader'

    Write-Host "[dev-setup] Ejecutando migraciones"
    Invoke-InPhp 'php bin/console doctrine:migrations:migrate --no-interaction'

    Write-Host "[dev-setup] Comprobando claves JWT"
    if ((Test-InPhp 'test -f /var/www/html/config/jwt/private.pem && test -f /var/www/html/config/jwt/public.pem') -and -not $ForceGenerateJwt) {
        Write-Host '[dev-setup] Las claves JWT ya existen; no se regeneran.'
    }
    else {
        if ($ForceGenerateJwt) {
            Write-Host '[dev-setup] Regenerando claves JWT con overwrite.'
            Invoke-InPhp 'php bin/console lexik:jwt:generate-keypair --overwrite'
        }
        else {
            Write-Host '[dev-setup] Generando claves JWT porque no existen.'
            Invoke-InPhp 'php bin/console lexik:jwt:generate-keypair'
        }
    }

    Write-Host '[dev-setup] Creando usuarios de desarrollo'
    Invoke-InPhp 'php bin/console app:populate-usuarios --env=prod'

    Write-Host '[dev-setup] Verificando usuarios creados'
    Invoke-InPhp 'php /var/www/html/tmp/check_users.php'

    if (-not $SkipFrontendInstall) {
        Write-Host '[dev-setup] Instalando dependencias del frontend'
        Invoke-External -FilePath 'npm' -Arguments @('install') -WorkingDirectory $script:FrontendRoot
    }

    if (-not $SkipFrontendBuild) {
        Write-Host '[dev-setup] Ejecutando build del frontend como smoke test'
        Invoke-External -FilePath 'npm' -Arguments @('run', 'build') -WorkingDirectory $script:FrontendRoot
    }

    Write-Host ''
    Write-Host '[dev-setup] Proyecto preparado.'
    Write-Host '  Backend:  http://localhost:8080'
    Write-Host '  Frontend: http://localhost:4200 (arráncalo con .\scripts\dev-start.ps1 o npm start)'
    Write-Host '  Usuarios demo:'
    Write-Host '    - ana.gonzalez@example.com / password'
    Write-Host '    - luis.martinez@example.com / password'
    Write-Host '    - sofia.perez@example.com / password'
    exit 0
}
catch {
    Write-Error "[dev-setup] $_"
    exit 1
}

