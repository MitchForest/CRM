<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class CustomerHealthScore extends Model
{
    protected $table = 'customer_health_scores';
    
    protected $fillable = [
        'account_id',
        'score',
        'previous_score',
        'score_change',
        'factors',
        'risk_level',
        'recommendations',
        'date_scored'
    ];
    
    protected $casts = [
        'score' => 'integer',
        'previous_score' => 'integer',
        'score_change' => 'integer',
        'factors' => 'json',
        'recommendations' => 'json',
        'date_scored' => 'datetime'
    ];
    
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Uuid::uuid4()->toString();
            }
        });
    }
    
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