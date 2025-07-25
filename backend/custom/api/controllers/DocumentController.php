<?php
namespace Api\Controllers;

use Api\Response;
use Api\Request;

class DocumentController extends BaseController
{
    public function downloadDocument(Request $request): Response
    {
        try {
            $documentId = $request->getParam('id');
            
            // Validate document ID format
            if (!preg_match('/^[a-f0-9\-]{36}$/', $documentId)) {
                return Response::json(['error' => 'Invalid document ID'], 400);
            }
            
            $db = $this->getDb();
            
            // Get document details
            $stmt = $db->prepare("SELECT d.*, dr.id as revision_id, dr.filename, dr.file_ext, 
                                        dr.file_mime_type, dr.file_size
                                 FROM documents d
                                 JOIN document_revisions dr ON d.document_revision_id = dr.id
                                 WHERE d.id = :id AND d.deleted = 0 AND dr.deleted = 0");
            $stmt->execute(['id' => $documentId]);
            $document = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$document) {
                return Response::json(['error' => 'Document not found'], 404);
            }
            
            // Build file path - SuiteCRM stores files in upload directory with revision ID as filename
            $uploadDir = getenv('SUITECRM_UPLOAD_DIR') ?: '/var/www/html/upload/';
            $filePath = rtrim($uploadDir, '/') . '/' . $document['revision_id'];
            
            if (!file_exists($filePath)) {
                return Response::json(['error' => 'File not found on disk'], 404);
            }
            
            // Check file permissions
            if (!is_readable($filePath)) {
                return Response::json(['error' => 'File not readable'], 403);
            }
            
            // Set appropriate headers for file download
            header('Content-Type: ' . $document['file_mime_type']);
            header('Content-Disposition: attachment; filename="' . $document['filename'] . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Stream the file
            readfile($filePath);
            exit();
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}