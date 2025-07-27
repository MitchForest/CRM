<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends BaseModel
{
    protected $table = 'users';
    
    protected $fillable = [
        'user_name',
        'user_hash',
        'first_name',
        'last_name',
        'department',
        'title',
        'phone_work',
        'email1',
        'status',
        'is_admin',
        'deleted',
        'date_entered',
        'date_modified',
        'created_by'
    ];
    
    protected $hidden = [
        'user_hash',
        'deleted'
    ];
    
    protected $casts = [
        'is_admin' => 'integer',
        'deleted' => 'integer',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime'
    ];
    
    
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
        return $this->hasMany(\App\Models\SupportCase::class, 'assigned_user_id');
    }
    
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_user_id');
    }
}