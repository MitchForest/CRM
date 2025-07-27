<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends BaseModel
{
    protected $table = 'opportunities';
    
    protected $fillable = [
        'name',
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'opportunity_type',
        'account_id',
        'amount',
        'amount_usdollar',          // USD equivalent
        'date_closed',
        'next_step',
        'sales_stage',
        'probability',
        'description',
        'lead_source',
        'campaign_id'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'amount_usdollar' => 'decimal:2',
        'probability' => 'integer',
        'date_closed' => 'date',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'deleted' => 'boolean'
    ];
    
    protected $appends = ['weighted_amount'];
    
    public function getWeightedAmountAttribute(): float
    {
        return ($this->amount ?? 0) * ($this->probability ?? 0) / 100;
    }
    
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
    
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'opportunities_contacts', 'opportunity_id', 'contact_id')
            ->withTimestamps('date_entered', 'date_modified');
    }
    
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->where('parent_type', 'Opportunities');
    }
    
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'parent_id')->where('parent_type', 'Opportunities');
    }
    
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'parent_id')->where('parent_type', 'Opportunities');
    }
    
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'parent_id')->where('parent_type', 'Opportunities');
    }
}