<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Account extends BaseModel
{
    protected $table = 'accounts';
    
    protected $fillable = [
        'name',
        'date_entered',
        'date_modified',
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'deleted',
        'account_type',
        'industry',
        'annual_revenue',
        'phone_office',
        'website',
        'employees',
        'billing_address_street',
        'billing_address_city',
        'billing_address_state',
        'billing_address_postalcode',
        'billing_address_country',
        'description'
    ];
    
    protected $casts = [
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'deleted' => 'integer'
    ];
    
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
    
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'account_id');
    }
    
    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'accounts_opportunities', 'account_id', 'opportunity_id')
            ->withTimestamps('date_entered', 'date_modified');
    }
    
    public function cases(): HasMany
    {
        return $this->hasMany(\App\Models\SupportCase::class, 'account_id');
    }
}