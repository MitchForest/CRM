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
        'lead_id',
        'contact_id',
        'started_at',
        'ended_at',
        'duration',
        'page_views',
        'events_count',
        'form_submissions',
        'utm_source',
        'utm_medium',
        'utm_campaign'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
        'page_views' => 'integer',
        'events_count' => 'integer',
        'form_submissions' => 'integer'
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