# PRD alur pengguna

Dokumen ini menjadi pegangan alur tiap aktor. Sumber setiap aturan dicatat supaya jelas mana yang dari Lola Sport, mana yang dari proposal, dan mana yang keputusan tim. Aturan lengkap beserta angkanya ada di [PROJECT.md](PROJECT.md).

Urutan sumber bila bertentangan: jawaban pihak Lola Sport, lalu proposal dan dokumen perancangan kelompok, lalu keputusan tim.

Fokus proyek adalah pencatatan: setiap reservasi dan setiap rupiah yang masuk tercatat, tersambung ke pesanannya, dan bisa ditelusuri sampai ke laporan omzet. Alur pelanggan dibuat sesederhana mungkin supaya datanya masuk dengan rapi.

## Bagian 1: Pelanggan

Tujuan: pelanggan memesan lapangan sendiri tanpa harus chat pegawai, dan setiap pesanan langsung tercatat beserta pembayarannya.

Prinsip: tanpa akun, satu cara bayar online (QRIS), dan setiap status menjawab "sekarang aku harus apa?".

### Alur utama

| # | Langkah | Yang dilihat pelanggan |
|---|---|---|
| 1 | Pilih tanggal dan lapangan | Jam kosong, terisi, dan ditutup. Nama pemesan lain tidak tampil |
| 2 | Pilih jam | Satu jam atau beberapa jam berurutan, total harga langsung tampil |
| 3 | Isi data dan cara bayar | Nama, nomor WhatsApp, pilih DP 50% atau lunas (sekali, di langkah ini) |
| 4 | Pesanan dibuat | Kode booking, nominal yang harus dibayar, batas waktu kirim bukti |
| 5 | Bayar QRIS dan unggah bukti | QRIS Lola Sport dan tombol unggah bukti |
| 6 | Menunggu pemeriksaan | Status "Menunggu verifikasi". Jam tetap terkunci |
| 7 | Hasil pemeriksaan | Diterima: Terkonfirmasi. Ditolak: alasan tampil, pesanan batal, jam dibuka lagi |

Kembali ke pesanan: kode booking + nomor WhatsApp lewat menu Cek pesanan, dari HP mana pun. Di browser yang sama, kartu "Pesanan kamu" tampil di halaman jadwal.

### Pesan langkah berikutnya

| Status | Pesan |
|---|---|
| Menunggu pembayaran | Bayar lewat QRIS dan kirim bukti sebelum batas waktu. Lewat dari itu pesanan batal otomatis |
| Menunggu verifikasi | Bukti diterima, penjaga sedang mengecek uang masuk |
| Bukti ditolak | Alasan penolakan, pesanan batal, tombol WhatsApp untuk bertanya |
| Terkonfirmasi, DP | Jadwal aman. Sisa dibayar sebelum main lewat QRIS atau tunai di tempat |
| Terkonfirmasi, lunas | Jadwal aman. Tunjukkan kode booking ke penjaga saat datang |
| Kedaluwarsa | Bukti tidak dikirim sampai batas waktu. Pesan ulang dari jadwal |
| Dibatalkan / Tidak hadir | Pesanan selesai. Uang yang sudah dibayar tidak dikembalikan |
| Hadir | Selamat bermain |

Tombol WhatsApp ke Lola Sport tampil saat menunggu verifikasi, saat bukti ditolak, dan saat terkonfirmasi, dengan pesan berisi kode booking. Tombol ini untuk bertanya. Bukti bayar tetap diunggah di web supaya tercatat. Nomor di demo adalah nomor contoh sampai pemilik mengizinkan nomor asli.

### Aturan

| Aturan | Sumber |
|---|---|
| Tanpa akun, cukup nama dan nomor WhatsApp | Kesepakatan kelompok |
| DP minimal 50%, boleh langsung lunas | Owner, 8 Nov 2026 |
| Bayar online hanya QRIS, tanpa payment gateway, diverifikasi manual | Owner 8 Nov 2026 dan batasan proposal |
| Verifikasi berarti mengecek uang masuk di riwayat QRIS, bukan hanya melihat foto | Dokumen perancangan (cek mutasi) |
| Pembatalan dan pindah jadwal lewat penjaga, DP hangus | Owner, 8 Nov 2026 |
| Jam ditahan 30 menit sampai bukti dikirim | Keputusan tim (10 Nov 2026) |
| Batal sendiri sebelum mengirim bukti | Keputusan tim |
| Pelunasan lewat QRIS di web atau tunai di tempat | Owner, 8 Nov 2026 |

### Di luar cakupan

Payment gateway dan konfirmasi otomatis (rencana pengembangan di Bab V), notifikasi WhatsApp otomatis, akun pelanggan, pindah jadwal sendiri, refund, nominal unik.

## Bagian 2: Pegawai (penjaga)

Tujuan: setiap pesanan dan setiap rupiah yang diterima di tempat tercatat, dan jadwal di lapangan selalu sama dengan jadwal di web. Layar dirancang untuk HP dan untuk orang yang tidak terbiasa memakai aplikasi.

### Prinsip tampilan

- Menu bawah yang selalu terlihat: Hari ini, Cek bayar (dengan jumlah bukti menunggu), Pesanan, Lainnya.
- Daftar pesanan berupa kartu, bukan tabel. Satu kartu, satu tombol utama sesuai keadaannya.
- Huruf minimal 16px, tombol minimal 48px tinggi.
- Bahasa sehari-hari: "Pelanggan datang", "Tidak datang", "Uang sudah masuk".
- Tindakan yang tidak bisa dibatalkan selalu meminta konfirmasi.

### Layar

| Layar | Isi |
|---|---|
| Hari ini | Pilih tanggal (7 hari ke depan atau kalender), kotak cari (kode, nama, nomor WA), peringatan bukti yang menunggu, kartu pesanan dikelompokkan: perlu cek bukti, belum lunas, lunas tinggal datang, sedang main, menunggu pelanggan bayar, lapangan ditutup, lewat jam belum ditandai, selesai |
| Cek bayar | Semua bukti yang menunggu dalam satu daftar: foto bukti, nominal, pemesan, jadwal, tombol "Uang sudah masuk" atau "Tidak ada di riwayat QRIS" |
| Pesanan baru | Pilih tanggal dan lapangan, ketuk jam kosong, isi nama dan WA, pilih uang yang diterima (DP atau lunas) dan cara bayar (tunai atau QRIS di tempat), simpan |
| Detail pesanan | Tombol utama di atas, rincian, chat WhatsApp pelanggan, tidak datang, batalkan, riwayat pembayaran dan perubahan |
| Lainnya | Tutup lapangan, keluar |

### Tombol utama di kartu dan detail

| Keadaan pesanan | Tombol |
|---|---|
| Ada bukti menunggu | Cek bukti |
| Terkonfirmasi, masih ada sisa | Terima tunai sebesar sisa, atau QRIS di tempat |
| Lunas dan sudah masuk waktu datang | Pelanggan datang |
| Jam main sudah lewat, belum ditandai | Tandai tidak datang |
| Menunggu pelanggan bayar online | Tidak ada, tampil batas waktunya |

### Alur

| # | Kejadian | Yang dilakukan pegawai | Hasil |
|---|---|---|---|
| 1 | Bukti QRIS masuk | Cek uang masuk di aplikasi QRIS, lalu Uang sudah masuk atau Tolak dengan alasan | Terkonfirmasi, atau pesanan batal bila itu bukti DP pertama |
| 2 | Pelanggan datang atau telepon untuk pesan | Pilih jam, isi nama dan WA, catat uang yang diterima (DP atau lunas, tunai atau QRIS) | Langsung terkonfirmasi, tidak bisa kedaluwarsa |
| 3 | Pelunasan di tempat | Terima tunai atau QRIS di tempat sebesar sisa | Lunas |
| 4 | Pelanggan datang main | Pelanggan datang (hanya bila lunas, paling cepat 60 menit sebelum main) | Hadir |
| 5 | Pelanggan tidak datang | Tidak datang (setelah jam mulai) | Tidak hadir, DP tetap tercatat |
| 6 | Pelanggan minta batal lewat WA | Batalkan dengan alasan | Dibatalkan, DP hangus, jam dibuka lagi |
| 7 | Lapangan perbaikan atau acara | Tutup lapangan dengan alasan | Jam tidak bisa dipesan |

Semua tindakan tercatat di riwayat perubahan: siapa, kapan, dan apa.

### Aturan

| Aturan | Sumber |
|---|---|
| Pesanan dari pegawai harus langsung membayar minimal DP | Prinsip tim: booking sah hanya bila ada DP atau lunas |
| Pelunasan tepat sejumlah sisa tagihan | Keputusan tim |
| Hadir hanya bila lunas | Keputusan tim |
| QRIS yang dibayar di depan penjaga langsung tercatat diterima, tanpa unggah bukti | Keputusan tim |
| Batal atau tidak datang: DP hangus, tanpa refund | Owner, 8 Nov 2026 |

## Pertanyaan terbuka untuk pihak Lola Sport

1. QRIS tersambung ke akun merchant atau rekening pribadi pemilik?
2. Siapa yang bisa melihat uang masuk: penjaga atau hanya pemilik? Jawaban ini menentukan siapa yang memverifikasi.
3. Pindah jadwal: boleh berapa kali, paling lambat kapan, dan bagaimana bila harganya berbeda? Sampai dijawab, pindah jadwal belum dibuat.

## Bagian 3: Pemilik

Tujuan: pemilik bisa melihat berapa uang yang masuk, dari mana, dicatat siapa, dan seberapa ramai lapangan, tanpa bisa mengubah pesanan atau pembayaran (dok. Faiz: "tanpa bisa mengubah data operasional"). Dashboard dibangun di aplikasi ini.

### Layar

Menu bawah: Ringkasan, Transaksi, Pengaturan. Setiap layar laporan punya pilihan periode: Hari ini, 7 hari, Bulan ini (bawaan), Bulan lalu, atau tanggal sendiri (paling panjang 93 hari).

| Layar | Isi |
|---|---|
| Ringkasan | Empat angka utama dengan perbandingan periode sebelumnya: uang masuk, reservasi, lapangan terpakai (%), belum dilunasi. Lalu uang masuk menurut cara bayar (tunai, QRIS lewat web, QRIS di tempat) dan jenis (DP, pelunasan, bayar penuh, termasuk DP hangus), uang yang dicatat tiap penjaga, grafik uang masuk per hari, jam paling ramai, dan pemakaian per lapangan |
| Transaksi | Kartu per pembayaran yang diterima: nominal, jenis, cara bayar, pemesan dan kode, jadwal main, waktu diterima dan siapa yang mencatat. Saring: Semua, Tunai, QRIS, DP hangus. Unduh CSV sesuai saringan |
| Pengaturan | Minimal DP, batas kirim bukti, nama/jenis/harga/aktif tiap lapangan, tambah lapangan, keluar |

### Definisi angka

| Angka | Cara hitung |
|---|---|
| Uang masuk | Pembayaran yang sudah diterima, dihitung pada tanggal diterima/diverifikasi. Bukti yang belum dicek tidak dihitung |
| Pembanding | Hari ini dibanding kemarin; 7 hari dibanding 7 hari sebelumnya; bulan ini dibanding tanggal yang sama bulan lalu (1-10 Nov vs 1-10 Okt); bulan lalu dibanding bulan sebelumnya; tanggal sendiri dibanding rentang sepanjang itu tepat sebelumnya |
| Reservasi | Pesanan yang jadi (terkonfirmasi, hadir, tidak datang) menurut tanggal main. Tutup lapangan tidak dihitung |
| Lapangan terpakai | Jam terpesan dibagi (jumlah hari x lapangan aktif x 18 jam buka) |
| Belum dilunasi | Sisa tagihan pesanan terkonfirmasi yang jadwalnya hari ini atau nanti |
| DP hangus | Uang dari pesanan yang dibatalkan atau tidak datang. Sudah termasuk di uang masuk |
| Jam paling ramai | Lima jam yang paling sering dipakai, semua lapangan digabung |

### Aturan

| Aturan | Sumber |
|---|---|
| Pemilik hanya melihat pesanan dan pembayaran, tidak bisa menerima, menolak, atau membatalkan | Dok. Faiz |
| Harga dan aturan hanya diubah pemilik, berlaku untuk pesanan baru | Usulan tim (UC-16), belum dikonfirmasi pemilik |
| Definisi uang masuk (tanggal diterima) | Asumsi kelompok, perlu disepakati dengan bagian EDM (Fizy) |

### Belum dibuat

- Kelola akun penjaga dari aplikasi. Akun dibuat lewat seeder sampai ada keputusan.
- Rekap setoran kas per shift. Saat ini yang ada adalah rekap per penjaga per periode.
