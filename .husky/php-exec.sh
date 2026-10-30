#!/usr/bin/env sh

if ! command -v docker >/dev/null 2>&1; then
    echo "pre-commit: docker não encontrado no PATH." >&2
    exit 1
fi

if ! docker ps --format '{{.Names}}' 2>/dev/null | grep -qx 'phparrot-php'; then
    echo "pre-commit: o container phparrot-php não está de pé. Rode terraform apply em terraform/." >&2
    exit 1
fi

exec docker exec -i phparrot-php "$@"
