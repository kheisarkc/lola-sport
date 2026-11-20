# Kerja bareng lewat Git

## Alur singkat

```bash
git switch main
git pull --ff-only
git switch -c nama/fitur-singkat
```

Ubah kode, lalu periksa:

```bash
npm run check
```

Kalau mengubah tampilan atau alur halaman, jalankan juga `npm run test:e2e`.

```bash
git status
git add <file yang memang diubah>
git commit -m "Jelaskan perubahan dengan singkat"
git push -u origin nama/fitur-singkat
```

Buat Pull Request ke `main` di GitHub. Tulis apa yang berubah, perintah pemeriksaan yang dijalankan, dan hasilnya. Minta satu anggota lain meninjau sebelum digabung.

## Sebelum mengubah aturan bisnis

Harga, DP, batas waktu, pembatalan, dan cara hitung omzet adalah keputusan bisnis, bukan keputusan teknis.

1. Cek sumbernya di [docs/PROJECT.md](docs/PROJECT.md).
2. Kalau aturannya baru atau berubah, catat sumbernya (jawaban Lola Sport atau keputusan kelompok).
3. Ubah kode, tambahkan atau sesuaikan tes, lalu perbarui tabel di `docs/PROJECT.md`.

Jangan ubah tes hanya supaya lulus. Tes adalah aturan yang sudah disepakati.

## Checklist review

- Aturan bisnis tetap di `app/Services`, bukan di controller atau Blade.
- Uang tetap integer rupiah, waktu tetap WIB.
- Halaman pegawai/pemilik tetap di balik middleware peran.
- Tidak ada `.env`, password asli, data pelanggan sungguhan, atau file bukti yang ikut ter-commit.
- Tes yang relevan dijalankan dan hasilnya ditulis di PR.
