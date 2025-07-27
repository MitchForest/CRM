<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends BaseModel
{
    protected $table = 'contacts';
    
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
        'description',
        'lead_source',
        'lifetime_value',
        'engagement_score',
        'last_activity_date'
    ];
    
    protected $casts = [
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'last_activity_date' => 'datetime',
        'lifetime_value' => 'float',
        'engagement_score' => 'integer',
        'deleted' => 'integer'
    ];
    
    protected $appends = ['full_name'];
    
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
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
        return $this->hasMany(\App\Models\SupportCase::class, 'contact_id');
    }
    
    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunities_contacts', 'contact_id', 'opportunity_id')
            ->withPivot(['deleted', 'date_modified'])
            ->wherePivot('deleted', 0);
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