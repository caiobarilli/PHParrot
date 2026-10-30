#!/usr/bin/env sh

set -e

eh_windows() {
    case "$1" in
        /mnt/[a-z]/*|/[a-z]/*|*/Program\ Files/*|*/Program\ Files\ \(x86\)/*|*.exe|*.cmd|*.bat) return 0 ;;
        *) return 1 ;;
    esac
}

mais_novo_primeiro() {
    if sort -V </dev/null >/dev/null 2>&1; then
        sort -Vr
    else
        sort -r
    fi
}

node_bin=''

candidato=$(command -v node 2>/dev/null || true)
if [ -n "$candidato" ] && ! eh_windows "$candidato"; then
    node_bin="$candidato"
fi

if [ -z "$node_bin" ]; then
    raiz_nvm="${NVM_DIR:-$HOME/.nvm}"
    for dir in \
        $(ls -d "$raiz_nvm"/versions/node/*/bin 2>/dev/null | mais_novo_primeiro) \
        $(ls -d "$HOME"/.local/share/fnm/node-versions/*/installation/bin 2>/dev/null | mais_novo_primeiro) \
        $(ls -d "$HOME"/.volta/tools/image/node/*/bin 2>/dev/null | mais_novo_primeiro) \
        /usr/local/bin /usr/bin "$HOME/.local/bin"
    do
        if [ -x "$dir/node" ] && ! eh_windows "$dir/node"; then
            node_bin="$dir/node"
            break
        fi
    done
fi

if [ -z "$node_bin" ]; then
    echo "pre-commit: nenhum node do Linux encontrado. Instale node pelo nvm no WSL." >&2
    exit 1
fi

dir_node=$(dirname "$node_bin")
PATH="$dir_node:$PATH"
export PATH

exec "$@"
