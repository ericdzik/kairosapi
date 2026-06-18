<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'action', 'entite', 'entite_id',
        'ancienne_valeur', 'nouvelle_valeur', 'ip', 'device_id',
    ];

    protected $casts = [
        'ancienne_valeur' => 'array',
        'nouvelle_valeur' => 'array',
        'created_at'      => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
