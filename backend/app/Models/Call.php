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
        'date_end',
        'parent_type',
        'parent_id',
        'status',
        'direction',
        'description',
        'reminder_time'
    ];
    
    protected $casts = [
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'duration_hours' => 'integer',
        'duration_minutes' => 'integer',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime'
    ];
    
    protected $appends = ['duration_total_minutes'];
    
    public function getDurationTotalMinutesAttribute(): int
    {
        return ($this->duration_hours * 60) + $this->duration_minutes;
    }
    
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
                return $this->belongsTo(Case::class, 'parent_id');
            default:
                return null;
        }
    }
}