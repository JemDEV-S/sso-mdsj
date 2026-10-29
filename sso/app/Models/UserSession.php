<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['authenticated_at' => 'datetime', 'last_activity_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isValid(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture()
            && $this->last_activity_at->gt(now()->subMinutes(config('session.lifetime')));
    }
}
