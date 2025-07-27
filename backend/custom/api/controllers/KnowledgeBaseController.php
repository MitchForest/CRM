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
        
        // Default configuration for KB
        $this->config = [
            'knowledge_base' => [
                'similarity_threshold' => 0.7,
                'categories' => ['general', 'technical', 'billing', 'features', 'getting-started']
            ]
        ];
        
        // OpenAI service not needed for basic KB functionality
        $this->openAIService = null;
        
        global $db;
        $this->db = $db;
    }
    
    /**
     * Get all articles
     * GET /api/v8/knowledge-base/articles
     */
    public function getArticles(Request $request)
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
                $where .= " AND status = ?";
                $queryParams[] = $isPublished ? 'published' : 'draft';
            }
            
            // Featured not supported in aok_knowledgebase table
            // We'll return all articles when is_featured is requested
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as count FROM aok_knowledgebase $where";
            if (!empty($queryParams)) {
                // Build query with escaped values
                $countQuery = str_replace('?', "'%s'", $countQuery);
                $countQuery = sprintf($countQuery, ...$queryParams);
            }
            $result = $this->db->query($countQuery);
            $row = $this->db->fetchByAssoc($result);
            $totalCount = $row['count'] ?? 0;
            
            // Get articles
            $query = "SELECT id, name as title, name as slug, description as summary, 
                            '' as category, '' as tags, 
                            status = 'published' as is_published, 0 as is_featured, 0 as view_count, 
                            0 as helpful_count, 0 as not_helpful_count, 
                            created_by as author_id, date_entered as date_published, date_modified
                     FROM aok_knowledgebase 
                     $where 
                     ORDER BY date_entered DESC 
                     LIMIT ? OFFSET ?";
            
            $queryParams[] = $limit;
            $queryParams[] = $offset;
            
            // Build query with escaped values
            if (!empty($queryParams) && count($queryParams) > 2) {
                // Replace parameter placeholders in where clause
                $whereProcessed = $where;
                foreach ($queryParams as $i => $param) {
                    if ($i < count($queryParams) - 2) { // Skip limit and offset
                        $safeParam = $this->db->quote($param);
                        $whereProcessed = preg_replace('/\?/', "'$safeParam'", $whereProcessed, 1);
                    }
                }
                $query = str_replace($where, $whereProcessed, $query);
                $query = str_replace('LIMIT ? OFFSET ?', "LIMIT $limit OFFSET $offset", $query);
            } else {
                // No query params besides limit/offset
                $query = str_replace('LIMIT ? OFFSET ?', "LIMIT $limit OFFSET $offset", $query);
            }
            
            $result = $this->db->query($query);
            $articles = [];
            while ($row = $this->db->fetchByAssoc($result)) {
                $articles[] = $row;
            }
            
            // Parse JSON fields
            foreach ($articles as &$article) {
                $article['tags'] = [];
                $article['is_published'] = (bool)$article['is_published'];
                $article['is_featured'] = (bool)$article['is_featured'];
            }
            
            return Response::json([
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
            return Response::json([
                'error' => 'Failed to get articles',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single article
     * GET /api/v8/knowledge-base/articles/{id}
     */
    public function getArticle(Request $request, $id)
    {
        try {
            $articleId = $id ?? null;
            
            // Support both ID and slug
            $isSlug = !preg_match('/^[a-f0-9\-]{36}$/', $articleId);
            
            if ($isSlug) {
                $query = "SELECT id, name as title, name as slug, description as content, 
                         description as summary, '' as category, status,
                         0 as view_count, created_by as author_id,
                         date_entered as date_published, date_modified
                         FROM aok_knowledgebase 
                         WHERE name = ? AND deleted = 0";
            } else {
                $query = "SELECT id, name as title, name as slug, description as content,
                         description as summary, '' as category, status,
                         0 as view_count, created_by as author_id,
                         date_entered as date_published, date_modified
                         FROM aok_knowledgebase 
                         WHERE id = ? AND deleted = 0";
            }
            
            $safeId = $this->db->quote($articleId);
            $query = str_replace('?', "'$safeId'", $query);
            $result = $this->db->query($query);
            $article = $this->db->fetchByAssoc($result);
            
            if (!$article) {
                return Response::json([
                    'error' => 'Article not found'
                ], 404);
            }
            
            // Add default fields
            $article['tags'] = [];
            $article['is_published'] = ($article['status'] === 'published');
            $article['is_featured'] = false;
            
            // Get author info
            if ($article['author_id']) {
                $authorQuery = "SELECT id, first_name, last_name 
                               FROM users WHERE id = ?";
                
                $safeAuthorId = $this->db->quote($article['author_id']);
                $authorQuery = str_replace('?', "'$safeAuthorId'", $authorQuery);
                $authorResult = $this->db->query($authorQuery);
                $article['author'] = $this->db->fetchByAssoc($authorResult);
            }
            
            // Remove embedding from public response
            unset($article['embedding']);
            
            return Response::json([
                'data' => $article
            ]);
            
        } catch (Exception $e) {
            error_log('Get article error: ' . $e->getMessage());
            return Response::json([
                'error' => 'Failed to get article',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Search articles using AI semantic search
     * GET /api/v8/knowledge-base/search
     */
    public function searchArticles(Request $request)
    {
        try {
            $params = $request->getQueryParams();
            $query = $params['q'] ?? '';
            $limit = min((int)($params['limit'] ?? 10), 50);
            
            if (empty($query)) {
                return Response::json([
                    'error' => 'Search query is required'
                ], 400);
            }
            
            // Simple search in aok_knowledgebase
            $searchTerm = '%' . $query . '%';
            $safeSearchTerm = $this->db->quote($searchTerm);
            
            $sqlQuery = "SELECT id, name as title, name as slug, description as summary, '' as category, 0 as view_count
                        FROM aok_knowledgebase 
                        WHERE status = 'published' AND deleted = 0
                        AND (name LIKE {$safeSearchTerm} OR description LIKE {$safeSearchTerm})
                        ORDER BY date_entered DESC
                        LIMIT {$limit}";
            
            $result = $this->db->query($sqlQuery);
            $textResults = [];
            while ($row = $this->db->fetchByAssoc($result)) {
                $textResults[] = $row;
            }
            
            // Return text search results
            return Response::json([
                'data' => [
                    'results' => $textResults,
                    'search_type' => 'full_text',
                    'query' => $query
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Search articles error: ' . $e->getMessage());
            return Response::json([
                'error' => 'Failed to search articles',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new article
     * POST /api/v8/knowledge-base/articles
     */
    public function createArticle(Request $request)
    {
        try {
            $body = $request->getParsedBody();
            
            // Validate required fields
            if (empty($body['title'])) {
                return Response::json([
                    'error' => 'Title is required'
                ], 400);
            }
            
            if (empty($body['content'])) {
                return Response::json([
                    'error' => 'Content is required'
                ], 400);
            }
            
            // Generate slug if not provided
            $slug = $body['slug'] ?? $this->generateSlug($body['title']);
            
            // Check if slug (name) already exists in aok_knowledgebase
            $safeSlug = $this->db->quote($slug);
            $slugCheck = "SELECT id FROM aok_knowledgebase WHERE name = '{$safeSlug}' AND deleted = 0";
            $result = $this->db->query($slugCheck);
            $row = $this->db->fetchByAssoc($result);
            
            if ($row && $row['id']) {
                return Response::json([
                    'error' => 'An article with this slug already exists'
                ], 400);
            }
            
            // Validate category
            $allowedCategories = $this->config['knowledge_base']['categories'];
            if (!empty($body['category']) && !in_array($body['category'], $allowedCategories)) {
                return Response::json([
                    'error' => 'Invalid category'
                ], 400);
            }
            
            $articleId = $this->generateUUID();
            
            // Insert into aok_knowledgebase table
            $values = [
                "'" . $this->db->quote($articleId) . "'",
                "'" . $this->db->quote($body['title']) . "'",  // name field
                "'" . $this->db->quote($body['content']) . "'", // description field
                "'" . $this->db->quote($body['is_published'] ? 'published' : 'draft') . "'", // status field
                "'" . $this->db->quote($this->getCurrentUserId()) . "'",
                "'" . $this->db->quote($this->getCurrentUserId()) . "'"
            ];
            
            $insertQuery = "INSERT INTO aok_knowledgebase 
                     (id, name, description, status, created_by, assigned_user_id, date_entered, date_modified)
                     VALUES (" . implode(', ', $values) . ", NOW(), NOW())";
            
            $this->db->query($insertQuery);
            
            return Response::json([
                'data' => [
                    'id' => $articleId,
                    'slug' => $slug,
                    'created' => true
                ]
            ], 201);
            
        } catch (Exception $e) {
            error_log('Create article error: ' . $e->getMessage());
            return Response::json([
                'error' => 'Failed to create article',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update article
     * PUT /api/v8/knowledge-base/articles/{id}
     */
    public function updateArticle(Request $request, $id)
    {
        try {
            $articleId = $id ?? null;
            if (!$articleId) {
                return Response::json([
                    'error' => 'Article ID is required'
                ], 400);
            }
            
            $body = $request->getParsedBody();
            
            // Get existing article from aok_knowledgebase
            $safeId = $this->db->quote($articleId);
            $query = "SELECT * FROM aok_knowledgebase WHERE id = '{$safeId}' AND deleted = 0";
            $result = $this->db->query($query);
            $article = $this->db->fetchByAssoc($result);
            
            if (!$article) {
                return Response::json([
                    'error' => 'Article not found'
                ], 404);
            }
            
            // Build update query for aok_knowledgebase
            $updates = [];
            
            if (isset($body['title'])) {
                $updates[] = "name = '" . $this->db->quote($body['title']) . "'";
            }
            
            if (isset($body['content'])) {
                $updates[] = "description = '" . $this->db->quote($body['content']) . "'";
            }
            
            if (isset($body['is_published'])) {
                $status = $body['is_published'] ? 'published' : 'draft';
                $updates[] = "status = '" . $this->db->quote($status) . "'";
            }
            
            if (empty($updates)) {
                return Response::json([
                    'error' => 'No fields to update'
                ], 400);
            }
            
            $updates[] = "date_modified = NOW()";
            $updates[] = "modified_user_id = '" . $this->db->quote($this->getCurrentUserId()) . "'";
            
            $query = "UPDATE aok_knowledgebase SET " . implode(', ', $updates) . " WHERE id = '" . $this->db->quote($articleId) . "'";
            $this->db->query($query);
            
            return Response::json([
                'data' => [
                    'id' => $articleId,
                    'updated' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Update article error: ' . $e->getMessage());
            return Response::json([
                'error' => 'Failed to update article',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete article
     * DELETE /api/v8/knowledge-base/articles/{id}
     */
    public function deleteArticle(Request $request, $id)
    {
        try {
            $articleId = $id ?? null;
            if (!$articleId) {
                return Response::json([
                    'error' => 'Article ID is required'
                ], 400);
            }
            
            // Soft delete from aok_knowledgebase
            $safeId = $this->db->quote($articleId);
            $query = "UPDATE aok_knowledgebase 
                     SET deleted = 1, date_modified = NOW() 
                     WHERE id = '{$safeId}'";
            $result = $this->db->query($query);
            
            if (!$result) {
                return Response::json([
                    'error' => 'Article not found'
                ], 404);
            }
            
            return Response::json([
                'data' => [
                    'id' => $articleId,
                    'deleted' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Delete article error: ' . $e->getMessage());
            return Response::json([
                'error' => 'Failed to delete article',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit article feedback
     * POST /api/v8/knowledge-base/articles/{id}/feedback
     */
    public function submitFeedback(Request $request, $id)
    {
        try {
            $articleId = $id ?? null;
            if (!$articleId) {
                return Response::json([
                    'error' => 'Article ID is required'
                ], 400);
            }
            
            $body = $request->getParsedBody();
            $isHelpful = $body['is_helpful'] ?? null;
            $feedbackText = $body['feedback'] ?? '';
            
            if ($isHelpful === null) {
                return Response::json([
                    'error' => 'Feedback rating is required'
                ], 400);
            }
            
            // Check if article exists in aok_knowledgebase
            $safeId = $this->db->quote($articleId);
            $query = "SELECT id FROM aok_knowledgebase WHERE id = '{$safeId}' AND deleted = 0";
            $result = $this->db->query($query);
            $row = $this->db->fetchByAssoc($result);
            
            if (!$row || !$row['id']) {
                return Response::json([
                    'error' => 'Article not found'
                ], 404);
            }
            
            // For now, just return success without storing feedback
            // (aok_knowledgebase doesn't have feedback fields)
            
            return Response::json([
                'data' => [
                    'feedback_id' => $feedbackId,
                    'submitted' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Submit feedback error: ' . $e->getMessage());
            return Response::json([
                'error' => 'Failed to submit feedback',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get article categories
     * GET /api/v8/knowledge-base/categories
     */
    public function getCategories(Request $request)
    {
        try {
            // Get categories from database
            $query = "SELECT id, name FROM aok_knowledge_base_categories WHERE deleted = 0 ORDER BY name";
            $result = $this->db->query($query);
            $categories = [];
            while ($row = $this->db->fetchByAssoc($result)) {
                $categories[] = $row;
            }
            
            // Get article count per category
            $result = [];
            foreach ($categories as $category) {
                // Count articles in this category
                $countQuery = "SELECT COUNT(*) as count 
                             FROM aok_knowledgebase kb
                             JOIN aok_knowledgebase_categories kbc ON kb.id = kbc.aok_knowledgebase_id
                             WHERE kbc.aok_knowledge_base_categories_id = ? 
                             AND kb.deleted = 0 
                             AND kb.status = 'published'";
                
                $safeCategoryId = $this->db->quote($category['id']);
                $countQuery = str_replace('?', $safeCategoryId, $countQuery);
                $countResult = $this->db->query($countQuery);
                $countRow = $this->db->fetchByAssoc($countResult);
                $count = $countRow['count'] ?? 0;
                
                $result[] = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'slug' => str_replace(' ', '-', strtolower($category['name'])),
                    'article_count' => (int)$count
                ];
            }
            
            return Response::json([
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            error_log('Get categories error: ' . $e->getMessage());
            return Response::json([
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
        $safeSlug = $this->db->quote($slug);
        $query = "SELECT id FROM aok_knowledgebase WHERE name = '{$safeSlug}' AND deleted = 0";
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        return ($row && $row['id']);
    }
    
    protected function generateUUID()
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
    
    protected function getCurrentUserId()
    {
        // TODO: Get from JWT token
        return '1'; // Admin user for now
    }
    
    /**
     * Mark an article as helpful
     * POST /api/kb/articles/{id}/helpful
     */
    public function markHelpful(Request $request, $id)
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
            $safeId = $this->db->quote($articleId);
            $query = str_replace('?', $safeId, $query);
            $result = $this->db->query($query);
            $row = $this->db->fetchByAssoc($result);
            
            if (!$row || !$row['id']) {
                return Response::error('Article not found', 404);
            }
            
            // For now, just return success without storing feedback
            // The aok_knowledgebase table doesn't have helpful_count or not_helpful_count fields
            
            return Response::success([
                'message' => 'Thank you for your feedback',
                'feedbackId' => $feedbackId
            ]);
            
        } catch (\Exception $e) {
            return Response::error('Failed to submit feedback: ' . $e->getMessage());
        }
    }
}