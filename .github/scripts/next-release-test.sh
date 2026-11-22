#!/usr/bin/env bash
set -euo pipefail

script=$(cd "$(dirname "$0")" && pwd)/next-release.sh
repo=$(mktemp -d)
trap 'rm -rf "$repo"' EXIT
git init -q "$repo"
cd "$repo"
git config user.name Test
git config user.email test@example.invalid
git config commit.gpgsign false
commit() { git -c core.hooksPath=/dev/null commit -q --allow-empty -m "$1"; }
expect() {
    actual=$(bash "$script")
    [[ $actual == "$1" ]] || { printf 'Expected:\n%s\nActual:\n%s\n' "$1" "$actual"; exit 1; }
}

commit 'fix: initial'
expect $'publish=true\nversion=v0.0.1'
git tag v0.0.8
expect 'publish=false'
commit 'fix: next'
expect $'publish=true\nversion=v0.0.9'
git tag -a v0.0.9 -m existing
commit 'fix: after annotated tag'
expect $'publish=true\nversion=v0.0.10'
git tag v0.19.10
commit 'feat(payroll): feature'
expect $'publish=true\nversion=v0.20.0'
git tag v99.0.0-rc.1
git tag unrelated
git tag v01.0.0
expect $'publish=true\nversion=v0.20.0'
branch=$(git symbolic-ref --short HEAD)
git checkout -q --orphan other-line
commit 'feat: unrelated history'
git tag v1.0.0
git checkout -q "$branch"
commit 'fix: current line'
expect $'publish=true\nversion=v1.0.1'
git tag v1.0.1
expect 'publish=false'
echo 'PASS: initial, annotated/lightweight tags, rerun, version order, feature bump, tag filtering, detached history.'
