<?php
/**
 * Knowledge Base Controller - Articles with AI-powered search
 * Phase 3 Implementation
 */

namespace Api\Controllers;

use Api\Controllers\BaseController;
use Api\Request;
use Api\Response;
use Exception;
use SuiteCRM\Custom\Services\OpenAIService;

class KnowledgeBaseController extends BaseController
{
    private $openAIService;
    private $db;
    private $config;
    
    public function __construct()
    {
        // parent::__construct(); // BaseController has no constructor
        
        // Load configuration
        $this->config = require(__DIR__ . '/../../config/ai_config.php');
        
        // Initialize OpenAI service
        require_once __DIR__ . '/../../services/OpenAIService.php';
        $this->openAIService = new OpenAIService();
        
        global $db;
        $this->db = $db;
    }
    
    /**
     * Get all articles
     * GET /api/v8/knowledge-base/articles
     */
    public function getArticles(Request $request, Response $response)
    {
        try {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $limit = min((int)($params['limit'] ?? 20), 100);
            $offset = ($page - 1) * $limit;
            $category = $params['category'] ?? null;
            $isPublished = $params['is_published'] ?? null;
            $isFeatured = $params['is_featured'] ?? null;
            
            // Build query
            $where = "WHERE deleted = 0";
            $queryParams = [];
            
            if ($category !== null) {
                $where .= " AND category = ?";
                $queryParams[] = $category;
            }
            
            if ($isPublished !== null) {
                $where .= " AND is_published = ?";
                $queryParams[] = (int)$isPublished;
            }
            
            if ($isFeatured !== null) {
                $where .= " AND is_featured = ?";
                $queryParams[] = (int)$isFeatured;
            }
            
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM knowledge_base_articles $where";
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($queryParams);
            $totalCount = $stmt->fetchColumn();
            
            // Get articles
            $query = "SELECT id, title, slug, summary, category, tags, 
                            is_published, is_featured, view_count, 
                            helpful_count, not_helpful_count, 
                            author_id, date_published, date_modified
                     FROM knowledge_base_articles 
                     $where 
                     ORDER BY is_featured DESC, date_published DESC 
                     LIMIT ? OFFSET ?";
            
            $queryParams[] = $limit;
            $queryParams[] = $offset;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($queryParams);
            $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Parse JSON fields
            foreach ($articles as &$article) {
                $article['tags'] = json_decode($article['tags'], true) ?: [];
            }
            
            return $response->withJson([
                'data' => $articles,
                'meta' => [
                    'total' => $totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Get articles error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get articles',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single article
     * GET /api/v8/knowledge-base/articles/{id}
     */
    public function getArticle(Request $request, Response $response, array $args)
    {
        try {
            $articleId = $args['id'] ?? null;
            
            // Support both ID and slug
            $isSlug = !preg_match('/^[a-f0-9\-]{36}$/', $articleId);
            
            if ($isSlug) {
                $query = "SELECT * FROM knowledge_base_articles 
                         WHERE slug = ? AND deleted = 0";
            } else {
                $query = "SELECT * FROM knowledge_base_articles 
                         WHERE id = ? AND deleted = 0";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$articleId]);
            $article = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$article) {
                return $response->withJson([
                    'error' => 'Article not found'
                ], 404);
            }
            
            // Parse JSON fields
            $article['tags'] = json_decode($article['tags'], true) ?: [];
            
            // Increment view count for published articles
            if ($article['is_published']) {
                $updateQuery = "UPDATE knowledge_base_articles 
                               SET view_count = view_count + 1 
                               WHERE id = ?";
                
                $stmt = $this->db->prepare($updateQuery);
                $stmt->execute([$article['id']]);
            }
            
            // Get author info
            if ($article['author_id']) {
                $authorQuery = "SELECT id, first_name, last_name 
                               FROM users WHERE id = ?";
                
                $stmt = $this->db->prepare($authorQuery);
                $stmt->execute([$article['author_id']]);
                $article['author'] = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            // Remove embedding from public response
            unset($article['embedding']);
            
            return $response->withJson([
                'data' => $article
            ]);
            
        } catch (Exception $e) {
            error_log('Get article error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get article',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Search articles using AI semantic search
     * GET /api/v8/knowledge-base/search
     */
    public function searchArticles(Request $request, Response $response)
    {
        try {
            $params = $request->getQueryParams();
            $query = $params['q'] ?? '';
            $limit = min((int)($params['limit'] ?? 10), 50);
            
            if (empty($query)) {
                return $response->withJson([
                    'error' => 'Search query is required'
                ], 400);
            }
            
            // First, try full-text search for exact matches
            $sqlQuery = "SELECT id, title, slug, summary, category, view_count, 
                               MATCH(title, content, summary) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                        FROM knowledge_base_articles 
                        WHERE is_published = 1 AND deleted = 0
                        AND MATCH(title, content, summary) AGAINST(? IN NATURAL LANGUAGE MODE)
                        ORDER BY relevance DESC
                        LIMIT ?";
            
            $stmt = $this->db->prepare($sqlQuery);
            $stmt->execute([$query, $query, $limit]);
            $textResults = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // If we have good text matches, use them
            if (count($textResults) >= 3) {
                return $response->withJson([
                    'data' => [
                        'results' => $textResults,
                        'search_type' => 'full_text',
                        'query' => $query
                    ]
                ]);
            }
            
            // Otherwise, use semantic search
            // Get all published articles with embeddings
            $embeddingQuery = "SELECT id, title, slug, summary, category, embedding 
                              FROM knowledge_base_articles 
                              WHERE is_published = 1 AND deleted = 0 
                              AND embedding IS NOT NULL";
            
            $stmt = $this->db->prepare($embeddingQuery);
            $stmt->execute();
            $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($articles)) {
                return $response->withJson([
                    'data' => [
                        'results' => [],
                        'search_type' => 'semantic',
                        'query' => $query
                    ]
                ]);
            }
            
            // Prepare embeddings for semantic search
            $embeddings = [];
            foreach ($articles as $article) {
                $embeddings[$article['id']] = [
                    'embedding' => json_decode($article['embedding'], true),
                    'metadata' => [
                        'title' => $article['title'],
                        'slug' => $article['slug'],
                        'summary' => $article['summary'],
                        'category' => $article['category']
                    ]
                ];
            }
            
            // Perform semantic search
            $threshold = $this->config['knowledge_base']['similarity_threshold'];
            $searchResults = $this->openAIService->semanticSearch($query, $embeddings, $threshold);
            
            // Format results
            $results = [];
            foreach ($searchResults as $result) {
                $results[] = array_merge(
                    ['id' => $result['id'], 'similarity' => $result['similarity']],
                    $result['data']
                );
            }
            
            return $response->withJson([
                'data' => [
                    'results' => $results,
                    'search_type' => 'semantic',
                    'query' => $query
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Search articles error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to search articles',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new article
     * POST /api/v8/knowledge-base/articles
     */
    public function createArticle(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            
            // Validate required fields
            if (empty($body['title'])) {
                return $response->withJson([
                    'error' => 'Title is required'
                ], 400);
            }
            
            if (empty($body['content'])) {
                return $response->withJson([
                    'error' => 'Content is required'
                ], 400);
            }
            
            // Generate slug if not provided
            $slug = $body['slug'] ?? $this->generateSlug($body['title']);
            
            // Check if slug already exists
            $slugCheck = "SELECT id FROM knowledge_base_articles WHERE slug = ? AND deleted = 0";
            $stmt = $this->db->prepare($slugCheck);
            $stmt->execute([$slug]);
            
            if ($stmt->fetchColumn()) {
                return $response->withJson([
                    'error' => 'An article with this slug already exists'
                ], 400);
            }
            
            // Validate category
            $allowedCategories = $this->config['knowledge_base']['categories'];
            if (!empty($body['category']) && !in_array($body['category'], $allowedCategories)) {
                return $response->withJson([
                    'error' => 'Invalid category'
                ], 400);
            }
            
            $articleId = $this->generateUUID();
            
            // Generate embedding for semantic search
            $embedding = null;
            if ($body['is_published'] ?? false) {
                $textForEmbedding = $body['title'] . ' ' . 
                                   ($body['summary'] ?? '') . ' ' . 
                                   strip_tags($body['content']);
                
                $embedding = $this->openAIService->generateEmbedding($textForEmbedding);
            }
            
            $query = "INSERT INTO knowledge_base_articles 
                     (id, title, slug, content, summary, category, tags, 
                      is_published, is_featured, embedding, author_id, 
                      date_published, date_entered, date_modified)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $articleId,
                $body['title'],
                $slug,
                $body['content'],
                $body['summary'] ?? null,
                $body['category'] ?? 'general',
                json_encode($body['tags'] ?? []),
                (int)($body['is_published'] ?? false),
                (int)($body['is_featured'] ?? false),
                $embedding ? json_encode($embedding) : null,
                $this->getCurrentUserId(),
                $body['is_published'] ? date('Y-m-d H:i:s') : null
            ]);
            
            return $response->withJson([
                'data' => [
                    'id' => $articleId,
                    'slug' => $slug,
                    'created' => true
                ]
            ], 201);
            
        } catch (Exception $e) {
            error_log('Create article error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to create article',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update article
     * PUT /api/v8/knowledge-base/articles/{id}
     */
    public function updateArticle(Request $request, Response $response, array $args)
    {
        try {
            $articleId = $args['id'] ?? null;
            if (!$articleId) {
                return $response->withJson([
                    'error' => 'Article ID is required'
                ], 400);
            }
            
            $body = $request->getParsedBody();
            
            // Get existing article
            $query = "SELECT * FROM knowledge_base_articles WHERE id = ? AND deleted = 0";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$articleId]);
            $article = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$article) {
                return $response->withJson([
                    'error' => 'Article not found'
                ], 404);
            }
            
            // Build update query
            $updates = [];
            $params = [];
            
            if (isset($body['title'])) {
                $updates[] = "title = ?";
                $params[] = $body['title'];
            }
            
            if (isset($body['content'])) {
                $updates[] = "content = ?";
                $params[] = $body['content'];
                
                // Regenerate embedding if content changed and article is published
                if ($article['is_published'] || ($body['is_published'] ?? false)) {
                    $needsEmbedding = true;
                }
            }
            
            if (isset($body['summary'])) {
                $updates[] = "summary = ?";
                $params[] = $body['summary'];
            }
            
            if (isset($body['category'])) {
                $allowedCategories = $this->config['knowledge_base']['categories'];
                if (!in_array($body['category'], $allowedCategories)) {
                    return $response->withJson([
                        'error' => 'Invalid category'
                    ], 400);
                }
                $updates[] = "category = ?";
                $params[] = $body['category'];
            }
            
            if (isset($body['tags'])) {
                $updates[] = "tags = ?";
                $params[] = json_encode($body['tags']);
            }
            
            if (isset($body['is_published'])) {
                $updates[] = "is_published = ?";
                $params[] = (int)$body['is_published'];
                
                // Set publish date if being published for first time
                if ($body['is_published'] && !$article['is_published']) {
                    $updates[] = "date_published = NOW()";
                    $needsEmbedding = true;
                }
            }
            
            if (isset($body['is_featured'])) {
                $updates[] = "is_featured = ?";
                $params[] = (int)$body['is_featured'];
            }
            
            // Regenerate embedding if needed
            if (isset($needsEmbedding) && $needsEmbedding) {
                $title = $body['title'] ?? $article['title'];
                $content = $body['content'] ?? $article['content'];
                $summary = $body['summary'] ?? $article['summary'];
                
                $textForEmbedding = $title . ' ' . $summary . ' ' . strip_tags($content);
                $embedding = $this->openAIService->generateEmbedding($textForEmbedding);
                
                $updates[] = "embedding = ?";
                $params[] = json_encode($embedding);
            }
            
            if (empty($updates)) {
                return $response->withJson([
                    'error' => 'No fields to update'
                ], 400);
            }
            
            $updates[] = "date_modified = NOW()";
            $updates[] = "modified_user_id = ?";
            $params[] = $this->getCurrentUserId();
            $params[] = $articleId;
            
            $query = "UPDATE knowledge_base_articles SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $response->withJson([
                'data' => [
                    'id' => $articleId,
                    'updated' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Update article error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to update article',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete article
     * DELETE /api/v8/knowledge-base/articles/{id}
     */
    public function deleteArticle(Request $request, Response $response, array $args)
    {
        try {
            $articleId = $args['id'] ?? null;
            if (!$articleId) {
                return $response->withJson([
                    'error' => 'Article ID is required'
                ], 400);
            }
            
            // Soft delete
            $query = "UPDATE knowledge_base_articles 
                     SET deleted = 1, date_modified = NOW() 
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$articleId]);
            
            if ($stmt->rowCount() === 0) {
                return $response->withJson([
                    'error' => 'Article not found'
                ], 404);
            }
            
            return $response->withJson([
                'data' => [
                    'id' => $articleId,
                    'deleted' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Delete article error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to delete article',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit article feedback
     * POST /api/v8/knowledge-base/articles/{id}/feedback
     */
    public function submitFeedback(Request $request, Response $response, array $args)
    {
        try {
            $articleId = $args['id'] ?? null;
            if (!$articleId) {
                return $response->withJson([
                    'error' => 'Article ID is required'
                ], 400);
            }
            
            $body = $request->getParsedBody();
            $isHelpful = $body['is_helpful'] ?? null;
            $feedbackText = $body['feedback'] ?? '';
            
            if ($isHelpful === null) {
                return $response->withJson([
                    'error' => 'Feedback rating is required'
                ], 400);
            }
            
            // Check if article exists
            $query = "SELECT id FROM knowledge_base_articles WHERE id = ? AND deleted = 0";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$articleId]);
            
            if (!$stmt->fetchColumn()) {
                return $response->withJson([
                    'error' => 'Article not found'
                ], 404);
            }
            
            // Save feedback
            $feedbackId = $this->generateUUID();
            $sessionId = session_id() ?: $body['session_id'] ?? '';
            
            $query = "INSERT INTO knowledge_base_feedback 
                     (id, article_id, user_id, session_id, is_helpful, feedback_text, date_created)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $feedbackId,
                $articleId,
                $this->getCurrentUserId() ?: null,
                $sessionId,
                (int)$isHelpful,
                $feedbackText
            ]);
            
            // Update article counters
            if ($isHelpful) {
                $updateQuery = "UPDATE knowledge_base_articles 
                               SET helpful_count = helpful_count + 1 
                               WHERE id = ?";
            } else {
                $updateQuery = "UPDATE knowledge_base_articles 
                               SET not_helpful_count = not_helpful_count + 1 
                               WHERE id = ?";
            }
            
            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute([$articleId]);
            
            return $response->withJson([
                'data' => [
                    'feedback_id' => $feedbackId,
                    'submitted' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Submit feedback error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to submit feedback',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get article categories
     * GET /api/v8/knowledge-base/categories
     */
    public function getCategories(Request $request, Response $response)
    {
        try {
            $categories = $this->config['knowledge_base']['categories'];
            
            // Get article count per category
            $query = "SELECT category, COUNT(*) as count 
                     FROM knowledge_base_articles 
                     WHERE is_published = 1 AND deleted = 0 
                     GROUP BY category";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $counts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $result = [];
            foreach ($categories as $category) {
                $result[] = [
                    'name' => $category,
                    'slug' => str_replace(' ', '-', strtolower($category)),
                    'article_count' => $counts[$category] ?? 0
                ];
            }
            
            return $response->withJson([
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            error_log('Get categories error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get categories',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Private helper methods
     */
    
    private function generateSlug($title)
    {
        // Convert to lowercase
        $slug = strtolower($title);
        
        // Replace spaces with hyphens
        $slug = preg_replace('/\s+/', '-', $slug);
        
        // Remove special characters
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        // Remove duplicate hyphens
        $slug = preg_replace('/\-+/', '-', $slug);
        
        // Trim hyphens from ends
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $baseSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    private function slugExists($slug)
    {
        $query = "SELECT id FROM knowledge_base_articles WHERE slug = ? AND deleted = 0";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$slug]);
        return (bool)$stmt->fetchColumn();
    }
    
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function getCurrentUserId()
    {
        // TODO: Get from JWT token
        return '1'; // Admin user for now
    }
    
    /**
     * Mark an article as helpful
     * POST /api/kb/articles/{id}/helpful
     */
    public function markHelpful(Request $request, Response $response)
    {
        try {
            $articleId = $request->getParam('id');
            $data = $request->getData();
            $helpful = $data['helpful'] ?? true;
            $feedback = $data['feedback'] ?? '';
            
            if (!$articleId) {
                return Response::error('Article ID is required');
            }
            
            // Verify article exists
            $query = "SELECT id FROM aok_knowledgebase WHERE id = ? AND deleted = 0";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$articleId]);
            
            if (!$stmt->fetchColumn()) {
                return Response::error('Article not found', 404);
            }
            
            // Store feedback
            $feedbackId = $this->generateUUID();
            $userId = $this->getCurrentUserId() ?: null;
            $visitorId = $_COOKIE['visitor_id'] ?? $this->generateUUID();
            
            $query = "INSERT INTO knowledge_base_feedback 
                     (id, article_id, helpful, feedback, user_id, visitor_id, date_created)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $feedbackId,
                $articleId,
                $helpful ? 1 : 0,
                $feedback,
                $userId,
                $visitorId
            ]);
            
            // Update article statistics
            if ($helpful) {
                $query = "UPDATE aok_knowledgebase 
                         SET helpful_count = COALESCE(helpful_count, 0) + 1
                         WHERE id = ?";
            } else {
                $query = "UPDATE aok_knowledgebase 
                         SET not_helpful_count = COALESCE(not_helpful_count, 0) + 1
                         WHERE id = ?";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$articleId]);
            
            return Response::success([
                'message' => 'Thank you for your feedback',
                'feedbackId' => $feedbackId
            ]);
            
        } catch (\Exception $e) {
            return Response::error('Failed to submit feedback: ' . $e->getMessage());
        }
    }
}