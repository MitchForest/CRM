<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class ActivityTrackingPageView extends Model
{
    protected $table = 'activity_tracking_page_views';
    
    protected $fillable = [
        'session_id',
        'visitor_id',
        'page_url',
        'page_title',
        'referrer',
        'time_on_page',
        'exit_page',
        'date_entered'
    ];
    
    protected $casts = [
        'time_on_page' => 'integer',
        'exit_page' => 'integer',
        'date_entered' => 'datetime'
    ];
    
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Uuid::uuid4()->toString();
            }
            if (empty($model->date_entered)) {
                $model->date_entered = new \DateTime();
            }
        });
    }
    
    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivityTrackingSession::class, 'session_id', 'session_id');
    }
    
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(ActivityTrackingVisitor::class, 'visitor_id', 'visitor_id');
    }
}