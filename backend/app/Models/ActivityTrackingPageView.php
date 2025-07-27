<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityTrackingPageView extends BaseModel
{
    protected $table = 'activity_tracking_page_views';
    
    protected $fillable = [
        'session_id',
        'visitor_id',
        'page_url',
        'page_title',
        'time_on_page',
        'bounce',
        'exit_page',
        'created_at'
    ];
    
    protected $casts = [
        'time_on_page' => 'integer',
        'bounce' => 'boolean',
        'exit_page' => 'boolean',
        'created_at' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivityTrackingSession::class, 'session_id', 'session_id');
    }
    
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(ActivityTrackingVisitor::class, 'visitor_id', 'visitor_id');
    }
}