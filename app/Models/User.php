<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Akun pegawai atau pemilik (UC-05).
 *
 * @property UserRole $role
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    /**
     * Halaman pertama setelah login.
     */
    public function homeUrl(): string
    {
        return $this->isOwner() ? route('owner.revenue') : route('staff.schedule');
    }
}
