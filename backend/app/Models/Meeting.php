<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends BaseModel
{
    protected $table = 'meetings';
    
    protected $fillable = [
        'date_entered',
        'date_modified',
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'deleted',
        'name',
        'location',
        'duration_hours',
        'duration_minutes',
        'date_start',
        'date_end',
        'status',
        'parent_type',
        'parent_id',
        'contact_id',
        'description'
    ];
    
    protected $casts = [
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'deleted' => 'integer',
        'duration_hours' => 'integer',
        'duration_minutes' => 'integer',
        'date_start' => 'datetime',
        'date_end' => 'datetime'
    ];
    
    
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
    
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
    
    public function parent()
    {
        switch ($this->parent_type) {
            case 'Leads':
                return $this->belongsTo(Lead::class, 'parent_id');
            case 'Contacts':
                return $this->belongsTo(Contact::class, 'parent_id');
            case 'Accounts':
                return $this->belongsTo(Account::class, 'parent_id');
            case 'Opportunities':
                return $this->belongsTo(Opportunity::class, 'parent_id');
            case 'Cases':
                return $this->belongsTo(\App\Models\SupportCase::class, 'parent_id');
            default:
                return null;
        }
    }
}