#!/usr/bin/env bash
# Pasang atau perbarui Lola Sport v1 di VPS, berdampingan dengan demo lama.
#   curl -fsSL https://raw.githubusercontent.com/kheisarkc/lola-sport/main/deploy/vps/install.sh | sudo bash
# atau, setelah terpasang:  sudo bash /opt/lola-sport-v1/deploy/vps/install.sh
# Tidak menyentuh /opt/lola-sport-demo, project Compose lama, ataupun volumenya.
set -euo pipefail

# Seluruh isi dibungkus fungsi dan stdin diarahkan ke /dev/null: saat dijalankan lewat
# `curl ... | bash`, perintah docker compose run/exec tidak boleh ikut membaca sisa skrip.
main() {
  HOST=${HOST:-lolasport.43.173.6.29.sslip.io}
  # Hostname lama yang diganti otomatis bila masih tercatat di Caddyfile.
  OLD_HOSTS="v1.43.173.6.29.sslip.io"
  APP=/opt/lola-sport-v1
  BACKUPS=/opt/lola-backups
  REPO=https://github.com/kheisarkc/lola-sport/archive/refs/heads/main.tar.gz
  CADDYFILE=/etc/caddy/Caddyfile
  STAMP=$(date +%Y%m%d-%H%M%S)
  C="docker compose --env-file deploy/vps/.env -f deploy/vps/compose.yml"

  mkdir -p "$APP" "$BACKUPS"
  cd "$APP"
  FIRST=0; [ -f deploy/vps/.env ] || FIRST=1

  if [ "$FIRST" = "0" ]; then
    echo "== Backup database v1 ($STAMP)"
    $C exec -T db pg_dump -h 127.0.0.1 -p 55443 -U lola lola_demo > "$BACKUPS/lola_v1-$STAMP.sql" || echo "(backup dilewati: database belum jalan)"
    chmod 600 "$BACKUPS"/* || true
  fi

  echo "== Ambil source v1"
  NEW=$(mktemp -d)
  curl -fsSL "$REPO" | tar -xz -C "$NEW" --strip-components=1
  rsync -a --delete --exclude=/deploy/vps/.env --exclude=/deploy/vps/.seeded --exclude=/.env "$NEW/" "$APP/"
  rm -rf "$NEW"

  if [ "$FIRST" = "1" ]; then
    echo "== Buat deploy/vps/.env (rahasia acak, hanya di server)"
    sed -e "s#^APP_KEY=.*#APP_KEY=base64:$(openssl rand -base64 32)#" \
        -e "s#^DB_PASSWORD=.*#DB_PASSWORD=$(openssl rand -hex 24)#" \
        -e "s#YOUR-DEMO-HOSTNAME#$HOST#" deploy/vps/.env.example > deploy/vps/.env
    chmod 600 deploy/vps/.env
  fi
  sed -i "s#^APP_URL=.*#APP_URL=https://$HOST#" deploy/vps/.env

  echo "== Database, build, migrasi"
  $C up -d db
  $C build app
  $C run --rm -T app php artisan migrate --force --no-interaction
  # Data demo diisi sekali saja; penanda disimpan di server, tidak ikut source.
  if [ ! -f deploy/vps/.seeded ]; then $C run --rm -T app php artisan db:seed --force --no-interaction && touch deploy/vps/.seeded; fi
  $C up -d --force-recreate app

  for i in $(seq 1 30); do curl -fsS -o /dev/null http://127.0.0.1:8011/up && break; sleep 2; done
  curl -fsS -o /dev/null http://127.0.0.1:8011/up || { echo "Aplikasi belum sehat. Lihat: cd $APP && $C logs --tail=50 app" >&2; exit 1; }

  echo "== Caddy"
  if [ -f "$CADDYFILE" ] && ! grep -q "^$HOST " "$CADDYFILE"; then
    cp "$CADDYFILE" "$BACKUPS/Caddyfile-$STAMP"
    RENAMED=0
    for old in $OLD_HOSTS; do
      if grep -q "^$old " "$CADDYFILE"; then sed -i "s#^$old #$HOST #" "$CADDYFILE"; RENAMED=1; fi
    done
    [ "$RENAMED" = "1" ] || { echo; sed "s#YOUR-DEMO-HOSTNAME#$HOST#" deploy/vps/Caddyfile.example; } >> "$CADDYFILE"
    if caddy validate --config "$CADDYFILE" --adapter caddyfile; then systemctl reload caddy
    else cp "$BACKUPS/Caddyfile-$STAMP" "$CADDYFILE"; echo "Caddyfile tidak valid, dikembalikan." >&2; exit 1; fi
  elif [ ! -f "$CADDYFILE" ]; then
    echo "Caddyfile tidak ada di $CADDYFILE. Tambahkan isi deploy/vps/Caddyfile.example (host $HOST) secara manual." >&2
  fi

  sleep 5
  curl -fsS -o /dev/null -w "https://$HOST/up -> %{http_code}\n" "https://$HOST/up" || echo "HTTPS belum siap (sertifikat mungkin masih dibuat), coba buka lagi sebentar."
  echo "SELESAI: https://$HOST"
}

main "$@" </dev/null
