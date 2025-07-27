<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends BaseModel
{
    protected $table = 'leads';
    
    protected $fillable = [
        'date_entered',
        'date_modified',
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'deleted',
        'salutation',
        'first_name',
        'last_name',
        'title',
        'department',
        'phone_work',
        'phone_mobile',
        'email1',
        'primary_address_street',
        'primary_address_city',
        'primary_address_state',
        'primary_address_postalcode',
        'primary_address_country',
        'status',
        'status_description',
        'lead_source',
        'lead_source_description',
        'description',
        'account_name',
        'website',
        'ai_score',
        'ai_score_date',
        'ai_insights',
        'ai_next_best_action'
    ];
    
    protected $casts = [
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'ai_score_date' => 'datetime',
        'ai_insights' => 'json',
        'deleted' => 'integer',
        'ai_score' => 'integer'
    ];
    
    
    // Relationships
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
    
    public function scores(): HasMany
    {
        return $this->hasMany(LeadScore::class, 'lead_id');
    }
    
    public function sessions(): HasMany
    {
        return $this->hasMany(ActivityTrackingSession::class, 'lead_id');
    }
    
    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'lead_id');
    }
    
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'lead_id');
    }
    
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->where('parent_type', 'Leads');
    }
    
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'parent_id')->where('parent_type', 'Leads');
    }
    
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'parent_id')->where('parent_type', 'Leads');
    }
    
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'parent_id')->where('parent_type', 'Leads');
    }
    
    // Scopes
    public function scopeQualified($query)
    {
        return $query->where('status', 'qualified');
    }
    
    public function scopeHighScore($query, float $minScore = 0.7)
    {
        return $query->whereHas('scores', function ($q) use ($minScore) {
            $q->where('score', '>=', $minScore)
              ->where('scored_at', '>=', (new \DateTime())->modify('-30 days')->format('Y-m-d H:i:s'));
        });
    }
    
    public function scopeAssignedTo($query, string $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }
}
