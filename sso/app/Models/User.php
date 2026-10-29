<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    protected $fillable = ['dni', 'nombres', 'apellidos', 'email', 'password', 'active', 'must_change_password', 'source', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::creating(fn (User $user) => $user->sub = (string) Str::uuid());
        static::updating(function (User $user) {
            if ($user->isDirty('sub')) {
                throw new \LogicException('El identificador sub es inmutable.');
            }
        });
    }

    public function getNameAttribute(): string
    {
        return $this->nombres.' '.$this->apellidos;
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
