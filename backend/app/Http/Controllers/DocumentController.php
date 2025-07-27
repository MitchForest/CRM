<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentRevision;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class DocumentController extends Controller
{
    /**
     * List documents
     * GET /api/crm/documents
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 20);
        
        if ($limit < 1 || $limit > 100) {
            return $this->error($response, 'Limit must be between 1 and 100', 400);
        }
        
        $query = Document::with(['latestRevision', 'assignedUser'])
            ->where('deleted', 0);
        
        // Apply filters
        if (isset($params['parent_type']) && isset($params['parent_id'])) {
            $query->where('parent_type', $params['parent_type'])
                  ->where('parent_id', $params['parent_id']);
        }
        
        if (isset($params['category'])) {
            $query->where('category_id', $params['category']);
        }
        
        if (isset($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('document_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Order by date
        $query->orderBy('date_entered', 'desc');
        
        // Paginate
        $documents = $query->paginate($limit, ['*'], 'page', $page);
        
        return $this->json($response, [
            'data' => $documents->items(),
            'pagination' => [
                'page' => $documents->currentPage(),
                'limit' => $documents->perPage(),
                'total' => $documents->total(),
                'total_pages' => $documents->lastPage()
            ]
        ]);
    }
    
    /**
     * Get document details
     * GET /api/crm/documents/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $document = Document::with(['latestRevision', 'revisions', 'assignedUser'])
            ->where('deleted', 0)
            ->find($id);
        
        if (!$document) {
            return $this->error($response, 'Document not found', 404);
        }
        
        return $this->json($response, ['data' => $document]);
    }
    
    /**
     * Upload new document
     * POST /api/crm/documents
     */
    public function upload(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();
        
        // Validate required fields
        if (empty($data['name'])) {
            return $this->error($response, 'Name is required', 400);
        }
        
        if (empty($uploadedFiles['file'])) {
            return $this->error($response, 'File is required', 400);
        }
        
        $file = $uploadedFiles['file'];
        
        // Validate file upload
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->error($response, 'File upload failed', 400);
        }
        
        // Check file size (10MB max)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->error($response, 'File size exceeds 10MB limit', 400);
        }
        
        DB::beginTransaction();
        
        try {
            $userId = $request->getAttribute('user_id');
            
            // Create document record
            $document = new Document();
            $document->document_name = $data['name'];
            $document->description = $data['description'] ?? '';
            $document->category_id = $data['category_id'] ?? null;
            $document->parent_type = $data['parent_type'] ?? null;
            $document->parent_id = $data['parent_id'] ?? null;
            $document->assigned_user_id = $userId;
            $document->status_id = 'Active';
            $document->save();
            
            // Handle file upload
            $revisionId = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads';
            $uploadPath = storage_path("app/{$uploadDir}");
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            // Move uploaded file
            $file->moveTo("{$uploadPath}/{$revisionId}");
            
            // Create document revision
            $revision = new DocumentRevision();
            $revision->id = $revisionId;
            $revision->document_id = $document->id;
            $revision->filename = $file->getClientFilename();
            $revision->file_ext = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            $revision->file_mime_type = $file->getClientMediaType();
            $revision->file_size = $file->getSize();
            $revision->revision = '1';
            $revision->created_by = $userId;
            $revision->save();
            
            // Update document with latest revision
            $document->document_revision_id = $revision->id;
            $document->save();
            
            DB::commit();
            
            return $this->json($response, [
                'data' => $document->load('latestRevision'),
                'message' => 'Document uploaded successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to upload document: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get document by ID (alias for show)
     * GET /api/crm/documents/{id}
     */
    public function getDocument(Request $request, Response $response, array $args): Response
    {
        return $this->show($request, $response, $args);
    }
    
    /**
     * Delete document
     * DELETE /api/crm/documents/{id}
     */
    public function deleteDocument(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $document = Document::where('deleted', 0)->find($id);
        
        if (!$document) {
            return $this->error($response, 'Document not found', 404);
        }
        
        $document->deleted = 1;
        $document->save();
        
        return $this->json($response, ['message' => 'Document deleted successfully']);
    }
    
    /**
     * Download document
     * GET /api/crm/documents/{id}/download
     */
    public function downloadDocument(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $document = Document::with('latestRevision')
            ->where('deleted', 0)
            ->find($id);
        
        if (!$document || !$document->latestRevision) {
            return $this->error($response, 'Document not found', 404);
        }
        
        $revision = $document->latestRevision;
        $uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads';
        $filePath = storage_path("app/{$uploadDir}/{$revision->id}");
        
        if (!file_exists($filePath)) {
            return $this->error($response, 'File not found on disk', 404);
        }
        
        // Set headers for download
        $response = $response
            ->withHeader('Content-Type', $revision->file_mime_type)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $revision->filename . '"')
            ->withHeader('Content-Length', $revision->file_size);
        
        // Stream file content
        $stream = fopen($filePath, 'r');
        if ($stream === false) {
            return $this->error($response, 'Failed to read file', 500);
        }
        
        $response->getBody()->write(stream_get_contents($stream));
        fclose($stream);
        
        return $response;
    }
    
    /**
     * Update document metadata
     * PUT /api/crm/documents/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $document = Document::where('deleted', 0)->find($id);
        
        if (!$document) {
            return $this->error($response, 'Document not found', 404);
        }
        
        $data = $this->validate($request, [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'sometimes|string',
            'status_id' => 'sometimes|string'
        ]);
        
        if (isset($data['name'])) $document->document_name = $data['name'];
        if (isset($data['description'])) $document->description = $data['description'];
        if (isset($data['category_id'])) $document->category_id = $data['category_id'];
        if (isset($data['status_id'])) $document->status_id = $data['status_id'];
        
        $document->save();
        
        return $this->json($response, [
            'data' => $document->load('latestRevision'),
            'message' => 'Document updated successfully'
        ]);
    }
    
    /**
     * Upload new revision
     * POST /api/crm/documents/{id}/revisions
     */
    public function uploadRevision(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $document = Document::where('deleted', 0)->find($id);
        
        if (!$document) {
            return $this->error($response, 'Document not found', 404);
        }
        
        $uploadedFiles = $request->getUploadedFiles();
        
        if (empty($uploadedFiles['file'])) {
            return $this->error($response, 'File is required', 400);
        }
        
        $file = $uploadedFiles['file'];
        
        // Validate file upload
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->error($response, 'File upload failed', 400);
        }
        
        // Check file size (10MB max)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->error($response, 'File size exceeds 10MB limit', 400);
        }
        
        DB::beginTransaction();
        
        try {
            $userId = $request->getAttribute('user_id');
            
            // Get current revision number
            $currentRevision = DocumentRevision::where('document_id', $id)
                ->orderBy('revision', 'desc')
                ->first();
            
            $newRevisionNumber = $currentRevision ? ((int)$currentRevision->revision + 1) : 1;
            
            // Handle file upload
            $revisionId = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads';
            $uploadPath = storage_path("app/{$uploadDir}");
            
            // Move uploaded file
            $file->moveTo("{$uploadPath}/{$revisionId}");
            
            // Create new revision
            $revision = new DocumentRevision();
            $revision->id = $revisionId;
            $revision->document_id = $document->id;
            $revision->filename = $file->getClientFilename();
            $revision->file_ext = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            $revision->file_mime_type = $file->getClientMediaType();
            $revision->file_size = $file->getSize();
            $revision->revision = (string)$newRevisionNumber;
            $revision->created_by = $userId;
            $revision->save();
            
            // Update document with latest revision
            $document->document_revision_id = $revision->id;
            $document->save();
            
            DB::commit();
            
            return $this->json($response, [
                'data' => $revision,
                'message' => 'New revision uploaded successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to upload revision: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete document (alias for destroy)
     * DELETE /api/crm/documents/{id}
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        return $this->deleteDocument($request, $response, $args);
    }
}

// Helper function for storage_path if not exists
if (!function_exists('storage_path')) {
    function storage_path($path = '') {
        return dirname(dirname(dirname(__DIR__))) . '/storage' . ($path ? '/' . $path : '');
    }
}