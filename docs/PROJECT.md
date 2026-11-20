# Kebutuhan dan aturan bisnis

Nomor KF (kebutuhan fungsional), KNF (non-fungsional), dan UC (use case) mengikuti dokumen analisis kebutuhan kelompok.
Setiap aturan punya kolom **sumber**, supaya jelas mana yang sudah dikonfirmasi dan mana yang masih asumsi.

Arti sumber:

- **WA K Pasya**: dijawab K Pasya (pihak Lola Sport) lewat WhatsApp, 11 Oktober 2026.
- **Wawancara**: wawancara dengan K Pasya, 9 Oktober 2026.
- **Asumsi kelompok**: diputuskan kelompok sementara, **belum** dikonfirmasi Lola Sport.

## Aturan yang dipakai demo

| Aturan | Nilai di demo | Sumber | Diatur di |
|---|---|---|---|
| Harga futsal sintetis | Rp130.000/jam | WA K Pasya | data lapangan (pemilik bisa ubah) |
| Harga futsal vinil | Rp160.000/jam | WA K Pasya | data lapangan |
| Harga badminton | Rp35.000/jam, 2 lapangan | Harga: WA owner K Ica, 8 Nov 2026. Jumlah lapangan: wawancara | data lapangan |
| Jam operasional | 07.00–01.00 setiap hari | Catatan Kei dari jam buka di Google, **belum dikonfirmasi** | `config/lola.php` |
| Slot 00.00–01.00 | Ikut tanggal operasional sebelumnya | Asumsi kelompok | `OperatingHours` |
| Durasi | Kelipatan 1 jam, minimal 1 jam | Asumsi kelompok | `ReservationRequest` |
| Pesan paling jauh | 30 hari ke depan (KF-26) | Asumsi kelompok | `config/lola.php` |
| Pelanggan | Tanpa akun, cukup nama + nomor WA (KF-03) | Kesepakatan kelompok | |
| Minimal DP | 50% total sewa, dibulatkan ke atas (KF-25). Boleh langsung bayar penuh | WA owner K Ica, 8 Nov 2026 (pembulatan: asumsi kelompok) | pengaturan pemilik |
| Batas kirim bukti bayar (DP atau lunas) | 30 menit, tidak lewat jam main (KF-08) | Keputusan tim, lihat [PRD.md](PRD.md) | pengaturan pemilik |
| Pembayaran berikutnya | Harus tepat sisa tagihan | Asumsi kelompok | `PaymentService` |
| Lunas sebelum main | Pegawai tidak bisa menandai hadir sebelum lunas (KF-23) | Asumsi kelompok, **perlu dikonfirmasi** | `ReservationService::checkIn` |
| Pelanggan telat | Tetap main, jam selesai tidak mundur (KF-24) | Asumsi kelompok, **perlu dikonfirmasi** | `ReservationService::checkIn` |
| Tandai hadir | Paling cepat 60 menit sebelum main | Asumsi kelompok | `config/lola.php` |
| Bukti ditolak | Bukti DP pertama ditolak = pesanan batal; bukti pelunasan ditolak = pesanan tetap terkonfirmasi | Keputusan tim (10 Nov 2026) | `PaymentService::reject` |
| Pembatalan dan tidak datang | Oleh pegawai. DP hangus, uang yang sudah diterima tidak dikembalikan | WA owner K Ica, 8 Nov 2026 | `ReservationService::cancel`, `markNoShow` |
| Pesanan dari pegawai (walk-in/telepon) | Langsung mencatat uang yang diterima (DP atau lunas, tunai atau QRIS di tempat), jadi langsung terkonfirmasi dan tidak bisa kedaluwarsa | Keputusan tim: booking sah hanya bila ada DP atau lunas | `Staff\BookingController::store` |
| Batal sendiri sebelum bayar | Pelanggan boleh membatalkan pesanannya selama belum mengirim bukti apa pun; slot langsung dibuka lagi. Setelah ada bukti, pembatalan lewat pegawai | Asumsi kelompok (tidak ada uang yang terlibat) | `ReservationService::cancelUnpaidHold` |
| Omzet | Uang yang sudah diverifikasi, dihitung pada tanggal diverifikasi. DP hangus tetap masuk | Asumsi kelompok, perlu disepakati dengan bagian EDM | `RevenueReport` |
| Pindah jadwal (UC-11) | Belum dibuat. Owner: boleh pindah jam/hari bila slot tujuan kosong. Batas (1x, paling lambat H-1, DP ikut pindah) **belum dijawab** | WA owner K Ica, 8 Nov 2026 | |
| Tutup slot (UC-13) | Sudah dibuat. Owner: fitur tidak wajib, tetapi boleh dibuat | WA owner K Ica, 8 Nov 2026 | `ReservationService::block` |
| Metode bayar | QRIS (online, kirim bukti) atau tunai di tempat. Tidak ada transfer bank. Pesanan web membayar DP atau lunas lewat QRIS; tunai di tempat untuk pelunasan dan pesanan yang dicatat pegawai | WA owner K Ica, 8 Nov 2026 | `PaymentMethod` |
| Kode QRIS asli | Belum ada, hanya teks simulasi | Tidak dipasang di demo publik | |

## Kebutuhan non-fungsional yang dijaga kode

| Kode | Kebutuhan | Cara di demo |
|---|---|---|
| KNF-01 | Nyaman di HP | CSS mobile-first, dites di ukuran Pixel 7 |
| KNF-02 | Halaman pegawai/pemilik sesuai peran | Middleware `can:staff` dan `can:owner` |
| KNF-03 | Data pelanggan dan bukti tidak tampil publik | Jadwal publik tanpa nama/nomor; bukti di storage privat, hanya bisa dibuka pegawai |
| KNF-04 | Waktu dalam WIB | Aplikasi dan koneksi database memakai `Asia/Jakarta` |
| KNF-05 | Uang dalam rupiah bulat | Kolom integer, tanpa desimal |
| KF-07 | Tidak ada jadwal bentrok, termasuk permintaan bersamaan | Kunci baris lapangan + constraint `bookings_no_overlap` di PostgreSQL |

## Status reservasi dan status pembayaran

Keduanya dipisah.

- **Status reservasi:** Menunggu pembayaran → Menunggu verifikasi → Terkonfirmasi → Hadir / Tidak hadir. Bisa juga Dibatalkan atau Kedaluwarsa.
- **Status pembayaran:** Belum bayar → DP diterima → Lunas. Dihitung dari jumlah pembayaran yang sudah diterima.

## Yang belum termasuk

Pindah jadwal, pembayaran online sungguhan, notifikasi WhatsApp otomatis, refund, dan akun pelanggan.
Demo ini juga belum diuji oleh pengguna sungguhan (UAT) dan belum disetujui pemilik.
