<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'license_key',
        'license_hash',
        'company_name',
        'contact_email',
        'issued_at',
        'expires_at',
        'is_active',
        'features',
        'max_users',
        'current_users',
        'activated_at',
        'last_validated_at',
        'signature',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'is_active' => 'boolean',
        'features' => 'array',
        'activated_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>=', now())
            ->whereDate('issued_at', '<=', now());
    }
}
