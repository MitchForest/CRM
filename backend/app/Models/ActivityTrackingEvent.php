<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityTrackingEvent extends Model
{
    protected $table = 'activity_tracking_events';
    
    protected $fillable = [
        'visitor_id',
        'session_id',
        'event_type',
        'event_name',
        'event_category',
        'event_data',
        'occurred_at',
        'page_url'
    ];
    
    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime'
    ];
    
    protected $dates = [
        'occurred_at',
        'created_at',
        'updated_at'
    ];
    
    /**
     * Get the visitor that owns the event
     */
    public function visitor()
    {
        return $this->belongsTo(ActivityTrackingVisitor::class, 'visitor_id', 'visitor_id');
    }
    
    /**
     * Get the session that owns the event
     */
    public function session()
    {
        return $this->belongsTo(ActivityTrackingSession::class, 'session_id', 'session_id');
    }
} 