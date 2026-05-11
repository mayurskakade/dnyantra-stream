#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

matches="$(rg -n --glob '*.php' -e '->(query|exec)\(' app routes || true)"
if [[ -n "$matches" ]]; then
  echo "SQL audit failed: direct PDO query()/exec() usage found in app/routes."
  echo "$matches"
  exit 1
fi

echo "SQL audit passed: no direct PDO query()/exec() usage found in app/routes."
