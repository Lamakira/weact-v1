<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminRole;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Concerns\HasRouteUuid;

class Admin extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, HasApiTokens, HasFactory, HasRouteUuid, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === AdminRole::SuperAdmin;
    }

    public function isEditor(): bool
    {
        return $this->role === AdminRole::Editor;
    }

    /**
     * Send the password reset notification using the admin-specific notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }

    /**
     * Get the articles authored by this admin.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}

