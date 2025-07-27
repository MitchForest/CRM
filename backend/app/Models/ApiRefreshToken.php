<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRefreshToken extends BaseModel
{
    protected $table = 'api_refresh_tokens';
    
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'created_at'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
    
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
    
    public function recordUsage(string $ipAddress = null, string $userAgent = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'ip_address' => $ipAddress ?? $this->ip_address,
            'user_agent' => $userAgent ?? $this->user_agent
        ]);
    }
    
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }
    
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}