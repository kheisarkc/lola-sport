<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique(); // kode booking untuk pelanggan (KF-04)
            $table->foreignId('court_id')->constrained();
            $table->string('source'); // website | staff | block
            $table->string('customer_name', 80);
            $table->string('customer_phone', 20)->nullable(); // kosong untuk slot yang ditutup
            // Tanggal operasional. Main 00.00-01.00 tetap ikut tanggal sebelumnya.
            $table->date('play_date');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            // Harga disalin saat pesan, jadi perubahan harga tidak mengubah tagihan lama.
            $table->integer('hourly_rate');
            $table->integer('total');
            $table->integer('minimum_dp');
            $table->string('status');
            $table->timestampTz('expires_at')->nullable(); // batas unggah bukti DP
            $table->timestampTz('checked_in_at')->nullable();
            $table->string('note', 200)->nullable(); // alasan tutup slot atau pembatalan
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->index(['play_date', 'court_id']);
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_valid_amounts CHECK (ends_at > starts_at AND total >= 0 AND minimum_dp BETWEEN 0 AND total)');

        // KF-07: lapangan dan jam yang sama tidak bisa dipakai dua reservasi aktif.
        // Aturan ini dijaga database, jadi tetap aman walaupun dua permintaan datang bersamaan.
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap EXCLUDE USING gist (
            court_id WITH =,
            tstzrange(starts_at, ends_at, '[)') WITH &&
        ) WHERE (status IN ('pending', 'review', 'confirmed', 'checked_in', 'no_show'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
