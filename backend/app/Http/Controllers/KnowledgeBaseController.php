<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseFeedback;
use App\Services\CRM\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseController extends Controller
{
    private KnowledgeBaseService $kbService;
    
    public function __construct(KnowledgeBaseService $kbService)
    {
        $this->kbService = $kbService;
    }
    
    /**
     * Get all articles
     * GET /api/crm/knowledge-base/articles
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'category' => 'sometimes|string',
            'is_published' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'search' => 'sometimes|string'
        ]);
        
        $query = KnowledgeBaseArticle::query();
        
        // Apply filters
        if (isset($data['category'])) {
            $query->where('category', $data['category']);
        }
        
        if (isset($data['is_published'])) {
            $query->where('is_published', (bool)$data['is_published']);
        }
        
        if (isset($data['is_featured'])) {
            $query->where('is_featured', (bool)$data['is_featured']);
        }
        
        if (isset($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }
        
        // Apply sorting
        $query->orderBy('created_at', 'desc');
        
        // Paginate
        $page = intval($data['page'] ?? 1);
        $limit = intval($data['limit'] ?? 20);
        $articles = $query->paginate($limit, ['*'], 'page', $page);
        
        return $this->json($response, [
            'data' => $articles->items(),
            'meta' => [
                'total' => $articles->total(),
                'page' => $articles->currentPage(),
                'limit' => $articles->perPage(),
                'pages' => $articles->lastPage()
            ]
        ]);
    }
    
    /**
     * Get single article
     * GET /api/crm/knowledge-base/articles/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $idOrSlug = $args['id'] ?? $args['slug'] ?? '';
        
        // Support both ID and slug
        $article = KnowledgeBaseArticle::where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->with('author')
            ->first();
        
        if (!$article) {
            return $this->error($response, 'Article not found', 404);
        }
        
        // Increment view count
        $article->increment('view_count');
        
        return $this->json($response, [
            'data' => $article
        ]);
    }
    
    /**
     * Search articles using AI semantic search
     * GET /api/crm/knowledge-base/search
     */
    public function search(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'q' => 'required|string|min:2',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);
        
        $query = $data['q'];
        $limit = intval($data['limit'] ?? 10);
        
        // Use service for AI-powered search if available
        $results = $this->kbService->searchArticles($query, $limit);
        
        return $this->json($response, [
            'data' => [
                'results' => $results,
                'search_type' => 'hybrid', // text + AI
                'query' => $query
            ]
        ]);
    }
    
    /**
     * Create new article
     * POST /api/crm/knowledge-base/articles
     */
    public function store(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'summary' => 'sometimes|string',
            'category' => 'sometimes|string',
            'tags' => 'sometimes|array',
            'is_published' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean'
        ]);
        
        DB::connection()->beginTransaction();
        
        try {
            $article = $this->kbService->createArticle(
                $data,
                $request->getAttribute('user_id')
            );
            
            DB::connection()->commit();
            
            return $this->json($response, [
                'data' => $article,
                'message' => 'Article created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::connection()->rollBack();
            return $this->error($response, 'Failed to create article: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update article
     * PUT /api/crm/knowledge-base/articles/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $article = KnowledgeBaseArticle::find($id);
        
        if (!$article) {
            return $this->error($response, 'Article not found', 404);
        }
        
        $data = $this->validate($request, [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'summary' => 'sometimes|string',
            'category' => 'sometimes|string',
            'tags' => 'sometimes|array',
            'is_published' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean'
        ]);
        
        DB::connection()->beginTransaction();
        
        try {
            $article = $this->kbService->updateArticle($article, $data);
            
            DB::connection()->commit();
            
            return $this->json($response, [
                'data' => $article,
                'message' => 'Article updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::connection()->rollBack();
            return $this->error($response, 'Failed to update article: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete article
     * DELETE /api/crm/knowledge-base/articles/{id}
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $article = KnowledgeBaseArticle::find($id);
        
        if (!$article) {
            return $this->error($response, 'Article not found', 404);
        }
        
        $article->delete();
        
        return $this->json($response, [
            'message' => 'Article deleted successfully'
        ]);
    }
    
    /**
     * Submit article feedback
     * POST /api/crm/knowledge-base/articles/{id}/feedback
     */
    public function submitFeedback(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $article = KnowledgeBaseArticle::find($id);
        
        if (!$article) {
            return $this->error($response, 'Article not found', 404);
        }
        
        $data = $this->validate($request, [
            'is_helpful' => 'required|boolean',
            'feedback' => 'sometimes|string|max:1000'
        ]);
        
        DB::connection()->beginTransaction();
        
        try {
            // Create feedback record
            $feedback = KnowledgeBaseFeedback::create([
                'article_id' => $article->id,
                'user_id' => $request->getAttribute('user_id') ?? null,
                'is_helpful' => (bool)$data['is_helpful'],
                'feedback_text' => $data['feedback'] ?? null,
                'session_id' => uniqid('session_', true)
            ]);
            
            // Update article counts
            if ((bool)$data['is_helpful']) {
                $article->increment('helpful_count');
            } else {
                $article->increment('not_helpful_count');
            }
            
            DB::connection()->commit();
            
            return $this->json($response, [
                'data' => [
                    'feedback_id' => $feedback->id,
                    'submitted' => true
                ],
                'message' => 'Thank you for your feedback'
            ]);
            
        } catch (\Exception $e) {
            DB::connection()->rollBack();
            return $this->error($response, 'Failed to submit feedback: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get article categories
     * GET /api/crm/knowledge-base/categories
     */
    public function getCategories(Request $request): JsonResponse
    {
        $categories = $this->kbService->getCategories();
        
        return response()->json([
            'data' => $categories
        ]);
    }
}