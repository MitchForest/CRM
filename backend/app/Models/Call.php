<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends BaseModel
{
    protected $table = 'calls';
    
    protected $fillable = [
        'name',                     // Call subject
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'duration_hours',
        'duration_minutes',
        'date_start',
        'parent_type',
        'parent_id',
        'status',
        'direction',
        'description',
        'date_entered',
        'date_modified',
        'deleted'
    ];
    
    protected $casts = [
        'date_start' => 'datetime',
        'duration_hours' => 'integer',
        'duration_minutes' => 'integer',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'deleted' => 'integer'
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