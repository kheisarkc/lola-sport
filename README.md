# Lola Sport: demo sistem reservasi lapangan

Demo capstone kelompok Arto (Telkom University) untuk Lola Sport, tempat sewa lapangan futsal dan badminton.
Satu aplikasi Laravel dengan tampilan Blade, database PostgreSQL.

> **Status:** prototipe untuk belajar dan bahan laporan. Data, akun, dan pembayaran fiktif.
> Beberapa aturan masih asumsi kelompok dan menunggu konfirmasi pemilik. Lihat [docs/PROJECT.md](docs/PROJECT.md).

## Fitur per aktor

| Aktor | Bisa apa | Halaman |
|---|---|---|
| Pelanggan (tanpa akun) | Lihat jadwal dan harga, pilih satu atau beberapa jam berurutan, pesan pakai nama + nomor WA, pilih bayar DP atau lunas, kirim bukti, batalkan sendiri sebelum bayar, lanjutkan pesanan dari jadwal, cek status pakai kode + WA | `/`, `/pesan`, `/cek`, `/reservasi/{kode}` |
| Pegawai | Lihat jadwal harian, catat reservasi WA/walk-in, verifikasi bukti, catat tunai, batalkan, tandai hadir/tidak hadir, tutup slot | `/pegawai` |
| Pemilik | Lihat omzet harian/bulanan beserta rincian transaksinya, atur lapangan, harga, DP, dan batas waktu DP | `/pemilik` |

## Menjalankan di komputer sendiri

Butuh PHP 8.4 (dengan `pdo_pgsql`), Composer, Node 22, dan Docker Desktop. Langkah lengkapnya ada di [docs/SETUP.md](docs/SETUP.md).

```bash
npm install
npm run setup
npm run dev
```

Buka http://127.0.0.1:8001. Akun demo (password `DemoLola2026!`):

- Pegawai: `pegawai@lola.test`
- Pemilik: `pemilik@lola.test`

Pelanggan tidak perlu akun.

## Struktur kode

Laravel standar. Bagian yang perlu dibaca untuk memahami aturan bisnis:

| File | Isi |
|---|---|
| `config/lola.php` | Jam operasional 07.00–01.00, batas 30 hari |
| `app/Support/OperatingHours.php` | Hari operasional yang lewat tengah malam |
| `app/Services/ReservationService.php` | Pesan, tutup slot, batal, hadir/tidak hadir |
| `app/Services/PaymentService.php` | Bukti, verifikasi, tunai, aturan DP dan pelunasan |
| `app/Services/CourtLock.php` | Penguncian supaya dua permintaan bersamaan tidak dobel |
| `app/Services/HoldExpiry.php` | Reservasi tanpa DP kedaluwarsa otomatis |
| `app/Services/RevenueReport.php` | Hitungan omzet dan rinciannya |
| `app/Enums/BookingStatus.php` | Daftar status reservasi |
| `database/migrations/` | Struktur tabel |
| `routes/web.php` | Semua halaman, ditandai nomor use case |
| `resources/views/` | Tampilan Blade per aktor |
| `public/css/app.css` | Satu file CSS tanpa build step, mengikuti `docs/DESIGN.md` |
| `tests/Feature/` | Tes per use case |

Komentar di kode memakai nomor KF/KNF/UC dari dokumen analisis kebutuhan supaya tiap fitur bisa ditelusuri.

## Pemeriksaan

```bash
npm run check
npm run test:e2e
```

`check` menjalankan format kode, analisis statis, dan seluruh tes PHP. `test:e2e` menjalankan alur tiga aktor di browser (ukuran HP dan desktop). Detailnya ada di [docs/PENGUJIAN.md](docs/PENGUJIAN.md).

## Dokumen lain

- [docs/PROJECT.md](docs/PROJECT.md): kebutuhan, aturan bisnis, dan sumbernya
- [docs/SETUP.md](docs/SETUP.md): instalasi dan perintah
- [docs/PENGUJIAN.md](docs/PENGUJIAN.md): daftar tes dan cara menjalankan
- [docs/DESIGN.md](docs/DESIGN.md): warna, huruf, komponen, dan pola tampilan
- [CONTRIBUTING.md](CONTRIBUTING.md): cara kerja bareng lewat Git
- [deploy/vps/README.md](deploy/vps/README.md): menjalankan demo di VPS
