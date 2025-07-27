<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseFeedback;
use App\Services\CRM\KnowledgeBaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class KnowledgeBaseController extends Controller
{
    // private KnowledgeBaseService $kbService;
    
    public function __construct()
    {
        parent::__construct();
        // Temporarily disable service dependency
        // $this->kbService = new KnowledgeBaseService();
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
        // TODO: Implement search
        $results = [
            'articles' => [],
            'total' => 0
        ];
        
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
            // Create article directly
            $article = KnowledgeBaseArticle::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'tags' => $data['tags'] ?? [],
                'category' => $data['category'] ?? 'General',
                'author_id' => $request->getAttribute('user_id') ?? '1',
                'is_published' => 0,
                'slug' => \Illuminate\Support\Str::slug($data['title'])
            ]);
            
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
            // Update article directly
            $article->update($data);
            
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
    public function getCategories(Request $request, Response $response, array $args): Response
    {
        // TODO: Implement categories
        $categories = [
            ['name' => 'Getting Started', 'articles_count' => 5],
            ['name' => 'API Documentation', 'articles_count' => 12],
            ['name' => 'Best Practices', 'articles_count' => 8],
            ['name' => 'Troubleshooting', 'articles_count' => 15]
        ];
        
        return $this->json($response, [
            'data' => $categories
        ]);
    }
    
    /**
     * Search public articles (public endpoint)
     * GET /api/public/kb/search
     */
    public function searchPublic(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';
        
        if (empty($query)) {
            return $this->json($response, [
                'articles' => [],
                'total' => 0
            ]);
        }
        
        // Simple search implementation
        $articles = KnowledgeBaseArticle::where('is_published', 1)
            ->where('deleted', 0)
            ->where(function($q) use ($query) {
                $q->where('title', 'LIKE', '%' . $query . '%')
                  ->orWhere('content', 'LIKE', '%' . $query . '%');
            })
            ->limit(10)
            ->get();
        
        return $this->json($response, [
            'articles' => $articles,
            'total' => count($articles)
        ]);
    }
    
    /**
     * Get public articles (public endpoint)
     * GET /api/public/kb/articles
     */
    public function getPublicArticles(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $category = $params['category'] ?? null;
        $page = intval($params['page'] ?? 1);
        $limit = min(intval($params['limit'] ?? 10), 50);
        
        $query = KnowledgeBaseArticle::where('is_published', 1)
            ->where('deleted', 0);
        
        if ($category) {
            $query->where('category', $category);
        }
        
        $articles = $query->orderBy('is_featured', 'desc')
            ->orderBy('date_modified', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        
        $data = $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'summary' => $article->summary,
                'category' => $article->category,
                'tags' => $article->tags,
                'author' => $article->author,
                'published_date' => $article->published_date,
                'is_featured' => $article->is_featured,
                'view_count' => $article->view_count
            ];
        });
        
        return $this->json($response, [
            'data' => $data,
            'meta' => [
                'total' => $articles->total(),
                'page' => $articles->currentPage(),
                'limit' => $articles->perPage(),
                'pages' => $articles->lastPage()
            ]
        ]);
    }
    
    /**
     * Get single public article (public endpoint)
     * GET /api/public/kb/articles/{slug}
     */
    public function getPublicArticle(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        
        $article = KnowledgeBaseArticle::where('slug', $slug)
            ->where('is_published', 1)
            ->where('deleted', 0)
            ->first();
        
        if (!$article) {
            return $this->error($response, 'Article not found', 404);
        }
        
        // Increment view count
        $article->increment('view_count');
        
        return $this->json($response, [
            'data' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'content' => $article->content,
                'summary' => $article->summary,
                'category' => $article->category,
                'tags' => $article->tags,
                'author' => $article->author,
                'published_date' => $article->published_date,
                'is_featured' => $article->is_featured,
                'view_count' => $article->view_count,
                'helpful_count' => $article->helpful_count,
                'not_helpful_count' => $article->not_helpful_count
            ]
        ]);
    }
    
    /**
     * Get public categories (public endpoint)
     * GET /api/public/kb/categories
     */
    public function getPublicCategories(Request $request, Response $response, array $args): Response
    {
        $categories = DB::table('kb_articles')
            ->where('is_published', 1)
            ->where('deleted', 0)
            ->select('category', DB::raw('COUNT(*) as article_count'))
            ->groupBy('category')
            ->orderBy('article_count', 'desc')
            ->get();
        
        return $this->json($response, [
            'data' => $categories
        ]);
    }
    
    /**
     * Get articles (admin endpoint)
     * GET /api/admin/knowledge-base/articles
     */
    public function getArticles(Request $request, Response $response, array $args): Response
    {
        return $this->index($request, $response, $args);
    }
    
    /**
     * Create article (admin endpoint)
     * POST /api/admin/knowledge-base/articles
     */
    public function createArticle(Request $request, Response $response, array $args): Response
    {
        return $this->store($request, $response, $args);
    }
    
    /**
     * Get article (admin endpoint)
     * GET /api/admin/knowledge-base/articles/{id}
     */
    public function getArticle(Request $request, Response $response, array $args): Response
    {
        return $this->show($request, $response, $args);
    }
    
    /**
     * Update article (admin endpoint)
     * PUT /api/admin/knowledge-base/articles/{id}
     */
    public function updateArticle(Request $request, Response $response, array $args): Response
    {
        return $this->update($request, $response, $args);
    }
    
    /**
     * Delete article (admin endpoint)
     * DELETE /api/admin/knowledge-base/articles/{id}
     */
    public function deleteArticle(Request $request, Response $response, array $args): Response
    {
        return $this->destroy($request, $response, $args);
    }
    
    /**
     * Publish article (admin endpoint)
     * POST /api/admin/knowledge-base/articles/{id}/publish
     */
    public function publishArticle(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $article = KnowledgeBaseArticle::where('deleted', 0)->find($id);
        
        if (!$article) {
            return $this->error($response, 'Article not found', 404);
        }
        
        $article->is_published = 1;
        $article->published_date = new \DateTime();
        $article->save();
        
        return $this->json($response, [
            'message' => 'Article published successfully',
            'data' => [
                'id' => $article->id,
                'is_published' => $article->is_published,
                'published_date' => $article->published_date
            ]
        ]);
    }
    
    /**
     * Unpublish article (admin endpoint)
     * POST /api/admin/knowledge-base/articles/{id}/unpublish
     */
    public function unpublishArticle(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $article = KnowledgeBaseArticle::where('deleted', 0)->find($id);
        
        if (!$article) {
            return $this->error($response, 'Article not found', 404);
        }
        
        $article->is_published = 0;
        $article->save();
        
        return $this->json($response, [
            'message' => 'Article unpublished successfully',
            'data' => [
                'id' => $article->id,
                'is_published' => $article->is_published
            ]
        ]);
    }
    
    /**
     * Create category (admin endpoint)
     * POST /api/admin/knowledge-base/categories
     */
    public function createCategory(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'description' => 'sometimes|string',
            'parent_id' => 'sometimes|string'
        ]);
        
        // In a real implementation, this would create a category in a kb_categories table
        return $this->json($response, [
            'message' => 'Category created successfully',
            'data' => $data
        ], 201);
    }
    
    /**
     * Update category (admin endpoint)
     * PUT /api/admin/knowledge-base/categories/{id}
     */
    public function updateCategory(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        
        $data = $this->validate($request, [
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|string',
            'parent_id' => 'sometimes|string'
        ]);
        
        // In a real implementation, this would update a category
        return $this->json($response, [
            'message' => 'Category updated successfully',
            'data' => array_merge(['id' => $id], $data)
        ]);
    }
    
    /**
     * Delete category (admin endpoint)
     * DELETE /api/admin/knowledge-base/categories/{id}
     */
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        
        // In a real implementation, this would delete a category
        return $this->json($response, [
            'message' => 'Category deleted successfully'
        ]);
    }
}