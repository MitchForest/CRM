<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityTrackingSession extends BaseModel
{
    protected $table = 'activity_tracking_sessions';
    
    protected $fillable = [
        'session_id',
        'visitor_id',
        'ip_address',
        'user_agent',
        'start_time',
        'end_time',
        'duration',
        'page_count',
        'bounce',
        'date_entered'
    ];
    
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
        'page_count' => 'integer',
        'bounce' => 'integer',
        'date_entered' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(ActivityTrackingVisitor::class, 'visitor_id', 'visitor_id');
    }
    
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
    
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
    
    public function pageViews(): HasMany
    {
        return $this->hasMany(ActivityTrackingPageView::class, 'session_id', 'session_id');
    }
}