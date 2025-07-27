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
        'summary',
        'category',
        'tags',
        'is_published',
        'is_featured',
        'view_count',
        'helpful_count',
        'not_helpful_count',
        'author_id',
        'date_published',
        'date_entered',
        'date_modified',
        'deleted',
        'embedding'
    ];
    
    protected $casts = [
        'tags' => 'json',
        'is_published' => 'integer',
        'is_featured' => 'integer',
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'date_published' => 'datetime',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'deleted' => 'integer',
        'embedding' => 'json'
    ];
    
    public $timestamps = false;
    
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
        return $this->is_published === 1;
    }
    
    public function incrementViews(): void
    {
        $this->increment('view_count');
    }
    
    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
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
              ->orWhere('summary', 'like', "%{$search}%");
        });
    }
}