# Setup

## Yang perlu dipasang

- PHP 8.4 dengan ekstensi `pdo_pgsql`, `pgsql`, `mbstring`, `fileinfo`
- Composer 2
- Node.js 22 (hanya untuk perintah `npm run` dan tes browser)
- Docker Desktop (untuk PostgreSQL 17)

Kalau PHP tidak ada di PATH, atur lokasinya dengan variabel `LOLA_PHP`, atau buat `.runtime/tools.json` berisi `{"php": "C:\\Tools\\php84\\php.exe"}`.
Bila ada `.runtime/php.ini`, file itu otomatis dipakai.

## Pertama kali

```bash
npm install
npm run setup
```

`setup` akan:

1. menjalankan `composer install` kalau folder `vendor` belum ada,
2. membuat `.env` dari `.env.example` dengan password database acak dan `APP_KEY` baru,
3. membuat `.env.testing` untuk tes (database `lola_demo_test`),
4. menyalakan PostgreSQL lewat Docker,
5. menjalankan migrasi dan mengisi data awal (akun demo dan empat lapangan).

File `.env` yang sudah ada tidak ditimpa.

Untuk tes browser, unduh Chromium sekali:

```bash
npx playwright install chromium
```

## Sehari-hari

```bash
npm run dev
```

Lalu buka http://127.0.0.1:8001. `dev` tidak menjalankan migrasi. Kalau ada migrasi baru dari teman, jalankan `npm run db:migrate`.

## Daftar perintah

| Perintah | Fungsi |
|---|---|
| `npm run dev` | Menyalakan database lalu server di port 8001 |
| `npm run test` | Semua tes PHP. Tambahkan filter: `npm run test -- --filter=Payment` |
| `npm run check` | Pint (format kode), PHPStan (analisis statis), lalu semua tes PHP |
| `npm run test:e2e` | Tes browser di port 8002 |
| `npm run db:up` / `db:stop` | Menyalakan/mematikan container database tanpa menghapus data |
| `npm run db:migrate` | Menjalankan migrasi yang belum jalan |
| `npm run db:seed` | Mengisi akun demo dan lapangan bila masih kosong |
| `npm run db:status` | Melihat status migrasi |
| `npm run db:reset -- --yes` | **Menghapus semua data demo lokal** lalu mengisi ulang |
| `npm run api -- <perintah>` | Menjalankan `php artisan <perintah>` |

## Port dan database

| Bagian | Alamat |
|---|---|
| Aplikasi | http://127.0.0.1:8001 |
| Aplikasi khusus tes browser | http://127.0.0.1:8002 |
| PostgreSQL | 127.0.0.1:55433 |
| Database demo | `lola_demo` |
| Database tes | `lola_demo_test` |

Tes PHP dan tes browser memakai `lola_demo_test` yang dikosongkan setiap kali jalan, jadi keduanya dijalankan bergantian, jangan bersamaan.
Migrasi, seed, dan tes punya pengaman di `AppServiceProvider` dan `tests/TestCase.php`: perintah ditolak bila database tujuannya bukan database demo di `127.0.0.1:55433`.

## Masalah yang sering muncul

- **`could not find driver`**: ekstensi `pdo_pgsql` belum aktif di `php.ini`.
- **Docker error `pipe/docker_engine`**: Docker Desktop belum dinyalakan.
- **`Database belum siap atau ada migrasi baru`**: jalankan `npm run db:migrate`.
- **Setelah tarik perubahan besar dari Git** (misalnya struktur tabel berubah total), data demo lama bisa tidak cocok. Karena datanya fiktif, jalankan `npm run db:reset -- --yes`.

## Yang tidak boleh masuk Git

`.env`, `.env.testing`, `.env.e2e`, folder `vendor`, `node_modules`, `.runtime`, dan file bukti pembayaran di `storage/app/private`. Semuanya sudah ada di `.gitignore`.
