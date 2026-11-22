#!/usr/bin/env bash
set -euo pipefail

# Ignore prereleases and unrelated tags; git describe selects ancestry, not version order.
latest=$(git tag --list --sort=-version:refname | grep -E '^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$' | sed -n '1p' || true)
if [[ -n $(git tag --points-at HEAD | grep -E '^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$' || true) ]]; then
    echo 'Skip: HEAD already has a stable version tag.' >&2
    echo 'publish=false'
    exit 0
fi

version=${latest:-v0.0.0}
IFS='.' read -r major minor patch <<< "${version#v}"
if [[ -n $latest ]] && git merge-base --is-ancestor "$latest" HEAD; then
    commits=$(git log "$latest..HEAD" --format=%s)
else
    # A detached version line must not make old feature commits bump every release.
    commits=$(git log -1 --format=%s)
fi
if grep -qE '^feat(\([^)]*\))?!?:' <<< "$commits"; then
    minor=$((minor + 1))
    patch=0
else
    patch=$((patch + 1))
fi
next="v$major.$minor.$patch"
if git show-ref --verify --quiet "refs/tags/$next"; then
    echo "Skip: tag $next already exists." >&2
    echo 'publish=false'
else
    echo 'publish=true'
    echo "version=$next"
fi
