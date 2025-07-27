<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends BaseModel
{
    protected $table = 'tasks';
    
    protected $fillable = [
        'name',                     // Task subject
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'status',
        'date_due_flag',
        'date_due',
        'date_start_flag',
        'date_start',
        'parent_type',              // Module name
        'parent_id',                // Related record
        'contact_id',
        'priority',
        'description'
    ];
    
    protected $casts = [
        'date_due' => 'datetime',
        'date_start' => 'datetime',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime'
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
                return $this->belongsTo(Case::class, 'parent_id');
            default:
                return null;
        }
    }
}