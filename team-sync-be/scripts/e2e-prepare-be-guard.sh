#!/usr/bin/env bash
set -euo pipefail

compose_file="${1:-}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
expected="${script_dir}/e2e/docker-compose.e2e.yml"

if [[ "${compose_file}" != "${expected}" || ! -f "${compose_file}" ]]; then
  echo "Refusing E2E reset: only the approved isolated Compose target is allowed." >&2
  exit 1
fi

actual_hash="$(shasum -a 256 "${compose_file}" | awk '{print $1}')"
expected_hash="e1e8851f7da3f1a247c6ef5cec4fdf686234696e821dd5c29ffe12627bcb6aa4"
if [[ "${actual_hash}" != "${expected_hash}" ]]; then
  echo "Refusing E2E reset: isolated Compose target does not match the approved definition." >&2
  exit 1
fi
