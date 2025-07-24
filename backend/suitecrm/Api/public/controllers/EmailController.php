<?php
namespace Api\Controllers;

use Api\Response;
use Api\Request;

class EmailController extends BaseController
{
    public function viewEmail(Request $request): Response
    {
        try {
            $emailId = $request->getParam('id');
            
            // Validate email ID format
            if (!preg_match('/^[a-f0-9\-]{36}$/', $emailId)) {
                return Response::json(['error' => 'Invalid email ID'], 400);
            }
            
            $db = $this->getDb();
            
            // Get email details with text content
            $stmt = $db->prepare("SELECT e.*, et.from_addr_name, et.to_addrs_names, et.cc_addrs_names,
                                        et.description_html, et.description 
                                 FROM emails e
                                 LEFT JOIN emails_text et ON e.id = et.email_id
                                 WHERE e.id = :id AND e.deleted = 0");
            $stmt->execute(['id' => $emailId]);
            $email = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$email) {
                return Response::json(['error' => 'Email not found'], 404);
            }
            
            // Get attachments
            $stmt = $db->prepare("SELECT n.id, n.name, n.file_mime_type, n.filename 
                                 FROM notes n
                                 JOIN emails_beans eb ON n.id = eb.bean_id
                                 WHERE eb.email_id = :email_id 
                                 AND eb.bean_module = 'Notes' 
                                 AND n.deleted = 0");
            $stmt->execute(['email_id' => $emailId]);
            $attachments = [];
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $attachments[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'filename' => $row['filename'],
                    'mime_type' => $row['file_mime_type'],
                ];
            }
            
            return Response::json([
                'data' => [
                    'id' => $email['id'],
                    'name' => $email['name'],
                    'date_sent' => $email['date_sent'],
                    'from' => [
                        'address' => $email['from_addr'],
                        'name' => $email['from_addr_name'],
                    ],
                    'to' => $email['to_addrs_names'],
                    'cc' => $email['cc_addrs_names'],
                    'subject' => $email['name'],
                    'body_text' => $email['description'],
                    'body_html' => $email['description_html'],
                    'attachments' => $attachments,
                    'parent_type' => $email['parent_type'],
                    'parent_id' => $email['parent_id'],
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}