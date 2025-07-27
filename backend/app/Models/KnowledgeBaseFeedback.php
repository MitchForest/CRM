<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseFeedback extends BaseModel
{
    protected $table = 'knowledge_base_feedback';
    
    protected $fillable = [
        'article_id',
        'user_id',
        'session_id',
        'is_helpful',
        'feedback',
        'date_entered'
    ];
    
    protected $casts = [
        'is_helpful' => 'integer',
        'date_entered' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'article_id');
    }
}