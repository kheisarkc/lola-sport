<?php

use Carbon\CarbonInterface;

/*
 * Fungsi kecil untuk tampilan Blade.
 */

if (! function_exists('rupiah')) {
    function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}

if (! function_exists('persen')) {
    /** 12,5% atau 12% (tanpa ,0). */
    function persen(float $value): string
    {
        return str_replace(',0', '', number_format($value, 1, ',', '.')).'%';
    }
}

if (! function_exists('jam')) {
    function jam(CarbonInterface $time): string
    {
        return $time->format('H.i');
    }
}

if (! function_exists('tanggal')) {
    function tanggal(CarbonInterface $date): string
    {
        return $date->locale('id')->translatedFormat('l, j F Y');
    }
}

if (! function_exists('waktu')) {
    function waktu(CarbonInterface $time): string
    {
        return $time->locale('id')->translatedFormat('j M Y, H.i').' WIB';
    }
}
