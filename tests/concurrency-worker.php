<?php

/*
 * Proses PHP terpisah untuk tes bersamaan. Dua proses ini menunggu sampai detik
 * yang sama, lalu menjalankan aksi yang sama ke database lola_demo_test.
 */

use App\Enums\BookingSource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || DB::selectOne('select current_database() as name')->name !== 'lola_demo_test') {
    throw new RuntimeException('Worker hanya boleh memakai lola_demo_test.');
}

$input = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
while (microtime(true) < $input['start']) {
    usleep(1000);
}

try {
    $staff = User::query()->findOrFail($input['staff']);
    $result = match ($input['action']) {
        'book' => app(ReservationService::class)->create($input['data'], BookingSource::Website),
        'approve' => app(PaymentService::class)->approve(Payment::query()->findOrFail($input['payment']), $staff),
        'cash' => app(PaymentService::class)->recordCash(Booking::query()->findOrFail($input['booking']), $input['amount'], $staff),
        'cancel' => app(ReservationService::class)->cancel(Booking::query()->findOrFail($input['booking']), $staff),
    };
    echo json_encode(['ok' => true, 'id' => $result->id]);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
