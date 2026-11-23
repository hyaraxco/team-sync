#!/usr/bin/env bash
set -euo pipefail

if [[ ${1:-} != --isolated ]]; then
    [[ $# == 1 ]] || { echo 'Usage: suite.sh be|fe|build-be|build-fe|a11y|roles' >&2; exit 64; }
    exec env -i PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
        HOME=/scratch/home TMPDIR=/scratch/tmp TMP=/scratch/tmp TEMP=/scratch/tmp \
        XDG_CACHE_HOME=/scratch/cache XDG_CONFIG_HOME=/scratch/config XDG_DATA_HOME=/scratch/data \
        BUN_INSTALL_CACHE_DIR=/scratch/cache/bun COMPOSER_CACHE_DIR=/scratch/cache/composer \
        PLAYWRIGHT_BROWSERS_PATH=/opt/browsers PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
        VITEST_SKIP_INSTALL_CHECKS=1 \
        CI=1 VITE_CACHE_DIR=/scratch/cache/vite TZ=UTC LANG=C.UTF-8 LC_ALL=C.UTF-8 \
        APP_ENV=testing APP_DEBUG=false APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
        APP_URL=http://127.0.0.1:8000 APP_MAINTENANCE_DRIVER=file \
        DB_CONNECTION=sqlite DB_DATABASE=/scratch/database.sqlite \
        MAIL_MAILER=array QUEUE_CONNECTION=sync SCOUT_DRIVER=null CACHE_STORE=array \
        SESSION_DRIVER=file SESSION_SECURE_COOKIE=false SANCTUM_STATEFUL_DOMAINS=127.0.0.1:4173 \
        BCRYPT_ROUNDS=4 LOG_CHANNEL=stderr LOG_LEVEL=warning TELESCOPE_ENABLED=false \
        PULSE_ENABLED=false NIGHTWATCH_ENABLED=false XDEBUG_MODE=coverage \
        VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1 E2E_FE_BASE_URL=http://127.0.0.1:4173 \
        E2E_BE_DIR=/source/team-sync-be E2E_BE_COMPOSE_CMD='bash /scratch/backend-exec.sh' \
        bash "$0" --isolated "$1"
fi
[[ $# == 2 ]] || exit 64
phase=$2
case "$phase" in be|fe|build-be|build-fe|a11y|roles) ;; *) exit 64 ;; esac
umask 077
mkdir -p /scratch/{home,tmp,cache,config,data,reports,results}
mkdir -p /scratch/cache/{pest,pest-mutate,vite,vite-temp-be,vite-temp-fe}

be=/source/team-sync-be
fe=/source/team-sync-fe
mkdir -p /scratch/{bootstrap-cache,build-be,build-fe,results/role-evidence}
mkdir -p /scratch/storage/{app/public,framework/cache/data,framework/sessions,framework/views,logs}
python3 - <<'PY'
import os
from pathlib import Path
for project in ('team-sync-be', 'team-sync-fe'):
    source = Path('/source') / project
    assert os.access(source, os.R_OK | os.X_OK), f'Unreadable source: {source}'
    for name in ('app', 'tests', 'config', 'src', 'e2e'):
        path = source / name
        if path.exists():
            assert not os.access(path, os.W_OK), f'Writable source: {path}'
for path in ('/opt/be/vendor/pestphp/pest/.temp', '/opt/be/vendor/pestphp/pest-plugin-mutate/.temp', '/opt/be/node_modules/.vite-temp', '/opt/fe/node_modules/.vite-temp', '/opt/fe/node_modules/.vite'):
    target = Path(path).resolve(strict=True)
    assert target.is_relative_to('/scratch/cache'), f'Cache outside scratch: {path}'
PY

status() {
    local code=$?
    trap - EXIT
    python3 - "$phase" "$code" <<'PY'
import json, sys
from pathlib import Path
Path('/scratch/reports/status.json').write_text(json.dumps({'phase': sys.argv[1], 'exit_code': int(sys.argv[2]), 'status': 'passed' if sys.argv[2] == '0' else 'failed', 'entrypoint': 'bash /source/scripts/sandbox/suite.sh ' + sys.argv[1]}) + '\n')
PY
    exit "$code"
}
trap status EXIT
node --input-type=module <<'JS'
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
const require = createRequire('/opt/fe/package.json');
const { chromium } = require('@playwright/test');
const version = (cmd, args) => execFileSync(cmd, args, { encoding: 'utf8' }).trim();
fs.writeFileSync('/scratch/reports/runtime.json', JSON.stringify({
  php: version('php', ['-r', 'echo PHP_VERSION;']),
  xdebug: version('php', ['-r', 'echo phpversion("xdebug") ?: "unavailable";']),
  node: process.version, v8: process.versions.v8, bun: version('bun', ['--version']),
  vitest: require('vitest/package.json').version,
  playwright: require('@playwright/test/package.json').version,
  browser: version(chromium.executablePath(), ['--version']), browserPath: chromium.executablePath(),
}) + '\n');
JS

artisan() { (cd "$be" && php artisan "$@"); }
build_fe() { (cd "$fe" && node /opt/fe/node_modules/vite/bin/vite.js build); }

case "$phase" in
    be)
        python3 - <<'PY'
import os
fd = os.open('/scratch/database.sqlite', os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
os.close(fd)
PY
        artisan config:clear
        result=0
        artisan test --coverage --cache-directory=/scratch/cache/phpunit --coverage-clover=/scratch/reports/be-clover.xml --coverage-text=/scratch/results/be-coverage.txt --log-junit=/scratch/reports/be-junit.xml || result=$?
        if [[ -f /scratch/results/be-coverage.txt ]]; then cat /scratch/results/be-coverage.txt; fi
        exit "$result"
        ;;
    fe)
        cd "$fe"
        result=0
        node /opt/fe/node_modules/vitest/vitest.mjs run --coverage --maxWorkers=2 \
            --coverage.reporter=json-summary --coverage.reporter=json --coverage.reporter=lcov --coverage.reporter=text \
            --coverage.reportsDirectory=/scratch/results/fe-coverage \
            --reporter=default --reporter=junit --outputFile.junit=/scratch/reports/fe-junit.xml || result=$?
        for pair in 'coverage-summary.json fe-coverage-summary.json' 'coverage-final.json fe-coverage-final.json' 'lcov.info fe-lcov.info'; do
            read -r source destination <<< "$pair"
            if [[ -f /scratch/results/fe-coverage/$source ]]; then
                cp "/scratch/results/fe-coverage/$source" "/scratch/reports/$destination"
            elif [[ $result == 0 ]]; then
                echo "Missing coverage report: $source" >&2
                result=1
            fi
        done
        exit "$result"
        ;;
    build-be)
        cd "$be"
        node /opt/be/node_modules/vite/bin/vite.js build
        ;;
    build-fe)
        build_fe
        ;;
    roles)
        artisan config:clear
        python3 - <<'PY'
import os
fd = os.open('/scratch/database.sqlite', os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
os.close(fd)
PY
        # Exercise both refusal boundaries before permitting the real fixture.
        (cd "$be" && php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$original = config('database.connections.sqlite.database');
$guard = '/scratch/seed-guard.sqlite';
$pdo = new PDO('sqlite:'.$guard);
$pdo->exec('CREATE TABLE sentinel (id INTEGER); INSERT INTO sentinel VALUES (1)');
foreach (['/source/team-sync-be/composer.json', $guard] as $path) {
    config(['database.connections.sqlite.database' => $path]);
    Illuminate\Support\Facades\DB::purge('sqlite');
    try {
        (new Database\Seeders\SandboxRoleSeeder)->run();
        throw new LogicException('Seeder accepted forbidden database: '.$path);
    } catch (RuntimeException $error) {
        if (! str_starts_with($error->getMessage(), 'SandboxRoleSeeder ')) {
            throw $error;
        }
        echo 'Seeder refusal verified: '.$path.PHP_EOL;
    }
}
config(['database.connections.sqlite.database' => $original]);
Illuminate\Support\Facades\DB::purge('sqlite');
unlink($guard);
PHP
        )
        artisan db:seed --class=SandboxRoleSeeder --force --no-interaction
        cat > /scratch/backend-exec.sh <<'SH'
#!/usr/bin/env bash
set -euo pipefail
[[ $# -ge 6 && $1 == exec && $2 == -T && $3 == web && $4 == php && $5 == artisan && $6 == queue:work ]] || exit 64
shift 6
for arg in "$@"; do
    case "$arg" in --once|--queue=default,meetings|--tries=1|--timeout=600|--stop-when-empty) ;; *) exit 64 ;; esac
done
[[ $APP_ENV == testing && $DB_CONNECTION == sqlite && $DB_DATABASE == /scratch/database.sqlite && $QUEUE_CONNECTION == sync ]] || exit 65
cd /source/team-sync-be
exec php artisan queue:work "$@"
SH
        cd "$fe"
        node /opt/fe/node_modules/@playwright/test/cli.js test --config=/source/scripts/sandbox/playwright.config.mjs \
            role-navigation.spec.ts role-button-matrix.spec.ts payroll-roles.spec.ts
        ;;
    a11y)
        build_fe
        cd "$fe"
        node --input-type=module <<'JS'
import { createRequire } from 'node:module';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import fs from 'node:fs';
const require = createRequire('/opt/fe/package.json');
const { chromium } = require('@playwright/test');
const axeRequire = createRequire(require.resolve('@axe-core/cli/package.json'));
const axePath = axeRequire.resolve('axe-core/axe.min.js');
const server = spawn('node', ['/opt/fe/node_modules/vite/bin/vite.js', 'preview', '--host', '127.0.0.1', '--port', '4173', '--strictPort'], { stdio: 'inherit' });
let browser;
try {
  let ready = false;
  for (let i = 0; i < 60; i++) {
    if (server.exitCode !== null) throw new Error('Preview server exited: ' + server.exitCode);
    try { ready = (await fetch('http://127.0.0.1:4173', { signal: AbortSignal.timeout(1000) })).ok; } catch {}
    if (ready) break;
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  if (!ready) throw new Error('Preview server did not become ready.');
  browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('http://127.0.0.1:4173', { waitUntil: 'networkidle', timeout: 30_000 });
  await page.addScriptTag({ path: axePath });
  const results = await page.evaluate(() => window.axe.run(document));
  fs.writeFileSync('/scratch/reports/a11y.json', JSON.stringify({ runner: 'Playwright + transitive axe-core (not Selenium CLI)', requestedUrl: 'http://127.0.0.1:4173', finalUrl: page.url(), browser: await browser.version(), scope: 'Unauthenticated root URL only; no authenticated-page accessibility claim', ...results }) + '\n');
  console.log(`Root accessibility audit: ${results.violations.length} violations, ${results.incomplete.length} incomplete checks`);
  if (results.violations.length) process.exitCode = 1;
} finally {
  if (browser) await browser.close();
  if (server.exitCode === null) {
    const exited = once(server, 'exit');
    server.kill('SIGTERM');
    await exited;
  }
}
JS
        ;;
esac
