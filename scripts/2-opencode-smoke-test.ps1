param(
  [switch]$NoPause
)

$ErrorActionPreference = "Stop"

try {
  $logDir = "$env:USERPROFILE\.local\share\opencode\log"
  $patterns = @(
    "Cannot call a class constructor TimeoutError",
    "ProviderModelNotFoundError",
    "Unexpected EOF",
    "failed to load plugin",
    "No assistant messages found",
    "Session not found"
  )

  Write-Host "== Smoke 1) CLI available ==" -ForegroundColor Cyan
  $version = opencode --version
  Write-Host "opencode version: $version" -ForegroundColor Green

  Write-Host "`n== Smoke 2) Config resolves ==" -ForegroundColor Cyan
  $configText = opencode debug config
  try {
    $config = $configText | ConvertFrom-Json
    Write-Host "OK: debug config parseable JSON" -ForegroundColor Green
  } catch {
    Write-Host "WARN: debug config no parseo como JSON, pero comando respondio." -ForegroundColor Yellow
  }

  Write-Host "`n== Smoke 3) Plugin list contains expected entries ==" -ForegroundColor Cyan
  if ($config -and $config.plugin) {
    $plugins = @($config.plugin)
    $hasEngram = $plugins -contains "file:///C:/Users/dario/.config/opencode/plugins/engram.ts"
    $hasBg = $plugins -contains "file:///C:/Users/dario/.config/opencode/plugins/background-agents.ts"

    if ($hasEngram -and $hasBg) {
      Write-Host "OK: Plugins engram + background-agents activos" -ForegroundColor Green
    } else {
      Write-Host "WARN: plugin list incompleta" -ForegroundColor Yellow
      $plugins | ForEach-Object { Write-Host " - $_" }
    }
  } else {
    Write-Host "WARN: no pude verificar plugins desde debug config" -ForegroundColor Yellow
  }

  Write-Host "`n== Smoke 4) Recent log scan for critical errors ==" -ForegroundColor Cyan
  if (!(Test-Path $logDir)) {
    Write-Host "WARN: no existe directorio de logs: $logDir" -ForegroundColor Yellow
    exit 0
  }

  $latest = Get-ChildItem $logDir -File | Sort-Object LastWriteTime -Descending | Select-Object -First 1
  if ($null -eq $latest) {
    Write-Host "WARN: no hay logs para inspeccionar" -ForegroundColor Yellow
    exit 0
  }

  Write-Host "Analizando log: $($latest.FullName)" -ForegroundColor Gray
  $tail = Get-Content $latest.FullName -Tail 500
  $hits = @()
  foreach ($p in $patterns) {
    $m = $tail | Select-String -Pattern $p
    if ($m) { $hits += $m }
  }

  if ($hits.Count -gt 0) {
    Write-Host "FAIL: Se detectaron errores criticos recientes:" -ForegroundColor Red
    $hits | Select-Object -First 20 | ForEach-Object { Write-Host " - $($_.Line.Trim())" -ForegroundColor Red }
    Write-Host "`nSugerencia: desactivar temporalmente background-agents.ts y reiniciar OpenCode." -ForegroundColor Yellow
    exit 1
  }

  Write-Host "OK: No se detectaron errores criticos en el tail del log" -ForegroundColor Green
  Write-Host "`nSMOKE TEST TECNICO COMPLETADO." -ForegroundColor Green
  Write-Host "Proximo paso manual recomendado: correr un task/delegate minimo desde la UI." -ForegroundColor Cyan
}
catch {
  Write-Host "`nERROR: $($_.Exception.Message)" -ForegroundColor Red
}
finally {
  if (-not $NoPause) {
    Read-Host "`nPresiona ENTER para cerrar"
  }
}
