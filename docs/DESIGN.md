# Panduan Tampilan Lola Sport

Dokumen ini menjadi satu-satunya acuan tampilan untuk `public/css/app.css` dan `resources/views/`. Fokus utamanya halaman publik yang dibuka pelanggan dari handphone. Pola pemesanan mengadaptasi situs booking lapangan pada umumnya; warna, aset, dan aturan bisnis tetap milik Lola Sport.

Setiap ketentuan diberi salah satu label berikut:

- **SEKARANG**: sudah diterapkan di kode saat dokumen ini diperbarui.
- **TARGET**: arah perbaikan yang disepakati untuk dikerjakan; belum tentu sudah diterapkan.
- **TERBUKA**: belum diputuskan. Jangan ditebak saat implementasi.

Dokumen ini bukan laporan UAT dan bukan bukti persetujuan pemilik usaha.

## 1. Prinsip

1. **Foto venue asli.** Hero dan kartu lapangan memakai foto Lola Sport, bukan ilustrasi atau foto stok.
2. **Jadwal adalah tugas utama.** Hero memberi konteks secukupnya; jadwal harus cepat terlihat.
3. **Mobile lebih dulu.** Lebar 320–430 px menjadi acuan desain. Halaman tidak boleh bergulir horizontal, kecuali strip tanggal yang memang dapat digeser.
4. **Status tidak bergantung pada warna.** Setiap status slot dan booking punya teks atau pola.
5. **Satu tombol utama per layar.** Tombol merah hanya untuk langkah berikutnya; aksi lain memakai `btn-outline`.
6. **Tanpa dekorasi yang menyaingi jadwal.** Tidak ada carousel, animasi latar, gradien warna-warni, atau emoji sebagai ikon.

## 2. Token

### SEKARANG

| Token | Nilai | Peran |
| --- | --- | --- |
| `--brand` | `#c8414f` | Tombol utama, tanggal aktif, slot terpilih |
| `--brand-dark` | `#a8323f` | Hover tombol utama, teks slot terpilih |
| `--brand-soft` / `--brand-line` | `#fbecee` / `#f1c7cc` | Latar dan garis aksen merah |
| `--navy` | `#2f4a6b` | Judul, teks slot, filter aktif |
| `--navy-dark` | `#1f3550` | Footer, gradasi hero |
| `--success` | `#1e7b4f` | Status lunas atau berhasil |
| `--danger` | `#b42335` | Error dan penolakan, selalu disertai teks |
| `--amber` | `#8a5a00` | Peringatan |
| `--ink` / `--body` / `--muted` | `#1f2933` / `#4b5563` / `#6b7280` | Teks utama, isi, keterangan |
| `--faint` | `#9ca3af` | Hanya untuk elemen nonteks atau teks nonaktif |
| `--line` | `#e6e8eb` | Garis kartu |
| `--bg` / `--card` / `--surface` | `#fafafa` / `#ffffff` / `#f3f4f6` | Latar halaman, kartu, bidang redup |
| `--radius` / `--radius-lg` | `8px` / `12px` | Kontrol dan kartu |
| `--topbar-h` | `60px` | Tinggi topbar, offset elemen sticky dan anchor |

Setiap warna status punya pasangan `-soft` (latar) dan `-line` (garis). Huruf memakai **Rubik** dengan cadangan `system-ui`. Lebar isi maksimum `1120px`.

Breakpoint yang dipakai: `360px` (slot jadi dua kolom di bawahnya), `420px` (header publik dipadatkan), `520px` (bar pilihan dua baris), `760px` (kartu lapangan dan langkah tampil berdampingan), dan `860px` (ringkasan booking di samping formulir).

### TARGET

- Teks informatif minimal memakai `--muted` (`#6b7280`, kontras sekitar 4,8:1 di atas putih). `--faint` tidak dipakai untuk teks yang dibaca pelanggan.
- Skala jarak: 4, 8, 12, 16, 24, 32, 48 px. Jarak 8–12 px dalam satu kelompok, 16–24 px antarisi kartu, 32–48 px antarseksi.
- Body dan input 16 px; label penting dan rentang jam minimal 14 px; caption 12 px hanya untuk metadata yang tidak menentukan keputusan.
- Kontrol penting punya area sentuh minimal 44 × 44 px. **SEKARANG** sudah berlaku untuk menu, chip, panah tanggal, tautan Ubah jam, dan tautan staf.
- **SEKARANG:** pengguna dengan `prefers-reduced-motion: reduce` tidak mendapat scroll halus dan transisi.
- Transisi ringan sekitar 150 ms dan menghormati `prefers-reduced-motion`.

## 3. Struktur halaman

**SEKARANG: jadwal (`/`):** header → hero foto → langkah pesan → judul dan filter olahraga → panel tanggal dan legenda (sticky) → kartu lapangan → cara pesan → footer. Bar pilihan muncul di bawah layar setelah slot dipilih.

**SEKARANG: data dan bayar (`/pesan`):** langkah 2 → ringkasan dengan foto dan tautan Ubah jam → data pemesan → pilihan DP atau lunas → Pesan dan bayar.

**SEKARANG: kirim bukti (`/reservasi/{kode}`):** langkah 3 → kode booking → hitung mundur waktu tahan → nominal sesuai pilihan → QRIS simulasi → unggah bukti.

**SEKARANG: cek reservasi (`/cek`):** kode booking dan nomor WhatsApp, lalu Lihat reservasi. Pelanggan tidak perlu akun.

**TARGET:** urutan dipertahankan. Hero dan panel tanggal diringkas di HP agar jadwal lebih cepat terlihat.

## 4. Komponen

| Komponen | Kelas / berkas |
| --- | --- |
| Header | `.topbar`, `.topbar-public`, `layouts/app.blade.php` |
| Footer | `.footer`, `.footer-brand`, `.staff-link` |
| Hero | `.hero`, `.hero-photo`, `.hero-meta` |
| Langkah pesan | `<x-booking-steps :current="n" />` |
| Filter dan tanggal | `.chips`, `.picker`, `.date-scroller` |
| Kartu lapangan | `.court-card`, `.court-photo`, `.court-body` |
| Slot | `.slot` dengan status di bawah |
| Bar pilihan | `.select-bar` |
| Pilihan bayar | `.pay-choice`, `.pay-option` |
| QRIS | `.qris-box` |

### Header dan footer

- **SEKARANG:** logo, "Lola Sport", tautan Jadwal dan Cek pesanan. Tautan "Masuk pegawai" hanya ada di footer. Di bawah 420 px logo dan nama merek mengecil sehingga kedua menu tetap utuh pada 320 px.
- **TARGET:** pada 320 px seluruh label menu tetap utuh; subjudul merek boleh disembunyikan lebih dulu. Menu aktif ditandai dengan teks tebal dan garis, bukan warna saja.
- **SEKARANG:** tidak ada banner demo. Halaman masuk menampilkan akun demo pegawai dan pemilik beserta kata sandinya; kotak QRIS tetap menyebut bahwa pembayaran masih simulasi.

### Hero

- **SEKARANG:** foto venue, judul, lokasi, jam buka, cabang olahraga, harga mulai, dan satu tombol Lihat jadwal.
- **TARGET:** tinggi awal sekitar 260–320 px di HP, boleh tumbuh saat teks membungkus. Tombol Lihat jadwal menggulir ke judul pemilih lapangan tanpa tertutup header.

### Filter olahraga dan tanggal

- **SEKARANG:** filter Semua/Futsal/Badminton, strip tanggal dengan label "Hari ini" dan "Besok", panel tanggal sticky. Mengganti tanggal memuat ulang halaman sehingga pilihan jam lama ikut hilang.
- **TARGET:** judul menampilkan hari dan tanggal lengkap; strip menunjukkan bahwa masih ada tanggal lain; tanggal aktif selalu terlihat. Evaluasi apakah panel tanggal perlu tetap sticky di HP bila header dan bar pilihan sudah menempel.

### Kartu lapangan

- **SEKARANG:** foto, nama, harga per jam, jumlah jam kosong, grid slot. Foto di atas pada HP, di kiri pada desktop.
- **TARGET:** nama, harga, dan ketersediaan terbaca sebelum slot. Foto HP sekitar 120–160 px agar tidak mendominasi. Lima kolom di desktop. (SEKARANG: dua kolom di bawah 360 px dan tiga kolom di HP lain.) Bila tarif semua slot sama, harga cukup ditulis di kepala kartu.

### Slot

| Status | Kelas | SEKARANG | Bisa dipilih |
| --- | --- | --- | --- |
| Kosong | `.slot-available` | Putih, garis navy muda | Ya |
| Dipilih | `.slot-available.selected` | Garis putus-putus merah dan tanda centang | Ya; klik ujung untuk melepas |
| Terisi | `.slot-booked` | Arsiran abu-abu, teks "Terisi" | Tidak |
| Ditutup | `.slot-blocked` | Abu-abu polos, teks "Ditutup" | Tidak |
| Lewat | `.slot-past` | Abu-abu dengan garis putus-putus, tersembunyi secara bawaan, dapat ditampilkan lewat toggle | Tidak |

**TARGET:**

- Rentang jam menjadi isi utama. Label "60 menit" tidak perlu diulang di setiap slot selama semua slot berdurasi satu jam.
- Slot terpilih memakai latar `--brand-soft`, garis merah, teks kontras, dan tanda centang. Status programatis (`aria-pressed`) harus sama dengan tampilan.
- Slot dapat dioperasikan dengan Tab, Enter, dan Space. Fokus terlihat tanpa menggeser layout.
- Bila jam yang diklik tidak bisa digabung dengan pilihan sebelumnya, beri umpan balik singkat, jangan hanya mengganti pilihan diam-diam.
- Satu pesanan tetap satu lapangan dengan jam berurutan.

### Bar pilihan

- **SEKARANG:** lapangan, jumlah jam, rentang jam, total, Batal, dan Lanjut. Di bawah 520 px ringkasan mendapat satu baris penuh tanpa dipotong, dan Batal tetap tampil. Tanggal belum ditampilkan. Pilihan tetap aktif walaupun filter olahraga menyembunyikan kartunya.
- **TARGET:** tampilkan lapangan, tanggal, rentang jam, durasi, dan total tanpa memotong tanggal atau jam; boleh dua baris di layar sempit. Hapus pilihan tetap tersedia di HP.
- **TARGET:** bila pilihan berada di luar filter aktif, tampilkan pemberitahuan dekat hasil, misalnya "Pilihan aktif: Futsal Sintetis, Sel 10 Nov, 11.00–13.00", dengan aksi Lihat pilihan dan Hapus pilihan.
- **TARGET:** perubahan ringkasan diumumkan singkat untuk pembaca layar (`aria-live="polite"`); bar tidak menutupi slot terakhir, tombol, footer, atau safe area perangkat.

### Data, pembayaran, dan cek reservasi

- **SEKARANG:** ringkasan pesanan dan tautan Ubah jam yang mengembalikan tanggal, lapangan, jam, dan durasi. Nama dan WhatsApp memakai `autocomplete` dan keyboard telepon. Pilihan DP atau lunas berupa kartu radio dengan nominal; pilihan itu dibawa ke langkah kirim bukti.
- **TARGET:** istilah "Total sewa", "Bayar sekarang", dan "Sisa pembayaran" dipakai konsisten. Error tampil dekat field dan isian valid tidak hilang. Tombol kirim memberi tanda sedang diproses dan mencegah kirim ganda.
- **TARGET:** saat cek reservasi gagal, minta pelanggan memeriksa kedua isian tanpa membuka data orang lain. Status booking dan status pembayaran tetap dua label berbeda.
- Angka DP, waktu tahan, pembulatan, dan status pembayaran mengikuti aturan aplikasi. Dokumen ini tidak mengubahnya.

## 5. Jam setelah tengah malam

**SEKARANG: aturan aplikasi:** jadwal memakai **hari operasional**. Venue buka 07.00 sampai 01.00, sehingga slot 00.00–01.00 pada tanggal operasional X disimpan sebagai pukul 00.00 tanggal kalender X+1 (`app/Support/OperatingHours.php`, `slotStart()`).

**SEKARANG: tampilan:** slot 00.00 di jadwal diberi label tanggal kalender (misalnya "Sab, 14 Nov") menggantikan "60 menit", dan nama aksesibelnya menyebut tanggal lengkap. Ringkasan langkah 2, halaman reservasi, verifikasi pembayaran, dan detail booking pegawai menampilkan tanggal dari `starts_at`. Tabel jadwal pegawai tetap per hari operasional dengan catatan tanggal pada baris setelah tengah malam. Cara data disimpan tidak berubah.

**TARGET:** bar pilihan ikut menampilkan tanggal (lihat bagian Bar pilihan).

## 6. Foto dan aset

- Logo dan foto disimpan sebagai WebP di `public/images/`.
- Pemetaan nama lapangan ke foto ada di `config/lola.php` (`court_photos`), dengan cadangan per cabang olahraga (`sport_photos`). Bila keduanya tidak cocok, `Court::photoUrl()` memakai `images/hero.webp`.
- Setiap `<img>` punya `width`/`height` atau rasio tetap agar layout tidak bergeser, serta `alt` yang menyebut lapangan.
- Foto dan logo merek lain tidak dipakai dan tidak di-hotlink.

## 7. Bahasa dan format

- Bahasa Indonesia yang ringkas. Istilah tetap: "lapangan", "jam", "reservasi", "kode booking", "total sewa", "bayar sekarang", "sisa pembayaran".
- Rupiah: `Rp35.000`. Rentang jam: `11.00–13.00 WIB`. Ringkasan memakai tanggal lengkap.
- Harga, nama lapangan, dan jumlah slot selalu dari data aplikasi, tidak ditulis langsung di template.
- Hindari kalimat promosi umum seperti "pengalaman terbaik" tanpa informasi nyata.

## 8. Aksesibilitas dan umpan balik

- Kontras teks biasa minimal 4,5:1 dan teks besar 3:1, termasuk indikator fokus.
- Label selalu terlihat, bukan placeholder saja. Urutan fokus mengikuti urutan baca.
- Hasil kosong menjelaskan penyebabnya (filter atau tanggal penuh) dan memberi langkah berikutnya, misalnya "Lihat besok".
- Bila jam sudah diambil orang lain saat dikirim, pelanggan dikembalikan ke jadwal dengan pesan. Server tetap menjadi sumber kebenaran.

## 9. Keputusan terbuka

- **Warna dan huruf.** Merah dan navy diambil dari logo dan venue. Penyesuaian palet dan huruf belum diputuskan.
- **Logo.** `logo.webp` masih hasil potong foto; versi vektor dari pemilik belum ada.
- **Pesanan lintas lapangan.** Saat ini tidak didukung dan tidak direncanakan tanpa keputusan baru.
- **Kalender untuk tanggal jauh.** Belum dibutuhkan; jangan ditambahkan otomatis.

## 10. Checklist pemeriksaan

Checkbox kosong berarti belum diperiksa, bukan gagal.

- [ ] Pada 320 × 640 dan 390 × 844, logo dan menu utuh, tanpa scroll horizontal.
- [ ] Hero terbaca dan jadwal dapat dicapai tanpa guliran panjang.
- [ ] Filter, tanggal aktif, dan status slot terbaca lewat teks atau bentuk, bukan warna saja.
- [ ] Pilih dua jam berurutan: tanda, durasi, rentang, dan total konsisten.
- [ ] Pilih Futsal lalu filter Badminton: pilihan tetap jelas, dapat ditemukan dan dihapus.
- [ ] Ganti tanggal: tidak ada pilihan atau URL yang membawa tanggal lama.
- [ ] Bar pilihan menampilkan lapangan, tanggal, jam, durasi, dan total tanpa terpotong pada 320 px.
- [ ] Slot 00.00–01.00 menampilkan tanggal kalender yang benar di semua halaman.
- [ ] Ubah jam mengembalikan pilihan yang sama.
- [ ] Slot dapat dioperasikan dengan keyboard dan perubahan pilihan diumumkan.
- [ ] Tidak ada isi yang tertutup bar sticky, safe area, atau keyboard layar.
- [ ] Validasi formulir, pilihan DP/lunas, tombol sibuk, dan hasil kosong sudah diperiksa.
- [ ] Kontras teks dan kontrol diperiksa dengan alat, bukan hanya dari screenshot.
- [ ] Desktop 1280 × 800 tetap rapi; halaman pegawai dan pemilik tidak rusak oleh perubahan gaya bersama.

Pemeriksaan lewat viewport browser bukan pengujian oleh pelanggan. Pengujian di handphone fisik, pembaca layar, dan UAT pemilik dicatat terpisah bila benar-benar dilakukan.
