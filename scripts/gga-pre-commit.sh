#!/usr/bin/env bash

set -euo pipefail

MAX_BATCH_FILES=15
MAX_BATCH_BYTES=180000

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

config_file="$repo_root/.gga"
backup_config=""

cleanup() {
  if [[ -n "$backup_config" && -f "$backup_config" ]]; then
    mv -f "$backup_config" "$config_file"
  fi
}

trap cleanup EXIT

if ! command -v gga >/dev/null 2>&1; then
  echo "[pre-commit] gga no está disponible en PATH" >&2
  exit 1
fi

mapfile -t review_files < <(
  git diff --cached --name-only --diff-filter=ACM \
    | grep -E '\.(ts|tsx|js|jsx)$' \
    | grep -Ev '(\.test\.(ts|tsx)$|\.spec\.(ts|tsx)$|\.d\.ts$)' || true
)

# Nada para revisar por GGA según FILE_PATTERNS configurado.
# En Windows, `gga run` sobre cambios masivos puede disparar `Argument list too long`
# dentro de Node/opencode aunque luego filtre por patrones.
if [[ ${#review_files[@]} -eq 0 ]]; then
  echo "[pre-commit] Sin archivos TS/JS staged para GGA; se omite gga run y se mantiene validación mínima."
  git diff --cached --check
  exit 0
fi

if [[ ! -f "$config_file" ]]; then
  echo "[pre-commit] No se encontró .gga en la raíz del repo" >&2
  exit 1
fi

backup_config="$(mktemp)"
cp "$config_file" "$backup_config"

run_gga_batch() {
  local -a batch_files=("$@")

  if [[ ${#batch_files[@]} -eq 0 ]]; then
    return 0
  fi

  local batch_patterns
  batch_patterns="$(IFS=,; echo "${batch_files[*]}")"

  awk -v patterns="$batch_patterns" '
    BEGIN { replaced = 0 }
    /^FILE_PATTERNS=/ {
      print "FILE_PATTERNS=\"" patterns "\""
      replaced = 1
      next
    }
    { print }
    END {
      if (replaced == 0) {
        print "FILE_PATTERNS=\"" patterns "\""
      }
    }
  ' "$backup_config" > "$config_file"

  gga run --no-cache
}

echo "[pre-commit] Ejecutando GGA por lotes para evitar límite de argumentos en Windows..."

batch_bytes=0
declare -a batch=()

for file in "${review_files[@]}"; do
  file_size="$(git cat-file -s ":$file" 2>/dev/null || echo 0)"

  if (( ${#batch[@]} >= MAX_BATCH_FILES || batch_bytes + file_size > MAX_BATCH_BYTES )); then
    run_gga_batch "${batch[@]}"
    batch=()
    batch_bytes=0
  fi

  batch+=("$file")
  batch_bytes=$((batch_bytes + file_size))
done

run_gga_batch "${batch[@]}"

# Mantener una validación local mínima y útil para todos los archivos staged.
git diff --cached --check
