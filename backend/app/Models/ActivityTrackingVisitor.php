<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityTrackingVisitor extends BaseModel
{
    protected $table = 'activity_tracking_visitors';
    
    protected $fillable = [
        'visitor_id',
        'lead_id',
        'contact_id',
        'first_visit',
        'last_visit',
        'visit_count',
        'page_view_count',
        'total_time_spent',
        'source',
        'medium',
        'campaign',
        'referrer',
        'device_type',
        'browser',
        'os',
        'country',
        'city',
        'date_entered',
        'date_modified'
    ];
    
    protected $casts = [
        'first_visit' => 'datetime',
        'last_visit' => 'datetime',
        'visit_count' => 'integer',
        'page_view_count' => 'integer',
        'total_time_spent' => 'integer',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function sessions(): HasMany
    {
        return $this->hasMany(ActivityTrackingSession::class, 'visitor_id', 'visitor_id');
    }
    
    public function pageViews(): HasMany
    {
        return $this->hasMany(ActivityTrackingPageView::class, 'visitor_id', 'visitor_id');
    }
}