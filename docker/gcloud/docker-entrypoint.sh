#!/usr/bin/env bash
set -euo pipefail

_term() {
  kill -TERM "$child" 2>/dev/null
}

trap _term SIGINT SIGTERM

"$@" &

child=$!

echo "ChildProcessId:${child}"

wait "$child"