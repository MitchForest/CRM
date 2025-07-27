<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class LeadScore extends Model
{
    protected $table = 'ai_lead_scoring_history';
    
    protected $fillable = [
        'lead_id',
        'score',
        'previous_score',
        'score_change',
        'factors',
        'insights',
        'recommendations',
        'date_scored'
    ];
    
    protected $casts = [
        'score' => 'integer',
        'previous_score' => 'integer',
        'score_change' => 'integer',
        'factors' => 'json',
        'insights' => 'json',
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
    
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
    
    public function getScorePercentageAttribute(): int
    {
        return (int) $this->score;
    }
    
    public function getScoreGradeAttribute(): string
    {
        $score = $this->score;
        
        if ($score >= 80) return 'A';
        if ($score >= 60) return 'B';
        if ($score >= 40) return 'C';
        if ($score >= 20) return 'D';
        return 'F';
    }
}