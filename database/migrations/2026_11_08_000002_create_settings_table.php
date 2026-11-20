<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris pengaturan yang boleh diubah pemilik (KF-25).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->integer('dp_percent');
            $table->integer('hold_minutes');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE settings ADD CONSTRAINT settings_valid CHECK (dp_percent BETWEEN 1 AND 100 AND hold_minutes BETWEEN 5 AND 240)');
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
