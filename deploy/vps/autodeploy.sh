#!/usr/bin/env bash
# Deploy otomatis: dijalankan timer systemd tiap 2 menit (lihat lola-autodeploy.timer).
# Commit baru di main hanya dipasang setelah semua cek GitHub Actions untuk commit itu lolos.
# Server menarik dari GitHub, jadi tidak ada kunci SSH VPS yang disimpan di GitHub.
# Log: journalctl -u lola-autodeploy -n 50
set -euo pipefail

# Dibungkus fungsi: install.sh menimpa file ini saat memasang versi baru.
main() {
  OWNER_REPO=kheisarkc/lola-sport
  APP=/opt/lola-sport-v1
  STATE=/var/lib/lola-autodeploy
  mkdir -p "$STATE"
  exec 9>"$STATE/lock"
  flock -n 9 || { echo "Deploy lain masih berjalan."; exit 0; }

  SHA=$(curl -fsS -m 20 -H "Accept: application/vnd.github+json" "https://api.github.com/repos/$OWNER_REPO/commits/main" \
    | python3 -c 'import json,sys; print(json.load(sys.stdin)["sha"])')
  DEPLOYED=$(cat "$STATE/deployed" 2>/dev/null || true)
  [ "$SHA" = "$DEPLOYED" ] && exit 0
  [ "$SHA" = "$(cat "$STATE/failed" 2>/dev/null || true)" ] && exit 0

  # Ringkas status cek: pending | success | failure
  VERDICT=$(curl -fsS -m 20 -H "Accept: application/vnd.github+json" "https://api.github.com/repos/$OWNER_REPO/commits/$SHA/check-runs" \
    | python3 -c '
import json, sys
runs = json.load(sys.stdin).get("check_runs", [])
if not runs or any(r["status"] != "completed" for r in runs): print("pending")
elif all(r["conclusion"] in ("success", "skipped", "neutral") for r in runs): print("success")
else: print("failure")')

  case "$VERDICT" in
    pending) echo "Commit ${SHA:0:7} menunggu tes GitHub selesai."; exit 0 ;;
    failure) echo "Commit ${SHA:0:7} gagal tes GitHub, tidak dipasang. Web tetap di ${DEPLOYED:0:7}."; echo "$SHA" > "$STATE/failed"; exit 0 ;;
  esac

  echo "Memasang commit ${SHA:0:7} (sebelumnya ${DEPLOYED:0:7})."
  if REF="$SHA" bash "$APP/deploy/vps/install.sh"; then
    echo "$SHA" > "$STATE/deployed"
    echo "Berhasil: ${SHA:0:7} sudah live."
  else
    echo "$SHA" > "$STATE/failed"
    echo "Gagal memasang ${SHA:0:7}; dicoba lagi saat ada commit baru. Lihat log di atas." >&2
    exit 1
  fi
}

main "$@"
