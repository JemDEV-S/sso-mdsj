<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['client_secret_hash'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function redirectUris(): HasMany
    {
        return $this->hasMany(ClientRedirectUri::class);
    }
}
