<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak perubahan penting: siapa melakukan apa dan kapan.
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained();
            $table->foreignId('actor_id')->nullable()->constrained('users'); // kosong = sistem atau pelanggan
            $table->string('action');
            $table->jsonb('details')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
