<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public $timestamps = false;

    protected $fillable = ['full_name', 'email', 'password_hash', 'role_id', 'is_active'];

    protected $hidden = ['password_hash'];

    // Laravel mặc định đọc cột "password" - ta override để dùng "password_hash"
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /** Helper kiểm tra quyền - dùng trong Middleware RBAC */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }
}
