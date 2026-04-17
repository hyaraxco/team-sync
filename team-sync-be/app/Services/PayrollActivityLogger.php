<?php

namespace App\Services;

use App\Models\PayrollActivityLog;

class PayrollActivityLogger
{
    public function log(
        int $payrollId,
        string $eventType,
        string $title,
        ?string $description = null,
        ?int $actorId = null,
        array $metadata = []
    ): PayrollActivityLog {
        return PayrollActivityLog::create([
            'payroll_id' => $payrollId,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'metadata' => empty($metadata) ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }
}
