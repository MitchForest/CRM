<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * List documents
     * GET /api/crm/documents
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'parent_type' => 'sometimes|string',
            'parent_id' => 'sometimes|string',
            'category' => 'sometimes|string',
            'search' => 'sometimes|string'
        ]);
        
        $query = Document::with(['latestRevision', 'assignedUser'])
            ->where('deleted', 0);
        
        // Apply filters
        if ($request->has('parent_type') && $request->has('parent_id')) {
            $query->where('parent_type', $request->input('parent_type'))
                  ->where('parent_id', $request->input('parent_id'));
        }
        
        if ($request->has('category')) {
            $query->where('category_id', $request->input('category'));
        }
        
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('document_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Order by date
        $query->orderBy('date_entered', 'desc');
        
        // Paginate
        $limit = $request->input('limit', 20);
        $documents = $query->paginate($limit);
        
        return response()->json([
            'data' => $documents->items(),
            'pagination' => [
                'page' => $documents->currentPage(),
                'limit' => $documents->perPage(),
                'total' => $documents->total(),
                'totalPages' => $documents->lastPage()
            ]
        ]);
    }
    
    /**
     * Get document details
     * GET /api/crm/documents/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $document = Document::with(['latestRevision', 'revisions', 'assignedUser'])
            ->where('deleted', 0)
            ->find($id);
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        
        return response()->json(['data' => $document]);
    }
    
    /**
     * Upload new document
     * POST /api/crm/documents
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'sometimes|string',
            'parent_type' => 'sometimes|string',
            'parent_id' => 'sometimes|string',
            'file' => 'required|file|max:10240' // 10MB max
        ]);
        
        DB::beginTransaction();
        
        try {
            // Create document record
            $document = Document::create([
                'document_name' => $request->input('name'),
                'description' => $request->input('description'),
                'category_id' => $request->input('category_id'),
                'parent_type' => $request->input('parent_type'),
                'parent_id' => $request->input('parent_id'),
                'assigned_user_id' => $request->user()->id,
                'status_id' => 'Active'
            ]);
            
            // Handle file upload
            $file = $request->file('file');
            $revisionId = \Ramsey\Uuid\Uuid::uuid4()->toString();
            
            // Store file with revision ID as filename
            $uploadDir = env('UPLOAD_DIR', 'uploads');
            $file->storeAs($uploadDir, $revisionId);
            
            // Create document revision
            $revision = DocumentRevision::create([
                'id' => $revisionId,
                'document_id' => $document->id,
                'filename' => $file->getClientOriginalName(),
                'file_ext' => $file->getClientOriginalExtension(),
                'file_mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'revision' => '1',
                'created_by' => $request->user()->id
            ]);
            
            // Update document with latest revision
            $document->document_revision_id = $revision->id;
            $document->save();
            
            DB::commit();
            
            return response()->json([
                'data' => $document->load('latestRevision'),
                'message' => 'Document uploaded successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to upload document',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update document metadata
     * PUT /api/crm/documents/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $document = Document::where('deleted', 0)->find($id);
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'sometimes|string',
            'status_id' => 'sometimes|string'
        ]);
        
        $document->fill([
            'document_name' => $request->input('name', $document->document_name),
            'description' => $request->input('description', $document->description),
            'category_id' => $request->input('category_id', $document->category_id),
            'status_id' => $request->input('status_id', $document->status_id)
        ]);
        
        $document->save();
        
        return response()->json([
            'data' => $document->load('latestRevision'),
            'message' => 'Document updated successfully'
        ]);
    }
    
    /**
     * Upload new revision
     * POST /api/crm/documents/{id}/revisions
     */
    public function uploadRevision(Request $request, string $id): JsonResponse
    {
        $document = Document::where('deleted', 0)->find($id);
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        
        $request->validate([
            'file' => 'required|file|max:10240' // 10MB max
        ]);
        
        DB::beginTransaction();
        
        try {
            // Get current revision number
            $currentRevision = DocumentRevision::where('document_id', $id)
                ->orderBy('revision', 'desc')
                ->first();
            
            $newRevisionNumber = $currentRevision ? ((int)$currentRevision->revision + 1) : 1;
            
            // Handle file upload
            $file = $request->file('file');
            $revisionId = \Ramsey\Uuid\Uuid::uuid4()->toString();
            
            // Store file
            $uploadDir = env('UPLOAD_DIR', 'uploads');
            $file->storeAs($uploadDir, $revisionId);
            
            // Create new revision
            $revision = DocumentRevision::create([
                'id' => $revisionId,
                'document_id' => $document->id,
                'filename' => $file->getClientOriginalName(),
                'file_ext' => $file->getClientOriginalExtension(),
                'file_mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'revision' => (string)$newRevisionNumber,
                'created_by' => $request->user()->id
            ]);
            
            // Update document with latest revision
            $document->document_revision_id = $revision->id;
            $document->save();
            
            DB::commit();
            
            return response()->json([
                'data' => $revision,
                'message' => 'New revision uploaded successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to upload revision',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Download document
     * GET /api/crm/documents/{id}/download
     */
    public function download(Request $request, string $id): StreamedResponse
    {
        $document = Document::with('latestRevision')
            ->where('deleted', 0)
            ->find($id);
        
        if (!$document || !$document->latestRevision) {
            abort(404, 'Document not found');
        }
        
        $revision = $document->latestRevision;
        $uploadDir = env('UPLOAD_DIR', 'uploads');
        $filePath = storage_path("app/{$uploadDir}/{$revision->id}");
        
        if (!file_exists($filePath)) {
            abort(404, 'File not found on disk');
        }
        
        return response()->stream(
            function () use ($filePath) {
                $stream = fopen($filePath, 'r');
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $revision->file_mime_type,
                'Content-Disposition' => 'attachment; filename="' . $revision->filename . '"',
                'Content-Length' => $revision->file_size
            ]
        );
    }
    
    /**
     * Delete document
     * DELETE /api/crm/documents/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $document = Document::where('deleted', 0)->find($id);
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        
        $document->deleted = 1;
        $document->save();
        
        return response()->json(['message' => 'Document deleted successfully']);
    }
}