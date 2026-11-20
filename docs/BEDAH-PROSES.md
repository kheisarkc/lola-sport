# Bedah proses: dari demo pertama ke versi sekarang

Catatan belajar untuk memahami apa yang sudah dibuat, kenapa bentuknya begini, dan bagaimana seharusnya prosesnya berjalan. Bukan bagian laporan; isinya bahan supaya bisa menjelaskan dan mempertanggungjawabkan aplikasi dengan kata-kata sendiri.

- **Before**: [github.com/kheisarkc/demo-lolasport](https://github.com/kheisarkc/demo-lolasport), dibuat 18 Okt 2026 (9 commit dalam satu hari), juga dipasang di VPS.
- **After**: folder `lola-sport-v1` ini, dikerjakan 8–9 Nov 2026, **belum di-commit sama sekali**.

---

## 1. Inti perbedaannya

Before dibangun dari **asumsi**. Dokumennya sendiri menulis "eksperimen pribadi berbantuan AI" dan "asumsi simulasi, bukan hasil wawancara". After dibangun ulang dari **sumber nyata**: WA pemilik (K Ica, 8 Nov), info K Pasya (11 Okt), proposal (kode UC/KF/KNF), dok. Faiz, dan keputusan tim yang dicatat.

Jadi urutannya bukan "before salah, after benar". Before berfungsi sebagai **prototipe**: alat untuk melihat bentuk sistem dan memancing pertanyaan. After adalah hasil setelah pertanyaan itu dijawab. Ini wajar dalam pengembangan perangkat lunak, asal ditulis jujur.

## 2. Before vs after

| Aspek | Before (18 Okt) | After (9 Nov) | Kenapa berubah |
|---|---|---|---|
| Teknologi | Dua aplikasi: Next.js (tampilan) + Laravel API (Sanctum) | Satu aplikasi Laravel + Blade, tanpa build step | Lebih sedikit bagian bergerak; lebih mudah dipahami dan dijalankan anggota kelompok |
| Pelanggan | Wajib daftar akun | Tanpa akun: nama + nomor WA, cek pesanan pakai kode + nomor | KF-03, kesepakatan kelompok |
| Harga | Futsal Rp150.000, badminton Rp50.000 (asumsi) | Sintetis Rp130.000, Vinil Rp160.000, badminton Rp35.000 | WA K Pasya dan K Ica |
| Jam buka | 08.00–22.00 (asumsi) | 07.00–01.00, slot 00.00 ikut tanggal sebelumnya | Data Lola Sport |
| Pesan paling jauh | 90 hari | 30 hari | KF-26 |
| Tahan slot | 30 menit | 30 menit | Tetap, keputusan tim 10 Nov 2026 |
| Bukti ditolak | Pesanan langsung batal | Bukti DP pertama ditolak = pesanan batal | Tetap, keputusan tim 10 Nov 2026 |
| Bayar di tempat | Tunai | Tunai atau QRIS di tempat | Keputusan tim |
| Pesanan walk-in | Dicatat pegawai | Wajib langsung bayar minimal DP, tidak bisa kedaluwarsa | Booking sah hanya bila ada DP |
| Pindah jadwal | Tidak ada | Tidak ada (aturannya belum dijawab pemilik) | Sama |
| Ubah harga/lapangan | Pegawai | Pemilik | UC-16; pegawai fokus operasional |
| Layar pegawai | Agenda, verifikasi satu per satu | Kartu per keadaan, satu tombol utama, menu bawah, semua bukti dalam satu daftar | Masukan uji coba: terlalu membingungkan |
| Laporan pemilik | "Penerimaan pembayaran" (DP/pelunasan/penuh) | Ringkasan dengan pembanding, tunai vs QRIS, per penjaga, lapangan terpakai, jam ramai, DP hangus, belum dilunasi, CSV | Dok. Faiz + fokus kelompok di keuangan |
| Dokumen | PROJECT, DECISIONS, DEMO-ASSUMPTIONS, dll. | PROJECT (aturan + sumber), PRD per aktor, DESIGN, PENGUJIAN | Tiap aturan bisa ditelusuri ke sumbernya |
| Git | 9 commit | 0 commit | **Perlu dibenahi** (lihat bagian 6) |

Yang **tidak berubah** dan memang inti sistemnya: uang dalam rupiah bulat, DP 50% dibulatkan ke atas, jadwal tidak boleh bentrok walau dua orang pesan bersamaan, bukti bayar disimpan privat, omzet dihitung pada tanggal uang diterima, DP hangus tetap tercatat, dan setiap perubahan tercatat siapa dan kapan.

## 3. Seharusnya prosesnya bagaimana

Laporan memakai Waterfall. Urutan idealnya, dan posisi proyek ini di tiap tahap:

| Tahap | Seharusnya menghasilkan | Yang sudah ada | Yang belum / perlu dicek |
|---|---|---|---|
| 1. Analisis kebutuhan | Hasil wawancara/observasi, masalah yang mau diselesaikan, aktor | Info dari WA pemilik dan K Pasya, proposal | Transkrip/catatan wawancara asli harus dari kelompok, jangan dibuat-buat |
| 2. Spesifikasi | Use case (UC), kebutuhan fungsional (KF) dan non-fungsional (KNF), aturan bisnis | Kode UC/KF/KNF di proposal; `docs/PROJECT.md` (aturan + sumber); `docs/PRD.md` | Pertanyaan terbuka di PRD belum dijawab pemilik |
| 3. Desain | ERD, alur (activity/flowchart), rancangan layar | Struktur tabel di `database/migrations`, mockup, `docs/DESIGN.md` | ERD dan activity diagram versi akhir untuk laporan |
| 4. Implementasi | Kode | Seluruh aplikasi | Commit bertahap |
| 5. Pengujian | Unit/feature test, black box, UAT | 80 tes otomatis + 4 tes browser (`docs/PENGUJIAN.md`) | **UAT dengan pemilik/penjaga belum dilakukan** |
| 6. Penerapan | Aplikasi bisa dipakai/didemokan | Before ada di VPS | After belum dipasang; VPS masih versi before |

**Yang terjadi sebenarnya** dengan vibe coding: tahap 4 lebih dulu (kode langsung jadi), lalu tahap 1–3 dikejar belakangan dan kode disesuaikan. Itu sebabnya terasa "langsung jadi tapi tidak paham".

Cara menuliskannya di laporan dengan jujur: prototipe awal dipakai sebagai alat bantu analisis kebutuhan, lalu kebutuhan yang sudah dikonfirmasi menjadi dasar implementasi. Jangan menulis seolah semua diagram dibuat sebelum kode kalau kenyataannya tidak begitu.

## 4. Supaya bisa mempertanggungjawabkan

Ukurannya sederhana: **bisa menjawab tanpa membuka AI**. Pertanyaan yang hampir pasti muncul saat sidang atau review, beserta tempat jawabannya di kode:

| Pertanyaan | Jawaban singkat | Lihat di |
|---|---|---|
| Bagaimana mencegah dua orang memesan jam yang sama? | Saat menyimpan, baris lapangan dikunci (`FOR UPDATE`) di dalam transaksi database. Di level database ada constraint `bookings_no_overlap` sebagai pengaman terakhir | `app/Services/CourtLock.php`, migration bookings, `tests/Concurrency` |
| Kenapa tidak pakai payment gateway? | Biaya dan pendaftaran merchant ada di pihak Lola Sport, bukan tim. Fokus kelompok di pencatatan keuangan. QRIS dicek manual oleh penjaga | `docs/PRD.md` |
| Bagaimana pembayaran diverifikasi? | Pelanggan unggah bukti, penjaga mencocokkan dengan riwayat QRIS, lalu "Uang sudah masuk" atau tolak dengan alasan | `app/Services/PaymentService.php` |
| Kapan pesanan kedaluwarsa? | 30 menit tanpa bukti. Dicek setiap kali jadwal dibuka atau ada pesanan masuk, lalu jamnya dilepas | `app/Services/HoldExpiry.php` |
| Omzet dihitung pada tanggal apa? | Tanggal uang diterima/diverifikasi (`reviewed_at`), bukan tanggal main | `app/Services/RevenueReport.php` |
| Kenapa DP hangus masuk omzet? | Uangnya memang diterima dan tidak dikembalikan (WA pemilik) | `OwnerSummary::isForfeited` |
| Pelanggan tanpa akun, aman? | Detail pesanan hanya bisa dibuka dengan kode booking + nomor WA yang cocok; jadwal publik tidak menampilkan nama | `ReservationController`, KNF-03 |
| Pegawai dan pemilik dibatasi bagaimana? | Middleware peran `can:staff` dan `can:owner`; pemilik tidak punya rute untuk mengubah pesanan | `routes/web.php` |
| Kenapa Laravel Blade, bukan Next.js seperti versi awal? | Satu aplikasi lebih sederhana dirawat tim; kebutuhan sistem tidak memerlukan tampilan yang rumit | bagian 2 |
| Bagaimana tahu aplikasinya benar? | Tes otomatis untuk tiap UC, termasuk pemesanan bersamaan, dan tes browser alur tiga aktor di HP dan desktop | `tests/`, `docs/PENGUJIAN.md` |

## 5. Urutan belajar kodenya

Ikuti **satu reservasi** dari awal sampai masuk laporan. Untuk tiap langkah, buka file-nya, baca, lalu jelaskan ulang dengan kata sendiri.

1. `routes/web.php`: peta semua alamat dan siapa yang boleh membukanya.
2. Pelanggan memilih jam: `ScheduleController` → `app/Services/ScheduleBoard.php` → `resources/views/schedule/index.blade.php`.
3. Pelanggan memesan: `ReservationRequest` (validasi) → `ReservationService` (hitung harga, DP, kunci lapangan, simpan).
4. Pelanggan kirim bukti: `ReservationController` → `PaymentService`.
5. Penjaga memeriksa: `Staff\PaymentController` → `PaymentService::approve/reject`.
6. Pelunasan dan kehadiran: `Staff\PaymentController::cash`, `ReservationService::checkIn`.
7. Pemilik melihat: `Owner\RevenueController` → `RevenueReport` dan `OwnerSummary`.
8. Struktur data: `database/migrations` dan `app/Models`.

Latihan yang membuat paham, bukan sekadar membaca:

- Ubah satu aturan (misalnya batas pesan 30 → 14 hari di `config/lola.php`), jalankan `npm run test`, lihat tes mana yang gagal dan kenapa.
- Tulis satu tes sendiri di `tests/Feature`, misalnya "pelanggan tidak bisa memesan jam yang sudah lewat".
- Gambar ERD dan activity diagram dari kode, bukan dari AI. Kalau bisa menggambarnya, berarti sudah paham alurnya.

## 6. Dari segi penilaian

- **Pemakaian AI wajib dideklarasikan**, dan teks laporan harus ditulis ulang dengan kata sendiri. Kode boleh dibantu AI, tetapi pemahaman tidak bisa diwakilkan.
- **Bukti proses penting.** Saat ini v1 tidak punya riwayat commit, jadi tidak terlihat bagaimana aplikasinya berkembang. Sebaiknya commit dalam beberapa langkah yang bermakna (misalnya: dasar aplikasi, alur pelanggan, alur pegawai, dashboard pemilik, dokumen), bukan satu commit besar.
- **Jangan mengarang data**: wawancara, hasil UAT, atau persetujuan pemilik hanya ditulis kalau benar-benar terjadi. Yang belum dikonfirmasi ditulis sebagai asumsi atau keputusan tim.
- **Ini kerja kelompok.** Anggota lain juga perlu paham minimal alurnya. Repo before menyebut dirinya "eksperimen pribadi, belum hasil kelompok", jadi sepakati dengan kelompok bahwa v1 yang dipakai, dan sepakati definisi omzet dengan Fizy (EDM).
- **Tiap perubahan dari rancangan perlu alasan.** Tabel di bagian 2 bisa menjadi dasar subbab "perubahan selama implementasi".

## 7. Yang masih harus dilakukan sebelum bisa disebut selesai

1. Jawaban pemilik untuk pertanyaan terbuka (`docs/PRD.md`): rekening QRIS, siapa yang melihat uang masuk, aturan pindah jadwal.
2. UAT nyata dengan pemilik dan penjaga, dicatat apa adanya.
3. Commit bertahap, lalu push ke repo kelompok.
4. Pasang v1 di VPS menggantikan versi before (perlu izin dan langkah tersendiri).
5. Sinkron dengan bagian EDM soal data dan definisi angka.
