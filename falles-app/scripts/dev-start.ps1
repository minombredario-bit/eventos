<#
Starts the local development environment after the initial setup.

Usage:
  .\scripts\dev-start.ps1
  .\scripts\dev-start.ps1 -FrontendMode net
#>

[CmdletBinding()]
param(
    [ValidateSet('local', 'net')]
    [string]$FrontendMode = 'local'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Path $PSCommandPath -Parent
$projectRoot = Split-Path -Path $scriptDir -Parent
$frontendRoot = Join-Path $projectRoot 'frontend'
$composeFile = Join-Path $projectRoot 'docker-compose.yml'
$composeProject = 'falles-app'

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

Write-Host '[dev-start] Levantando backend con Docker Compose'
Invoke-Compose @('up', '-d')

$npmScript = if ($FrontendMode -eq 'net') { 'start:net' } else { 'start:local' }
$frontendCommand = "Set-Location '$frontendRoot'; npm run $npmScript"

Write-Host "[dev-start] Abriendo el frontend en una nueva ventana con '$npmScript'"
Start-Process powershell.exe -ArgumentList @('-NoExit', '-ExecutionPolicy', 'Bypass', '-Command', $frontendCommand) | Out-Null

Write-Host '[dev-start] Listo.'
Write-Host '  Backend:  http://localhost:8080'
Write-Host '  Frontend: http://localhost:4250'

