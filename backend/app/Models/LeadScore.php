<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadScore extends BaseModel
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
    
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
    
    public function getScorePercentageAttribute(): int
    {
        return (int) round($this->score * 100);
    }
    
    public function getScoreGradeAttribute(): string
    {
        $score = $this->score * 100;
        
        if ($score >= 80) return 'A';
        if ($score >= 60) return 'B';
        if ($score >= 40) return 'C';
        if ($score >= 20) return 'D';
        return 'F';
    }
}