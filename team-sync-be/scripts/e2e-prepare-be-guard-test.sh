#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
backend="${root}/team-sync-be"
guard="${backend}/scripts/e2e-prepare-be-guard.sh"
tmp="$(mktemp -d)"
trap 'rm -rf "${tmp}"' EXIT

expect_refusal() {
  if "$@"; then echo "Expected refusal: $*" >&2; exit 1; fi
}

expect_refusal bash "${guard}" "${tmp}/missing.yml"
expect_refusal bash "${guard}" "${backend}/docker-compose.yml"
expect_refusal bash "${guard}" "${backend}/e2e/altered.yml"

guard_root="${tmp}/backend"
mkdir -p "${guard_root}/scripts" "${guard_root}/e2e"
cp "${guard}" "${guard_root}/scripts/e2e-prepare-be-guard.sh"
cp "${backend}/e2e/docker-compose.e2e.yml" "${guard_root}/e2e/docker-compose.e2e.yml"
bash "${guard_root}/scripts/e2e-prepare-be-guard.sh" "${guard_root}/e2e/docker-compose.e2e.yml"
printf '\n# altered definition\n' >> "${guard_root}/e2e/docker-compose.e2e.yml"
expect_refusal bash "${guard_root}/scripts/e2e-prepare-be-guard.sh" "${guard_root}/e2e/docker-compose.e2e.yml"

cp "${backend}/e2e/docker-compose.e2e.yml" "${tmp}/copy.yml"
expect_refusal bash "${guard}" "${tmp}/copy.yml"
bash "${guard}" "${backend}/e2e/docker-compose.e2e.yml"

wrapper="${root}/team-sync-fe/scripts/e2e-prepare-be.sh"
if ! (cd "${root}/team-sync-fe" && E2E_BE_DIR="${backend}" E2E_PREPARE_PREFLIGHT_ONLY=1 bash "${wrapper}"); then
  echo "Expected wrapper preflight acceptance for isolated target" >&2
  exit 1
fi

echo "E2E reset guard checks passed"

