<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('sport'); // futsal | badminton
            $table->integer('hourly_rate'); // rupiah bulat (KNF-05)
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE courts ADD CONSTRAINT courts_positive_rate CHECK (hourly_rate > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
