#!/usr/bin/env bash

set -euo pipefail

backend_dir="${E2E_BE_DIR:-../team-sync-be}"
compose_cmd_string="${E2E_BE_COMPOSE_CMD:-docker compose}"

if [[ ! -d "${backend_dir}" ]]; then
  echo "Backend directory not found: ${backend_dir}" >&2
  exit 1
fi

read -r -a compose_cmd <<< "${compose_cmd_string}"

cd "${backend_dir}"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker CLI is not installed or not available in PATH." >&2
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  echo "Docker daemon is not reachable. Start Docker/OrbStack first, then rerun e2e." >&2
  exit 1
fi

if ! "${compose_cmd[@]}" version >/dev/null 2>&1; then
  echo "Docker Compose command is not available: ${compose_cmd_string}" >&2
  exit 1
fi

wait_for_service_health() {
  local service="$1"
  local container_id
  local status

  container_id="$("${compose_cmd[@]}" ps -q "${service}")"
  if [[ -z "${container_id}" ]]; then
    echo "Container for ${service} is not running" >&2
    exit 1
  fi

  for attempt in {1..30}; do
    status="$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "${container_id}")"
    if [[ "${status}" == "healthy" || "${status}" == "none" ]]; then
      return 0
    fi

    if [[ "${attempt}" -eq 30 ]]; then
      echo "${service} did not become healthy in time" >&2
      docker inspect "${container_id}" >&2 || true
      exit 1
    fi

    sleep 2
  done
}

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

if ! grep -q '^DB_ROOT_PASSWORD=' .env; then
  echo 'DB_ROOT_PASSWORD=rootpass' >> .env
fi

"${compose_cmd[@]}" up -d mysql redis web

wait_for_service_health mysql
wait_for_service_health redis

if ! grep -q '^APP_KEY=base64:' .env; then
  "${compose_cmd[@]}" exec -T web php artisan key:generate --force
fi

"${compose_cmd[@]}" exec -T web php artisan migrate:fresh
"${compose_cmd[@]}" exec -T web php artisan db:seed --class=MinimalPayrollE2ESeeder
