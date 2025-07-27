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
        'date_entered',
        'date_modified',
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'deleted',
        'opportunity_type',
        'account_id',
        'lead_source',
        'amount',
        'amount_usdollar',
        'currency_id',
        'date_closed',
        'next_step',
        'sales_stage',
        'probability',
        'description',
        'ai_close_probability',
        'ai_risk_factors',
        'ai_recommendations'
    ];
    
    protected $casts = [
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'deleted' => 'integer',
        'amount' => 'float',
        'amount_usdollar' => 'float',
        'date_closed' => 'date',
        'probability' => 'float',
        'ai_close_probability' => 'float',
        'ai_risk_factors' => 'json',
        'ai_recommendations' => 'json'
    ];
    
    
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
            ->withPivot(['deleted', 'date_modified'])
            ->wherePivot('deleted', 0);
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