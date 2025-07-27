<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseFeedback extends BaseModel
{
    protected $table = 'knowledge_base_feedback';
    
    protected $fillable = [
        'article_id',
        'visitor_id',
        'helpful',
        'feedback_text',
        'created_at'
    ];
    
    protected $casts = [
        'helpful' => 'boolean',
        'created_at' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'article_id');
    }
}