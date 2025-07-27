<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends BaseModel
{
    protected $table = 'users';
    
    protected $fillable = [
        'user_name',                // Login username
        'user_hash',                // Password hash
        'first_name',
        'last_name',
        'email',                    // NOTE: Just 'email', NOT 'email1' for users
        'status',
        'is_admin',
        'default_team',
        'phone_work',
        'phone_mobile',
        'address_street',           // NOTE: No prefix for users
        'address_city',
        'address_state',
        'address_postalcode',
        'address_country',
        'title',
        'department'
    ];
    
    protected $hidden = [
        'user_hash',
        'deleted'
    ];
    
    protected $casts = [
        'is_admin' => 'boolean',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime'
    ];
    
    protected $appends = ['full_name'];
    
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
    
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_user_id');
    }
    
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'assigned_user_id');
    }
    
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'assigned_user_id');
    }
    
    public function cases(): HasMany
    {
        return $this->hasMany(\App\Models\Case::class, 'assigned_user_id');
    }
    
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_user_id');
    }
}