#!/bin/sh
set -eu

(
    while true; do
        sleep 60
        find /tmp -mindepth 1 -mmin +2 -delete 2>/dev/null || true
    done
) &

exec whisper-server --host 0.0.0.0 --port 8080 --convert --tmp-dir /tmp -m "/models/ggml-${MODELO}.bin"
