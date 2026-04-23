#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cleanup() {
  kill 0
}

trap cleanup INT TERM EXIT

(
  cd "${ROOT_DIR}/web"
  php artisan boost:mcp
) &

(
  cd "${ROOT_DIR}/game"
  dart mcp-server
) &

wait
