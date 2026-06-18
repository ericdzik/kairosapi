<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nom', 'prenom', 'telephone', 'password',
        'role', 'actif', 'first_login', 'device_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'actif'         => 'boolean',
        'first_login'   => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // Relations
    public function clients()
    {
        return $this->hasMany(Client::class, 'commercial_id');
    }

    public function cotisations()
    {
        return $this->hasMany(Cotisation::class, 'commercial_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // Helpers rôles
    public function isDirecteur(): bool    { return $this->role === 'directeur'; }
    public function isComptabilite(): bool { return $this->role === 'comptabilite'; }
    public function isSecretaire(): bool   { return $this->role === 'secretaire'; }
    public function isControleur(): bool   { return $this->role === 'controleur'; }
    public function isCommercial(): bool   { return $this->role === 'commercial'; }
}
