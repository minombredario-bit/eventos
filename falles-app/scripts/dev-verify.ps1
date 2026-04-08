<#
Quick verification script for a freshly prepared local environment.
#>

[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Path $PSCommandPath -Parent
$projectRoot = Split-Path -Path $scriptDir -Parent
$composeFile = Join-Path $projectRoot 'docker-compose.yml'
$composeProject = 'falles-app'
$backendWorkingDir = Join-Path $projectRoot 'backend'

function Invoke-External {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [string]$WorkingDirectory = $projectRoot
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

    Invoke-External -FilePath 'docker' -Arguments (@('compose', '-p', $composeProject, '-f', $composeFile) + $ComposeArguments) -WorkingDirectory $projectRoot
}

if (-not (Test-Path $composeFile)) {
    throw "No existe docker-compose.yml en la raíz esperada: $composeFile"
}

Push-Location $projectRoot
try {
    $legacyBackendDb = (& docker ps -a --filter "label=com.docker.compose.project=backend" --filter "label=com.docker.compose.project.working_dir=$backendWorkingDir" --filter "label=com.docker.compose.service=database" --format "{{.Names}}")
    if ($LASTEXITCODE -eq 0 -and $legacyBackendDb) {
        Write-Warning "Detectado contenedor legacy del compose backend: $($legacyBackendDb -join ', '). Usa solo scripts/dev-setup.ps1 y scripts/dev-start.ps1 desde falles-app/."
    }
}
finally {
    Pop-Location
}

Write-Host '[dev-verify] Estado de contenedores'
Invoke-Compose @('ps')

Write-Host '[dev-verify] Estado de migraciones'
Invoke-Compose @('exec', 'php', 'bash', '-lc', 'php bin/console doctrine:migrations:status --no-interaction')

Write-Host '[dev-verify] Claves JWT disponibles'
Invoke-Compose @('exec', 'php', 'bash', '-lc', 'ls -1 /var/www/html/config/jwt')

Write-Host '[dev-verify] Usuarios demo presentes'
Invoke-Compose @('exec', 'php', 'bash', '-lc', 'php /var/www/html/tmp/check_users.php')

Write-Host '[dev-verify] Frontend instalado'
Invoke-External -FilePath 'npm' -Arguments @('list', '--depth=0') -WorkingDirectory (Join-Path $projectRoot 'frontend')

