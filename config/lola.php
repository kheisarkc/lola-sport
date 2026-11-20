<?php

/*
 * Aturan operasional yang jarang berubah. Aturan yang boleh diubah pemilik
 * (DP dan lama penahanan slot) disimpan di tabel settings, lihat UC-16.
 * Sumber tiap angka dicatat di docs/PROJECT.md.
 */
return [
    // KNF-06: buka 07.00 sampai 01.00. Jam tutup lebih kecil dari jam buka
    // berarti tutupnya di tanggal kalender berikutnya.
    'open_hour' => 7,
    'close_hour' => 1,

    // KF-26 (asumsi kelompok): reservasi paling jauh 30 hari ke depan.
    'max_days_ahead' => 30,

    // UC-12 (asumsi kelompok): pelanggan boleh ditandai hadir mulai
    // 60 menit sebelum jam main.
    'check_in_early_minutes' => 60,

    // Nomor WhatsApp Lola Sport untuk tombol "Tanya lewat WhatsApp".
    // Ini nomor contoh. Nomor asli baru dipasang setelah pemilik mengizinkan.
    'whatsapp' => env('LOLA_WHATSAPP', '6281200000000'),

    // Foto venue asli (public/images). Lapangan dicocokkan lewat nama;
    // lapangan baru tanpa foto sendiri memakai foto umum cabangnya.
    'court_photos' => [
        'Futsal Sintetis' => 'images/futsal-sintetis.webp',
        'Futsal Vinil' => 'images/futsal-vinil.webp',
        'Badminton 1' => 'images/badminton-1.webp',
        'Badminton 2' => 'images/badminton-2.webp',
    ],
    'sport_photos' => [
        'futsal' => 'images/galeri-futsal-sintetis.webp',
        'badminton' => 'images/galeri-badminton.webp',
    ],
];
