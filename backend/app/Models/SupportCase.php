<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportCase extends BaseModel
{
    protected $table = 'cases';
    
    protected $fillable = [
        'date_entered',
        'date_modified',
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'deleted',
        'case_number',
        'name',
        'account_id',
        'contact_id',
        'status',
        'priority',
        'type',
        'description',
        'resolution'
    ];
    
    protected $casts = [
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'deleted' => 'integer',
        'case_number' => 'integer'
    ];
    
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
    
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
    
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->where('parent_type', 'Cases');
    }
    
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'parent_id')->where('parent_type', 'Cases');
    }
    
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'parent_id')->where('parent_type', 'Cases');
    }
    
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'parent_id')->where('parent_type', 'Cases');
    }
}