<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseArticle extends BaseModel
{
    protected $table = 'knowledge_base_articles';
    
    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'category',
        'tags',
        'status',
        'views',
        'helpful_count',
        'not_helpful_count',
        'ai_embeddings',
        'created_by',
        'updated_by'
    ];
    
    protected $casts = [
        'tags' => 'array',
        'ai_embeddings' => 'array',
        'views' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public $timestamps = true;
    
    public function feedback(): HasMany
    {
        return $this->hasMany(KnowledgeBaseFeedback::class, 'article_id');
    }
    
    public function getHelpfulnessRateAttribute(): ?float
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        if ($total === 0) return null;
        
        return round(($this->helpful_count / $total) * 100, 1);
    }
    
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
    
    public function incrementViews(): void
    {
        $this->increment('views');
    }
    
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
    
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
    
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('content', 'like', "%{$search}%")
              ->orWhere('excerpt', 'like', "%{$search}%");
        });
    }
}