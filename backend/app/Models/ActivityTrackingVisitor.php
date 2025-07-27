<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityTrackingVisitor extends BaseModel
{
    protected $table = 'activity_tracking_visitors';
    
    protected $fillable = [
        'visitor_id',
        'user_agent',
        'ip_address',
        'country',
        'region',
        'city',
        'referrer_url',
        'first_page_url'
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public $timestamps = true;
    
    public function sessions(): HasMany
    {
        return $this->hasMany(ActivityTrackingSession::class, 'visitor_id', 'visitor_id');
    }
    
    public function pageViews(): HasMany
    {
        return $this->hasMany(ActivityTrackingPageView::class, 'visitor_id', 'visitor_id');
    }
}