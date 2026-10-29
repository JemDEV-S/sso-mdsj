<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Audit
{
    public static function record(string $event, ?int $userId = null, ?int $clientId = null): void
    {
        DB::table('audit_logs')->insert([
            'event' => $event, 'user_id' => $userId, 'client_id' => $clientId,
            'actor_id' => auth()->id(), 'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 1000), 'created_at' => now(),
        ]);
    }
}
