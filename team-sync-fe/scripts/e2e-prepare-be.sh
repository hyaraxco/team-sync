#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_dir="$(cd "${script_dir}/../.." && pwd)"
backend_dir="${E2E_BE_DIR:-${repo_dir}/team-sync-be}"
if [[ ! -d "${backend_dir}" ]]; then echo "Backend directory not found: ${backend_dir}" >&2; exit 1; fi
cd "${backend_dir}"
compose_file="${PWD}/e2e/docker-compose.e2e.yml"
"${PWD}/scripts/e2e-prepare-be-guard.sh" "${compose_file}"
[[ "${E2E_PREPARE_PREFLIGHT_ONLY:-0}" != 1 ]] || exit 0
read -r -a compose_cmd <<< "${E2E_BE_COMPOSE_CMD:-docker compose}"
compose_args=(--env-file /dev/null -p teamsync-e2e-isolated -f "${compose_file}")
command -v docker >/dev/null && docker info >/dev/null 2>&1 || { echo "Docker CLI or daemon unavailable." >&2; exit 1; }
"${compose_cmd[@]}" "${compose_args[@]}" version >/dev/null 2>&1 || { echo "Docker Compose unavailable." >&2; exit 1; }
wait_for_service_health() {
  local container_id status
  container_id="$("${compose_cmd[@]}" "${compose_args[@]}" ps -q "$1")"
  [[ -n "${container_id}" ]] || { echo "Container for $1 is not running" >&2; exit 1; }
  for attempt in {1..30}; do
    status="$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "${container_id}")"
    [[ "${status}" == healthy || "${status}" == none ]] && return 0
    [[ "$attempt" -lt 30 ]] || { echo "$1 did not become healthy in time" >&2; exit 1; }
    sleep 2
  done
}
"${compose_cmd[@]}" "${compose_args[@]}" up -d mysql redis web
wait_for_service_health mysql
wait_for_service_health redis
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan migrate:fresh
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan cache:clear
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan db:seed --class=MinimalPayrollE2ESeeder
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan db:seed --class=DemoDataSeeder
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan attendance-periods:sync
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan tinker --execute="
use App\Models\AttendancePeriod;
use App\Models\StaffMemberProfile;
use App\Models\Attendance;
\$prev = now()->subMonth()->startOfMonth();
AttendancePeriod::firstOrCreate(['start_date' => \$prev->toDateString(), 'end_date' => \$prev->copy()->endOfMonth()->toDateString()], ['cutoff_date' => \$prev->copy()->day(25)->toDateString(), 'status' => 'review']);
AttendancePeriod::where('start_date', \$prev->toDateString())->update(['status' => 'review']);
\$cur = now()->startOfMonth();
AttendancePeriod::where('start_date', \$cur->toDateString())->update(['status' => 'open']);
\$ids = StaffMemberProfile::whereHas('jobInformation', fn(\$q) => \$q->where('status','active'))->pluck('id');
foreach (\$ids as \$id) for (\$d = \$prev->copy(); \$d->lte(\$prev->copy()->endOfMonth()); \$d->addDay()) if (\$d->isWeekday()) Attendance::firstOrCreate(['staff_member_id' => \$id, 'date' => \$d->toDateString()], ['check_in' => \$d->copy()->setTime(8,0)->toDateTimeString(), 'check_out' => \$d->copy()->setTime(17,0)->toDateTimeString(), 'status' => 'present', 'worked_minutes' => 540]);
foreach ([\$prev, \$cur] as \$period) app(\Database\Seeders\BpjsPayrollFixtureSeeder::class)->run(\$period->format('Y-m'));
"
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan db:seed --class=PerformanceReviewSectionSeeder
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan db:seed --class=PerformanceOutcomeRuleSeeder
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan db:seed --class=PerformanceReviewTemplateSeeder
"${compose_cmd[@]}" "${compose_args[@]}" exec -T web php artisan db:seed --class=PerformanceDataSeeder
