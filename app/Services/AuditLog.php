<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\User;

class AuditLog
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function record(?Booking $booking, ?User $actor, string $action, array $details = []): void
    {
        AuditEvent::create([
            'booking_id' => $booking?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
