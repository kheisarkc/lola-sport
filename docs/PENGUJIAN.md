# Pengujian

Ada tiga jenis tes otomatis. Semuanya memakai database `lola_demo_test`, bukan data demo.

Tes otomatis membuktikan aturan di kode berjalan sesuai yang ditulis. Tes ini **tidak menggantikan** black-box testing manual dan UAT dengan pemilik, pegawai, dan pelanggan.

## 1. Tes fitur (`tests/Feature`)

Tes lewat HTTP seperti browser, tetapi tanpa membuka browser. Waktu dibekukan pada Selasa, 10 November 2026 pukul 10.00 WIB, supaya hasil tidak bergantung jam saat tes dijalankan.

| File | Use case | Contoh yang dicek |
|---|---|---|
| `ScheduleTest` | UC-01 | Slot 07.00–00.00 tampil, slot terisi/ditutup/lewat, nama pemesan tidak tampil, jam 00.30 masih hari operasional sebelumnya |
| `ReservationTest` | UC-02, UC-04 | Form pesan sesuai jam yang dipilih di jadwal, pilihan DP atau lunas dibawa ke langkah kirim bukti dan bisa diganti, batal sendiri sebelum bayar, pesanan aktif tampil di jadwal, tanggal kalender untuk jam lewat tengah malam, pesan tanpa akun, kode booking, nomor WA tidak valid, bentrok vs bersebelahan, main sampai 01.00, batas 30 hari, DP dibulatkan ke atas, cek pakai kode + WA, pesan langkah berikutnya per status dan tombol WhatsApp |
| `PaymentTest` | UC-03, UC-08, UC-09 | Bukti belum dihitung sebelum diverifikasi, minimal DP, pelunasan harus tepat, tidak bisa disetujui dua kali, tolak dengan alasan, tunai, bukti hanya untuk pegawai |
| `StaffTest` | UC-05, UC-06, UC-07, UC-10, UC-12, UC-13 | Login dan batas peran, catat walk-in harus sekalian bayar, form jam yang bisa diketuk, cari pesanan, cek bukti satu per layar, satu tombol utama per kartu, batal dengan DP tetap tercatat, hadir hanya kalau lunas, telat tidak menggeser jam selesai, tutup/buka slot |
| `OwnerTest` | UC-14, UC-15, UC-16 | Omzet per tanggal WIB, DP/pelunasan/bayar penuh selalu sama dengan total, DP hangus tetap masuk, bukti pending tidak masuk, harga baru hanya untuk reservasi baru, periode dan pembandingnya, tunai/QRIS dan per penjaga, reservasi, lapangan terpakai, jam ramai, belum dilunasi, saring dan unduh CSV transaksi, pemilik tanpa tombol pengubah pesanan |
| `DatabaseTest` | KF-07 | Database sendiri menolak jadwal bentrok dan nominal nol |

```bash
npm run test -- --testsuite=Feature
npm run test -- --filter="minimum DP"
```

## 2. Tes bersamaan (`tests/Concurrency`)

Dua proses PHP terpisah menekan tombol yang sama pada detik yang sama:

- dua pelanggan memesan slot yang sama → hanya satu reservasi tersimpan,
- dua pegawai menyetujui bukti yang sama → uang hanya tercatat sekali,
- dua pencatatan tunai → total tidak melebihi tagihan,
- pembatalan dan persetujuan bersamaan → hasil akhir tetap konsisten.

```bash
npm run test -- --testsuite=Concurrency
```

## 3. Tes browser (`tests/e2e`)

Playwright membuka Chromium dalam ukuran HP (Pixel 7) dan desktop, lalu menjalankan satu alur penuh:
pelanggan memilih dua jam berurutan di jadwal, mengisi data, memilih DP, dan mengirim bukti → pegawai menerima DP, mencatat pelunasan tunai → pelanggan cek status dari browser lain → pemilik melihat dua transaksi di omzet.
Tes juga memastikan halaman tidak bisa digeser ke samping di HP.

```bash
npm run test:e2e
```

## Dari tes otomatis ke black-box testing

Tabel di atas bisa dipakai sebagai daftar awal skenario black-box. Untuk laporan, tulis ulang tiap skenario dengan format kelompok (input, langkah, hasil yang diharapkan, hasil aktual), lalu jalankan manual di aplikasi. Hasil tes otomatis cukup dicantumkan sebagai pendukung, dengan tanggal dan perintah yang benar-benar dijalankan.
