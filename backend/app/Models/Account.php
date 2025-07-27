<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Account extends BaseModel
{
    protected $table = 'accounts';
    
    protected $fillable = [
        'name',                     // Company name
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'account_type',
        'industry',
        'annual_revenue',
        'phone_office',             // NOT 'phone_work'
        'phone_alternate',
        'website',
        'email1',                   // NOT 'email'
        'employees',
        'billing_address_street',   // NOTE: 'billing_' prefix
        'billing_address_city',
        'billing_address_state',
        'billing_address_postalcode',
        'billing_address_country',
        'description',
        'rating',
        'ownership',
        'health_score',
        'renewal_date',
        'contract_value'
    ];
    
    protected $casts = [
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'renewal_date' => 'date',
        'contract_value' => 'decimal:2',
        'health_score' => 'integer',
        'deleted' => 'boolean'
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
        return $this->hasMany(Case::class, 'account_id');
    }
}