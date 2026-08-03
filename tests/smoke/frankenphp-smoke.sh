#!/usr/bin/env bash
# Smoke-test GEMVC FrankenPHP classic + worker against dunglas/frankenphp:1-php8.4-bookworm
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SMOKE_DIR="$ROOT/tests/smoke/frankenphp"
IMAGE="dunglas/frankenphp:1-php8.4-bookworm"
CLASSIC_NAME="gemvc-frankenphp-classic-smoke"
WORKER_NAME="gemvc-frankenphp-worker-smoke"
PORT_CLASSIC="${PORT_CLASSIC:-18080}"
PORT_WORKER="${PORT_WORKER:-18081}"

cleanup() {
  docker rm -f "$CLASSIC_NAME" "$WORKER_NAME" >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "==> Preparing smoke app vendor (path repo mirror -> library)"
(
  cd "$SMOKE_DIR"
  rm -rf vendor composer.lock
  composer install --no-interaction --quiet
  # Ensure library is a real directory inside vendor (not a host symlink)
  if [[ -L vendor/gemvc/library ]]; then
    echo "ERROR: vendor/gemvc/library is still a symlink; Docker cannot follow host paths"
    exit 1
  fi
)

echo "==> Classic mode on :$PORT_CLASSIC"
docker run -d --name "$CLASSIC_NAME" \
  -p "${PORT_CLASSIC}:80" \
  -e SERVER_NAME=:80 \
  -e APP_ENV=dev \
  -e APP_ENV_SERVER=frankenphp \
  -v "$SMOKE_DIR:/app" \
  -v "$SMOKE_DIR/Caddyfile:/etc/frankenphp/Caddyfile:ro" \
  -w /app \
  "$IMAGE" >/dev/null

sleep 3

classic_body="$(curl -fsS "http://127.0.0.1:${PORT_CLASSIC}/api/Index/ping" || true)"
echo "Classic /api/Index/ping => $classic_body"
if [[ -z "$classic_body" ]] || ! echo "$classic_body" | grep -q '"ok": *true'; then
  echo "Classic container logs:"
  docker logs "$CLASSIC_NAME" 2>&1 | tail -40
  exit 1
fi
echo "$classic_body" | grep -q 'classic'

deny_code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT_CLASSIC}/app/secret.php" || true)"
echo "Classic deny /app/secret.php => HTTP $deny_code"
if [[ "$deny_code" != "403" && "$deny_code" != "404" ]]; then
  echo "Expected 403 or 404 for /app path, got $deny_code"
  exit 1
fi

echo "==> Worker mode on :$PORT_WORKER"
docker rm -f "$CLASSIC_NAME" >/dev/null 2>&1 || true

docker run -d --name "$WORKER_NAME" \
  -p "${PORT_WORKER}:80" \
  -e SERVER_NAME=:80 \
  -e APP_ENV=dev \
  -e APP_ENV_SERVER=frankenphp \
  -v "$SMOKE_DIR:/app" \
  -v "$SMOKE_DIR/Caddyfile.worker:/etc/frankenphp/Caddyfile:ro" \
  -w /app \
  "$IMAGE" >/dev/null

sleep 4

worker_body="$(curl -fsS "http://127.0.0.1:${PORT_WORKER}/api/Index/ping" || true)"
echo "Worker /api/Index/ping => $worker_body"
if [[ -z "$worker_body" ]] || ! echo "$worker_body" | grep -q '"ok": *true'; then
  echo "Worker container logs:"
  docker logs "$WORKER_NAME" 2>&1 | tail -40
  exit 1
fi
echo "$worker_body" | grep -q 'worker'

worker_body2="$(curl -fsS "http://127.0.0.1:${PORT_WORKER}/api/Index/ping")"
echo "Worker second hit => $worker_body2"
echo "$worker_body2" | grep -q '"ok": *true'

echo "OK - FrankenPHP classic + worker smoke passed"
