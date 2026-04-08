param(
  [switch]$NoPause
)

$ErrorActionPreference = "Stop"

try {
  $configPath = "$env:USERPROFILE\.config\opencode\opencode.json"
  $engramPlugin = "file:///C:/Users/dario/.config/opencode/plugins/engram.ts"
  $bgPlugin = "file:///C:/Users/dario/.config/opencode/plugins/background-agents.ts"
  $engramFile = "$env:USERPROFILE\.config\opencode\plugins\engram.ts"
  $bgFile = "$env:USERPROFILE\.config\opencode\plugins\background-agents.ts"

  Write-Host "== 1) Checking opencode.json exists ==" -ForegroundColor Cyan
  if (!(Test-Path $configPath)) {
    throw "No existe: $configPath"
  }
  Write-Host "OK: $configPath" -ForegroundColor Green

  Write-Host "`n== 2) Parsing JSON ==" -ForegroundColor Cyan
  $configRaw = Get-Content $configPath -Raw
  $config = $configRaw | ConvertFrom-Json
  Write-Host "OK: JSON valido" -ForegroundColor Green

  Write-Host "`n== 3) Validating plugin section ==" -ForegroundColor Cyan
  if ($null -eq $config.plugin) {
    throw "Falta la seccion 'plugin' en opencode.json"
  }
  if ($config.plugin -isnot [System.Array]) {
    throw "'plugin' existe pero no es array"
  }
  Write-Host "OK: seccion plugin presente" -ForegroundColor Green

  Write-Host "`n== 4) Checking plugin entries ==" -ForegroundColor Cyan
  $plugins = @($config.plugin)
  $hasEngram = $plugins -contains $engramPlugin
  $hasBg = $plugins -contains $bgPlugin

  if (!$hasEngram) { throw "Falta plugin engram: $engramPlugin" }
  if (!$hasBg) { throw "Falta plugin background-agents: $bgPlugin" }

  Write-Host "OK: engram plugin presente" -ForegroundColor Green
  Write-Host "OK: background-agents plugin presente" -ForegroundColor Green

  Write-Host "`n== 5) Checking plugin files exist on disk ==" -ForegroundColor Cyan
  if (!(Test-Path $engramFile)) { throw "No existe archivo: $engramFile" }
  if (!(Test-Path $bgFile)) { throw "No existe archivo: $bgFile" }
  Write-Host "OK: archivos plugin existen" -ForegroundColor Green

  Write-Host "`n== 6) Checking subagent model prefixes ==" -ForegroundColor Cyan
  $agentProps = $config.agent.PSObject.Properties
  $badModels = @()

  foreach ($a in $agentProps) {
    $name = $a.Name
    $value = $a.Value
    if ($null -ne $value.model -and $value.model -match "^opencode/") {
      $badModels += "$name -> $($value.model)"
    }
  }

  if ($badModels.Count -gt 0) {
    Write-Host "WARN: hay agentes con modelo opencode/* (deberian ser openai/*):" -ForegroundColor Yellow
    $badModels | ForEach-Object { Write-Host " - $_" -ForegroundColor Yellow }
  } else {
    Write-Host "OK: modelos de agentes no usan prefijo opencode/*" -ForegroundColor Green
  }

  Write-Host "`n== 7) Stopping opencode processes for clean restart ==" -ForegroundColor Cyan
  $procs = Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq "node.exe" -and $_.CommandLine -match "opencode"
  }
  if ($procs) {
    $procs | ForEach-Object {
      try {
        Stop-Process -Id $_.ProcessId -Force
        Write-Host "Stopped PID $($_.ProcessId)" -ForegroundColor Yellow
      } catch {
        Write-Host "Could not stop PID $($_.ProcessId): $($_.Exception.Message)" -ForegroundColor DarkYellow
      }
    }
  } else {
    Write-Host "No opencode node processes found" -ForegroundColor Gray
  }

  Write-Host "`nVALIDACION COMPLETA" -ForegroundColor Green
  Write-Host "Ahora abri OpenCode y corre:" -ForegroundColor Cyan
  Write-Host "  opencode debug config" -ForegroundColor White
  Write-Host "Y despues corre: .\\scripts\\opencode-smoke-test.ps1" -ForegroundColor White
}
catch {
  Write-Host "`nERROR: $($_.Exception.Message)" -ForegroundColor Red
}
finally {
  if (-not $NoPause) {
    Read-Host "`nPresiona ENTER para cerrar"
  }
}
