<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerHealthScore extends BaseModel
{
    protected $table = 'customer_health_scores';
    
    protected $fillable = [
        'contact_id',
        'account_id',
        'score',
        'factors',
        'trend',
        'risk_level',
        'calculated_at'
    ];
    
    protected $casts = [
        'score' => 'decimal:2',
        'factors' => 'array',
        'calculated_at' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
    
    public function getHealthStatusAttribute(): string
    {
        $score = $this->score * 100;
        
        if ($score >= 80) return 'Excellent';
        if ($score >= 60) return 'Good';
        if ($score >= 40) return 'Fair';
        if ($score >= 20) return 'At Risk';
        return 'Critical';
    }
    
    public function getHealthColorAttribute(): string
    {
        $score = $this->score * 100;
        
        if ($score >= 80) return 'green';
        if ($score >= 60) return 'blue';
        if ($score >= 40) return 'yellow';
        if ($score >= 20) return 'orange';
        return 'red';
    }
}