# Menjalankan demo di VPS Linux

Satu container aplikasi Laravel (Apache + PHP 8.4) dan satu container PostgreSQL, di belakang Caddy untuk HTTPS.
Tetap demo dengan akun fiktif, bukan sistem operasional Lola Sport.

## Pertama kali

1. Pasang Docker dan Compose. Salin source ke folder khusus, misalnya `/opt/lola-sport-v1`. Pastikan port 8011 dan 55443 belum dipakai.
2. Salin `deploy/vps/.env.example` menjadi `deploy/vps/.env` **di server saja**. Isi hostname, `APP_KEY` (`base64:` + 32 byte acak), dan `DB_PASSWORD` acak. Jalankan `chmod 600` pada file itu.
3. Dari root source:

```bash
docker compose --env-file deploy/vps/.env -f deploy/vps/compose.yml up -d db
docker compose --env-file deploy/vps/.env -f deploy/vps/compose.yml build app
docker compose --env-file deploy/vps/.env -f deploy/vps/compose.yml run --rm app php artisan migrate --force --no-interaction
docker compose --env-file deploy/vps/.env -f deploy/vps/compose.yml run --rm app php artisan db:seed --force --no-interaction
docker compose --env-file deploy/vps/.env -f deploy/vps/compose.yml up -d app
```

4. Tambahkan isi `Caddyfile.example` ke Caddyfile dengan hostname yang benar, cek dengan `caddy validate`, lalu reload.
5. Buka `https://hostname/up`, jadwal, login pegawai dan pemilik, lalu pastikan `/pegawai` tanpa login diarahkan ke halaman masuk.

## Berdampingan dengan demo Next.js lama

Versi ini **tidak menggantikan** demo lama. Project Compose `lola-sport-v1`, port 8011/55443, volume, dan cookie sesi sengaja berbeda,
jadi demo lama (`lola-sport-hosted-demo`, port 3001/8001/55433) tetap jalan. Pakai hostname lain, misalnya `lolasport.<ip>.sslip.io`.
Jangan menghentikan atau mengosongkan container dan database demo lama dari folder ini.

## Operasional

- Lihat status: `docker compose --env-file deploy/vps/.env -f deploy/vps/compose.yml ps` dan `logs --tail=50 app`.
- Update biasa: tarik source, `build app`, `run --rm app php artisan migrate --force`, lalu `up -d app`. Jangan pakai `migrate:fresh` di luar langkah di atas.
- `stop` mempertahankan data. Jangan pakai `down -v` atau `docker volume prune`.
- Sebelum mengubah skema, backup database dengan `pg_dump` dan backup volume `storage` (berisi bukti pembayaran).
- Jangan masukkan data pribadi sungguhan atau melakukan pembayaran nyata di demo publik.
