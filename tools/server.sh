#!/usr/bin/env bash
# tools/server.sh - 确保本地开发服务器可用 (端口固定 9095)
# 用法: bash tools/server.sh   (或 npm run server)
PORT=9095
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LOG="${TMPDIR:-/tmp}/his-dev-server.log"
FRANKEN="$HOME/.local/bin/frankenphp"

if curl -s -o /dev/null "http://127.0.0.1:${PORT}/"; then
    echo "HIS dev server already running: http://0.0.0.0:${PORT}"
    exit 0
fi

if [ ! -x "$FRANKEN" ]; then
    echo "frankenphp not found at $FRANKEN" >&2
    exit 1
fi

nohup "$FRANKEN" php-server --root "$ROOT_DIR/public/" --listen "0.0.0.0:${PORT}" > "$LOG" 2>&1 &
sleep 2

if curl -s -o /dev/null "http://127.0.0.1:${PORT}/"; then
    echo "HIS dev server started: http://0.0.0.0:${PORT} (log: ${LOG})"
else
    echo "HIS dev server failed to start, see ${LOG}" >&2
    exit 1
fi
