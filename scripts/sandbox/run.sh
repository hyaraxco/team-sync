#!/bin/sh
set -eu
# Host orchestration only; target code runs exclusively inside the local image.
exec python3 -I "$(dirname "$0")/probe.py" --host "$@"
