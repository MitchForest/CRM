<?php

namespace App\Services\CRM;

use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseFeedback;
use App\Services\AI\OpenAIService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class KnowledgeBaseService
{
    public function __construct(
        private OpenAIService $openAI
    ) {}
    
    /**
     * Create a new article
     */
    public function createArticle(array $data): KnowledgeBaseArticle
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
            
            // Ensure unique slug
            $counter = 1;
            $baseSlug = $data['slug'];
            while (KnowledgeBaseArticle::where('slug', $data['slug'])->exists()) {
                $data['slug'] = $baseSlug . '-' . $counter;
                $counter++;
            }
        }
        
        // Generate excerpt if not provided
        if (empty($data['excerpt']) && !empty($data['content'])) {
            $data['excerpt'] = Str::limit(strip_tags($data['content']), 160);
        }
        
        // Generate embeddings for semantic search
        if (!empty($data['content'])) {
            $data['ai_embeddings'] = $this->generateEmbeddings($data);
        }
        
        $article = KnowledgeBaseArticle::create($data);
        
        return $article;
    }
    
    /**
     * Update an article
     */
    public function updateArticle(string $id, array $data): KnowledgeBaseArticle
    {
        $article = KnowledgeBaseArticle::findOrFail($id);
        
        // Regenerate embeddings if content changed
        if (isset($data['content']) && $data['content'] !== $article->content) {
            $data['ai_embeddings'] = $this->generateEmbeddings($data);
        }
        
        // Update excerpt if content changed
        if (isset($data['content']) && empty($data['excerpt'])) {
            $data['excerpt'] = Str::limit(strip_tags($data['content']), 160);
        }
        
        $article->update($data);
        
        return $article;
    }
    
    /**
     * Search articles
     */
    public function searchArticles(string $query, array $filters = []): Collection
    {
        $articles = KnowledgeBaseArticle::published();
        
        // Text search
        if ($query) {
            $articles->search($query);
        }
        
        // Category filter
        if (!empty($filters['category'])) {
            $articles->byCategory($filters['category']);
        }
        
        // Tag filter
        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $articles->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }
        
        // Sort by relevance or date
        if ($query) {
            // Simple relevance: prioritize title matches
            $articles->orderByRaw("CASE WHEN title LIKE ? THEN 1 ELSE 2 END", ["%{$query}%"])
                    ->orderBy('views', 'desc');
        } else {
            $articles->orderBy('created_at', 'desc');
        }
        
        return $articles->limit(20)->get();
    }
    
    /**
     * Semantic search using embeddings
     */
    public function semanticSearch(string $query, int $limit = 5): Collection
    {
        try {
            // Generate embedding for query
            $queryEmbedding = $this->openAI->embed($query);
            
            // Find similar articles
            // In production, use vector database for efficiency
            $articles = KnowledgeBaseArticle::published()->get();
            
            $scored = $articles->map(function ($article) use ($queryEmbedding) {
                $embedding = $article->ai_embeddings['embedding'] ?? null;
                if (!$embedding) {
                    return null;
                }
                
                // Calculate cosine similarity
                $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);
                
                return [
                    'article' => $article,
                    'score' => $similarity
                ];
            })->filter()->sortByDesc('score')->take($limit);
            
            return $scored->pluck('article');
            
        } catch (\Exception $e) {
            // Fallback to regular search
            return $this->searchArticles($query);
        }
    }
    
    /**
     * Get article by slug
     */
    public function getBySlug(string $slug): ?KnowledgeBaseArticle
    {
        $article = KnowledgeBaseArticle::where('slug', $slug)
            ->published()
            ->first();
        
        if ($article) {
            // Increment view count
            $article->incrementViews();
        }
        
        return $article;
    }
    
    /**
     * Get related articles
     */
    public function getRelatedArticles(KnowledgeBaseArticle $article, int $limit = 5): Collection
    {
        // Find articles with similar tags
        $relatedByTags = KnowledgeBaseArticle::published()
            ->where('id', '!=', $article->id)
            ->where(function ($q) use ($article) {
                foreach ($article->tags ?? [] as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            })
            ->limit($limit)
            ->get();
        
        if ($relatedByTags->count() >= $limit) {
            return $relatedByTags;
        }
        
        // Fill with articles from same category
        $needed = $limit - $relatedByTags->count();
        $relatedByCategory = KnowledgeBaseArticle::published()
            ->where('id', '!=', $article->id)
            ->whereNotIn('id', $relatedByTags->pluck('id'))
            ->byCategory($article->category)
            ->limit($needed)
            ->get();
        
        return $relatedByTags->concat($relatedByCategory);
    }
    
    /**
     * Submit feedback for an article
     */
    public function submitFeedback(string $articleId, array $data): KnowledgeBaseFeedback
    {
        $article = KnowledgeBaseArticle::findOrFail($articleId);
        
        // Create feedback
        $feedback = KnowledgeBaseFeedback::create([
            'article_id' => $articleId,
            'visitor_id' => $data['visitor_id'] ?? null,
            'helpful' => $data['helpful'],
            'feedback_text' => $data['feedback_text'] ?? null,
            'created_at' => new \DateTime()
        ]);
        
        // Update article counters
        if ($data['helpful']) {
            $article->increment('helpful_count');
        } else {
            $article->increment('not_helpful_count');
        }
        
        return $feedback;
    }
    
    /**
     * Get article categories
     */
    public function getCategories(): array
    {
        return KnowledgeBaseArticle::published()
            ->distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values()
            ->toArray();
    }
    
    /**
     * Get popular articles
     */
    public function getPopularArticles(int $limit = 10): Collection
    {
        return KnowledgeBaseArticle::published()
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get recent articles
     */
    public function getRecentArticles(int $limit = 10): Collection
    {
        return KnowledgeBaseArticle::published()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Generate AI summary for article
     */
    public function generateSummary(string $articleId): string
    {
        $article = KnowledgeBaseArticle::findOrFail($articleId);
        
        try {
            $summary = $this->openAI->summarize($article->content, 50);
            
            // Update article excerpt
            $article->update(['excerpt' => $summary]);
            
            return $summary;
        } catch (\Exception $e) {
            return $article->excerpt ?? Str::limit(strip_tags($article->content), 160);
        }
    }
    
    /**
     * Generate suggested articles for chatbot
     */
    public function getSuggestedArticles(string $query, int $limit = 3): array
    {
        // Use semantic search for better results
        $articles = $this->semanticSearch($query, $limit);
        
        if ($articles->isEmpty()) {
            // Fallback to keyword search
            $articles = $this->searchArticles($query)->take($limit);
        }
        
        return $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'url' => "/kb/{$article->slug}",
                'category' => $article->category
            ];
        })->toArray();
    }
    
    /**
     * Bulk import articles
     */
    public function bulkImport(array $articles): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($articles as $articleData) {
            try {
                $this->createArticle($articleData);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'title' => $articleData['title'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Generate embeddings for article
     */
    private function generateEmbeddings(array $data): array
    {
        try {
            // Combine title and content for embedding
            $text = $data['title'] . "\n\n" . strip_tags($data['content'] ?? '');
            
            $embedding = $this->openAI->embed($text);
            
            return [
                'embedding' => $embedding,
                'model' => 'text-embedding-ada-002',
                'generated_at' => new \DateTime()
            ];
        } catch (\Exception $e) {
            error_log("WARNING: Failed to generate embeddings - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0;
        }
        
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        
        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        
        $normA = sqrt($normA);
        $normB = sqrt($normB);
        
        if ($normA == 0 || $normB == 0) {
            return 0;
        }
        
        return $dotProduct / ($normA * $normB);
    }
    
    /**
     * Get article analytics
     */
    public function getArticleAnalytics(string $articleId): array
    {
        $article = KnowledgeBaseArticle::findOrFail($articleId);
        
        return [
            'views' => $article->views,
            'helpfulness_rate' => $article->helpfulness_rate,
            'helpful_count' => $article->helpful_count,
            'not_helpful_count' => $article->not_helpful_count,
            'feedback' => $article->feedback()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
            'view_trend' => $this->getViewTrend($article)
        ];
    }
    
    /**
     * Get view trend (mock data for now)
     */
    private function getViewTrend(KnowledgeBaseArticle $article): array
    {
        // In production, track daily views
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $trend[] = [
                'date' => (new \DateTime())->modify("-$i days")->format('Y-m-d'),
                'views' => rand(10, 100)
            ];
        }
        return $trend;
    }
}