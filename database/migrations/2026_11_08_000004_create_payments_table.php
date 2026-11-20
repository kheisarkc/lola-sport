<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->integer('amount');
            $table->string('method'); // qris | cash
            $table->string('status'); // pending | approved | rejected
            $table->string('proof_path')->nullable(); // file privat, bukan folder public
            $table->foreignId('recorded_by')->nullable()->constrained('users'); // pegawai pencatat tunai
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            // Omzet dihitung dari tanggal ini (uang diterima saat diverifikasi).
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('rejection_reason', 200)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'reviewed_at']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_positive_amount CHECK (amount > 0)');
        // Satu reservasi hanya boleh punya satu bukti yang menunggu verifikasi.
        DB::statement("CREATE UNIQUE INDEX payments_one_pending ON payments (booking_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
