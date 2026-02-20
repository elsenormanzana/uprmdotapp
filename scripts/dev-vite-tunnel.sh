#!/usr/bin/env bash
set -euo pipefail

log_file="$(mktemp -t vite-tunnel.XXXXXX.log)"
VITE_TUNNEL_TOKEN="${VITE_TUNNEL_TOKEN:-}"
VITE_TUNNEL_HOSTNAME="${VITE_TUNNEL_HOSTNAME:-}"
VITE_TUNNEL_URL="${VITE_TUNNEL_URL:-}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ -f "${ROOT_DIR}/.env" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "${ROOT_DIR}/.env"
    set +a
fi

cleanup() {
    if [[ -n "${cf_pid:-}" ]] && kill -0 "$cf_pid" 2>/dev/null; then
        kill "$cf_pid" || true
    fi
    rm -f "$log_file"
}
trap cleanup EXIT

if [[ -z "${VITE_TUNNEL_URL}" && -n "${VITE_TUNNEL_HOSTNAME}" ]]; then
    VITE_TUNNEL_URL="https://${VITE_TUNNEL_HOSTNAME}"
fi

if [[ -n "${VITE_TUNNEL_TOKEN}" ]]; then
    if [[ -z "${VITE_TUNNEL_URL}" ]]; then
        echo "VITE_TUNNEL_URL or VITE_TUNNEL_HOSTNAME is required when VITE_TUNNEL_TOKEN is set." >&2
        exit 1
    fi
    cloudflared tunnel run --token "${VITE_TUNNEL_TOKEN}" 2>&1 | tee "$log_file" &
    cf_pid=$!
    vite_url="${VITE_TUNNEL_URL}"
else
    cloudflared tunnel --url https://127.0.0.1:5173 --no-tls-verify 2>&1 | tee "$log_file" &
    cf_pid=$!

    vite_url=""
    for _ in $(seq 1 30); do
        vite_url="$(rg -o 'https://[a-z0-9-]+\.trycloudflare\.com' "$log_file" 2>/dev/null || true | sed -n '$p')"
        if [[ -n "$vite_url" ]]; then
            break
        fi
        sleep 1
    done

    if [[ -z "$vite_url" ]]; then
        echo "Failed to get Cloudflare URL for Vite tunnel." >&2
        exit 1
    fi
fi

vite_host="$(printf "%s" "$vite_url" | sed -E 's|https://([^/]+).*|\1|')"
export VITE_DEV_SERVER_URL="$vite_url"
export VITE_HMR_HOST="$vite_host"
export VITE_HMR_PROTOCOL="wss"
export VITE_HMR_CLIENT_PORT=443

echo "Vite tunnel: $vite_url"
npm run dev
