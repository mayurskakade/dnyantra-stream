#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

fail=0

tracked_env="$(git ls-files | rg '(^|/)\.env$' || true)"
if [[ -n "$tracked_env" ]]; then
  echo "Secrets audit failed: tracked .env files detected."
  echo "$tracked_env"
  fail=1
fi

matches="$(rg -n -I \
  --glob '!vendor/**' \
  --glob '!.git/**' \
  --glob '!tests/**' \
  --glob '!.env.example' \
  --glob '!.env' \
  -e 'AKIA[0-9A-Z]{16}' \
  -e 'ghp_[A-Za-z0-9]{36}' \
  -e 'xox[baprs]-[A-Za-z0-9-]{10,}' \
  -e 'eyJ[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{10,}' \
  . || true)"

if [[ -n "$matches" ]]; then
  echo "Secrets audit failed: suspicious high-entropy token patterns detected."
  echo "$matches"
  fail=1
fi

private_key_hits="$(rg -n -I \
  --glob '!vendor/**' \
  --glob '!.git/**' \
  --glob '!tests/**' \
  --glob '!.env.example' \
  --glob '!.env' \
  'BEGIN (RSA |EC |OPENSSH |DSA )?PRIVATE KEY' \
  . || true)"

if [[ -n "$private_key_hits" ]]; then
  echo "Secrets audit failed: private key block detected in tracked source."
  echo "$private_key_hits"
  fail=1
fi

if [[ "$fail" -ne 0 ]]; then
  exit 1
fi

echo "Secrets audit passed: no tracked .env files or obvious secret patterns detected."
