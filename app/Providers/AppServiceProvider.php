<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // KNF-02: halaman pegawai dan pemilik dibatasi sesuai peran.
        Gate::define('staff', fn (User $user) => $user->isStaff());
        Gate::define('owner', fn (User $user) => $user->isOwner());

        // Mencegah tebak-tebakan password dan kode booking.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('lookup', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        $this->guardDatabaseCommands();
    }

    /**
     * Migrasi, seed, dan tes browser hanya boleh menyentuh database demo lokal,
     * supaya database lain tidak terhapus tanpa sengaja.
     */
    private function guardDatabaseCommands(): void
    {
        $command = $_SERVER['argv'][1] ?? '';
        $touchesSchema = $this->app->runningInConsole()
            && (str_starts_with($command, 'migrate') || $command === 'db:seed');

        if ($this->app->environment('e2e')) {
            $this->ensureDatabase('lola_demo_test');
        } elseif ($touchesSchema && ! $this->app->environment('production')) {
            $this->ensureDatabase($this->app->environment('testing') ? 'lola_demo_test' : 'lola_demo');
        }
    }

    private function ensureDatabase(string $expected): void
    {
        $config = config('database.connections.pgsql');
        $isExpected = config('database.default') === 'pgsql'
            && $config['database'] === $expected
            && $config['host'] === '127.0.0.1'
            && (string) $config['port'] === '55433'
            && DB::selectOne('select current_database() as name')->name === $expected;

        if (! $isExpected) {
            throw new RuntimeException("Perintah ini hanya boleh memakai database {$expected} di 127.0.0.1:55433.");
        }
    }
}
