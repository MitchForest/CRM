<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends BaseModel
{
    protected $table = 'contacts';
    
    protected $fillable = [
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'salutation',
        'first_name',
        'last_name',
        'title',
        'department',
        'phone_work',
        'phone_mobile',
        'email1',                   // NOT 'email' - consistent with Lead
        'primary_address_street',
        'primary_address_city',
        'primary_address_state',
        'primary_address_postalcode',
        'primary_address_country',
        'description',
        'lead_source',
        'account_id',
        'lifetime_value',
        'engagement_score',
        'last_activity_date'
    ];
    
    protected $casts = [
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'last_activity_date' => 'datetime',
        'lifetime_value' => 'decimal:2',
        'engagement_score' => 'integer',
        'deleted' => 'boolean'
    ];
    
    protected $appends = ['full_name', 'health_score'];
    
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
    
    public function getHealthScoreAttribute(): ?float
    {
        return $this->healthScores()->latest()->first()?->score;
    }
    
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
    
    public function cases(): HasMany
    {
        return $this->hasMany(\App\Models\Case::class, 'contact_id');
    }
    
    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunities_contacts', 'contact_id', 'opportunity_id')
            ->withTimestamps('date_entered', 'date_modified');
    }
    
    public function activities(): HasMany
    {
        return $this->hasMany(ActivityTrackingSession::class, 'contact_id');
    }
    
    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'contact_id');
    }
    
    public function healthScores(): HasMany
    {
        return $this->hasMany(CustomerHealthScore::class, 'contact_id');
    }
}