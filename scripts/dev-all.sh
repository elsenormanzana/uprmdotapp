#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="${ROOT_DIR}/storage/logs/dev"

if [[ -f "${ROOT_DIR}/.env" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "${ROOT_DIR}/.env"
  set +a
fi
LARAVEL_SERVE_HOST="${LARAVEL_SERVE_HOST:-0.0.0.0}"
LARAVEL_SERVE_PORT="${LARAVEL_SERVE_PORT:-8000}"
LARAVEL_URL="http://127.0.0.1:${LARAVEL_SERVE_PORT}"
LARAVEL_ORIGIN_URL="http://127.0.0.1:${LARAVEL_SERVE_PORT}"
APP_TUNNEL_TOKEN="${APP_TUNNEL_TOKEN:-}"
APP_TUNNEL_URL="${APP_TUNNEL_URL:-}"
VITE_TUNNEL_URL="${VITE_TUNNEL_URL:-}"

cd "${ROOT_DIR}"
mkdir -p "${LOG_DIR}"

QUEUE_PID=""
LARAVEL_PID=""
VITE_PID=""
CF_PID=""

QUEUE_LOG="${LOG_DIR}/queue.log"
LARAVEL_LOG="${LOG_DIR}/laravel.log"
VITE_LOG="${LOG_DIR}/vite.log"
CF_LOG="${LOG_DIR}/cloudflared.log"

truncate_logs() {
  : >"${QUEUE_LOG}"
  : >"${LARAVEL_LOG}"
  : >"${VITE_LOG}"
  : >"${CF_LOG}"
}

start_processes() {
  truncate_logs
  php artisan queue:listen --tries=1 --timeout=0 >"${QUEUE_LOG}" 2>&1 &
  QUEUE_PID=$!
  php artisan serve --host "${LARAVEL_SERVE_HOST}" --port "${LARAVEL_SERVE_PORT}" >"${LARAVEL_LOG}" 2>&1 &
  LARAVEL_PID=$!
  bash scripts/dev-vite-tunnel.sh >"${VITE_LOG}" 2>&1 &
  VITE_PID=$!
  if [[ -n "${APP_TUNNEL_TOKEN}" ]]; then
    cloudflared tunnel run --token "${APP_TUNNEL_TOKEN}" >"${CF_LOG}" 2>&1 &
  else
    cloudflared tunnel --url "${LARAVEL_ORIGIN_URL}" >"${CF_LOG}" 2>&1 &
  fi
  CF_PID=$!
}

stop_processes() {
  for pid in "${CF_PID}" "${VITE_PID}" "${LARAVEL_PID}" "${QUEUE_PID}"; do
    if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
      kill "${pid}" 2>/dev/null || true
    fi
  done

  for pid in "${CF_PID}" "${VITE_PID}" "${LARAVEL_PID}" "${QUEUE_PID}"; do
    if [[ -n "${pid}" ]]; then
      wait "${pid}" 2>/dev/null || true
    fi
  done
}

restart_processes() {
  stop_processes
  start_processes
}

status_line() {
  local label="$1"
  local pid="$2"
  if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
    printf "%-14s running (pid %s)\n" "${label}:" "${pid}"
  else
    printf "%-14s stopped\n" "${label}:"
  fi
}

view_log() {
  local label="$1"
  local path="$2"
  if [[ ! -f "${path}" ]]; then
    echo "Log not found for ${label}."
    sleep 1
    return
  fi
  clear
  echo "Viewing ${label} log (last 200 lines)."
  echo "Press q to return to the dashboard."
  echo
  tail -n 200 -f "${path}" &
  local tail_pid=$!
  local key=""
  while IFS= read -rsn1 key; do
    if [[ "${key}" == "q" ]]; then
      break
    fi
  done
  if kill -0 "${tail_pid}" 2>/dev/null; then
    kill "${tail_pid}" 2>/dev/null || true
    wait "${tail_pid}" 2>/dev/null || true
  fi
}

extract_tunnel_url() {
  local path="$1"
  local fallback="${2:-}"
  local url=""
  if [[ -f "${path}" ]]; then
    url="$(grep -Eo 'https://[a-zA-Z0-9.-]+\.trycloudflare\.com' "${path}" | tail -n 1 || true)"
  fi
  if [[ -n "${url}" ]]; then
    echo "${url}"
  elif [[ -n "${fallback}" ]]; then
    echo "${fallback}"
  else
    echo "-"
  fi
}

term_cols() {
  local cols
  cols="$(tput cols 2>/dev/null || true)"
  if [[ -z "${cols}" ]]; then
    cols=80
  fi
  echo "${cols}"
}

color_init() {
  if [[ -n "${NO_COLOR:-}" ]] || [[ ! -t 1 ]]; then
    COLOR_RED=""
    COLOR_GREEN=""
    COLOR_YELLOW=""
    COLOR_BLUE=""
    COLOR_MAGENTA=""
    COLOR_CYAN=""
    COLOR_BOLD=""
    COLOR_RESET=""
    return
  fi
  COLOR_RED=$'\033[31m'
  COLOR_GREEN=$'\033[32m'
  COLOR_YELLOW=$'\033[33m'
  COLOR_BLUE=$'\033[34m'
  COLOR_MAGENTA=$'\033[35m'
  COLOR_CYAN=$'\033[36m'
  COLOR_BOLD=$'\033[1m'
  COLOR_RESET=$'\033[0m'
}

repeat_char() {
  local count="$1"
  local char="$2"
  printf "%*s" "${count}" "" | tr " " "${char}"
}

box_line() {
  local width="$1"
  local text="$2"
  local pad=$((width - 2))
  local clean
  clean="$(printf "%s" "${text}" | sed -E 's/\x1B\[[0-9;]*[A-Za-z]//g')"
  local visible_len=${#clean}
  if (( visible_len > pad )); then
    text="${clean:0:${pad}}"
    visible_len=${#text}
  fi
  local spaces=$((pad - visible_len))
  printf "│%s%*s│\n" "${text}" "${spaces}" ""
}

box_title() {
  local width="$1"
  local text="$2"
  local pad=$((width - 2))
  local title=" ${text} "
  local clean
  clean="$(printf "%s" "${title}" | sed -E 's/\x1B\[[0-9;]*[A-Za-z]//g')"
  local visible_len=${#clean}
  if (( visible_len > pad )); then
    title="${clean:0:${pad}}"
    visible_len=${#title}
  fi
  local left=$(( (pad - visible_len) / 2 ))
  local right=$(( pad - visible_len - left ))
  printf "│%s%s%s│\n" "$(repeat_char "${left}" " ")" "${title}" "$(repeat_char "${right}" " ")"
}

status_value() {
  local pid="$1"
  if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
    echo "${COLOR_GREEN}running${COLOR_RESET} (pid ${pid})"
  else
    echo "${COLOR_RED}stopped${COLOR_RESET}"
  fi
}

dashboard() {
  while true; do
    local cols width
    color_init
    cols="$(term_cols)"
    width=$(( cols < 80 ? cols : 80 ))
    clear
    printf "┌%s┐\n" "$(repeat_char $((width - 2)) "─")"
    box_title "${width}" "${COLOR_BOLD}${COLOR_CYAN}UPRM DOT DEV DASHBOARD${COLOR_RESET}"
    box_line "${width}" ""
    box_line "${width}" "Log folder: ${COLOR_BLUE}${LOG_DIR}${COLOR_RESET}"
    box_line "${width}" ""
    box_line "${width}" "${COLOR_BOLD}Status${COLOR_RESET}"
    box_line "${width}" " Queue:       $(status_value "${QUEUE_PID}")"
    box_line "${width}" " Laravel:     $(status_value "${LARAVEL_PID}")"
    box_line "${width}" " Vite:        $(status_value "${VITE_PID}")"
    box_line "${width}" " Cloudflared: $(status_value "${CF_PID}")"
    box_line "${width}" ""
    box_line "${width}" "${COLOR_BOLD}Local URLs${COLOR_RESET}"
    box_line "${width}" " Laravel:     ${COLOR_CYAN}${LARAVEL_URL}${COLOR_RESET}"
    box_line "${width}" " Vite:        ${COLOR_CYAN}http://127.0.0.1:5173${COLOR_RESET}"
    box_line "${width}" ""
    box_line "${width}" "${COLOR_BOLD}Cloudflared URLs${COLOR_RESET}"
    box_line "${width}" " Laravel:     ${COLOR_MAGENTA}$(extract_tunnel_url "${CF_LOG}" "${APP_TUNNEL_URL}")${COLOR_RESET}"
    box_line "${width}" " Vite:        ${COLOR_MAGENTA}$(extract_tunnel_url "${VITE_LOG}" "${VITE_TUNNEL_URL}")${COLOR_RESET}"
    box_line "${width}" ""
    box_line "${width}" "${COLOR_BOLD}Logs${COLOR_RESET}"
    box_line "${width}" " [1] Vite  [2] Laravel  [3] Cloudflared"
    box_line "${width}" ""
    box_line "${width}" "${COLOR_BOLD}Actions${COLOR_RESET}"
    box_line "${width}" " [r] Restart all  [k] Kill all and exit  [q] Quit"
    printf "└%s┘\n" "$(repeat_char $((width - 2)) "─")"
    echo
    read -rp "Select option: " choice
    case "${choice}" in
      1) view_log "Vite" "${VITE_LOG}" ;;
      2) view_log "Laravel" "${LARAVEL_LOG}" ;;
      3) view_log "Cloudflared" "${CF_LOG}" ;;
      r|R) restart_processes ;;
      k|K) stop_processes; exit 0 ;;
      q|Q) stop_processes; exit 0 ;;
      *) echo "Unknown option."; sleep 1 ;;
    esac
  done
}

cleanup() {
  stop_processes
}

trap cleanup EXIT INT TERM

start_processes
dashboard
