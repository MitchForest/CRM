<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailText;
use App\Models\EmailBean;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    /**
     * List emails
     * GET /api/crm/emails
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'folder' => 'sometimes|string|in:inbox,sent,draft,trash',
            'parent_type' => 'sometimes|string',
            'parent_id' => 'sometimes|string',
            'search' => 'sometimes|string',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from'
        ]);
        
        $query = Email::with(['emailText', 'assignedUser'])
            ->where('deleted', 0);
        
        // Apply folder filter
        $folder = $request->input('folder', 'inbox');
        switch ($folder) {
            case 'sent':
                $query->where('type', 'out');
                break;
            case 'draft':
                $query->where('type', 'draft');
                break;
            case 'trash':
                $query->where('deleted', 1);
                break;
            default: // inbox
                $query->where('type', 'inbound');
                break;
        }
        
        // Apply filters
        if ($request->has('parent_type') && $request->has('parent_id')) {
            $query->where('parent_type', $request->input('parent_type'))
                  ->where('parent_id', $request->input('parent_id'));
        }
        
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('emailText', function ($q) use ($search) {
                      $q->where('from_addr_name', 'like', "%{$search}%")
                        ->orWhere('to_addrs_names', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                  });
            });
        }
        
        if ($request->has('date_from')) {
            $query->where('date_sent', '>=', $request->input('date_from'));
        }
        
        if ($request->has('date_to')) {
            $query->where('date_sent', '<=', $request->input('date_to'));
        }
        
        // Order by date
        $query->orderBy('date_sent', 'desc');
        
        // Paginate
        $limit = $request->input('limit', 20);
        $emails = $query->paginate($limit);
        
        // Format emails
        $formattedEmails = $emails->map(function ($email) {
            return $this->formatEmail($email);
        });
        
        return response()->json([
            'data' => $formattedEmails,
            'pagination' => [
                'page' => $emails->currentPage(),
                'limit' => $emails->perPage(),
                'total' => $emails->total(),
                'totalPages' => $emails->lastPage()
            ]
        ]);
    }
    
    /**
     * Get email details
     * GET /api/crm/emails/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $email = Email::with(['emailText', 'attachments'])
            ->where('deleted', 0)
            ->find($id);
        
        if (!$email) {
            return response()->json(['error' => 'Email not found'], 404);
        }
        
        // Mark as read
        if ($email->status === 'unread') {
            $email->status = 'read';
            $email->save();
        }
        
        return response()->json(['data' => $this->formatEmailDetail($email)]);
    }
    
    /**
     * Create/send email
     * POST /api/crm/emails
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|array|min:1',
            'to.*' => 'email',
            'cc' => 'sometimes|array',
            'cc.*' => 'email',
            'bcc' => 'sometimes|array',
            'bcc.*' => 'email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'body_html' => 'sometimes|string',
            'parent_type' => 'sometimes|string',
            'parent_id' => 'sometimes|string',
            'is_draft' => 'sometimes|boolean',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'file|max:10240' // 10MB max
        ]);
        
        DB::beginTransaction();
        
        try {
            $user = $request->user();
            $isDraft = $request->boolean('is_draft', false);
            
            // Create email record
            $email = Email::create([
                'name' => $request->input('subject'),
                'type' => $isDraft ? 'draft' : 'out',
                'status' => $isDraft ? 'draft' : 'sent',
                'date_sent' => $isDraft ? null : now(),
                'assigned_user_id' => $user->id,
                'parent_type' => $request->input('parent_type'),
                'parent_id' => $request->input('parent_id'),
                'from_addr' => $user->email1,
                'from_addr_name' => $user->full_name,
                'to_addrs' => implode(';', $request->input('to')),
                'cc_addrs' => $request->has('cc') ? implode(';', $request->input('cc')) : null,
                'bcc_addrs' => $request->has('bcc') ? implode(';', $request->input('bcc')) : null
            ]);
            
            // Create email text
            EmailText::create([
                'email_id' => $email->id,
                'from_addr_name' => $user->full_name,
                'to_addrs_names' => implode(', ', $request->input('to')),
                'cc_addrs_names' => $request->has('cc') ? implode(', ', $request->input('cc')) : null,
                'bcc_addrs_names' => $request->has('bcc') ? implode(', ', $request->input('bcc')) : null,
                'description' => $request->input('body'),
                'description_html' => $request->input('body_html', $request->input('body'))
            ]);
            
            // Handle attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $note = Note::create([
                        'name' => $file->getClientOriginalName(),
                        'file_mime_type' => $file->getMimeType(),
                        'filename' => $file->getClientOriginalName(),
                        'parent_type' => 'Emails',
                        'parent_id' => $email->id,
                        'assigned_user_id' => $user->id
                    ]);
                    
                    // Store file
                    $uploadDir = env('UPLOAD_DIR', 'uploads');
                    $file->storeAs($uploadDir, $note->id);
                    
                    // Link to email
                    EmailBean::create([
                        'email_id' => $email->id,
                        'bean_id' => $note->id,
                        'bean_module' => 'Notes'
                    ]);
                }
            }
            
            // Send email if not draft
            if (!$isDraft) {
                // In real implementation, you would send via SMTP
                // Mail::send(...);
            }
            
            DB::commit();
            
            return response()->json([
                'data' => $email->load('emailText'),
                'message' => $isDraft ? 'Draft saved successfully' : 'Email sent successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create email',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update draft email
     * PUT /api/crm/emails/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $email = Email::where('deleted', 0)
            ->where('type', 'draft')
            ->find($id);
        
        if (!$email) {
            return response()->json(['error' => 'Draft not found'], 404);
        }
        
        $request->validate([
            'to' => 'sometimes|array|min:1',
            'to.*' => 'email',
            'cc' => 'sometimes|array',
            'cc.*' => 'email',
            'bcc' => 'sometimes|array',
            'bcc.*' => 'email',
            'subject' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'body_html' => 'sometimes|string'
        ]);
        
        DB::beginTransaction();
        
        try {
            // Update email
            if ($request->has('subject')) {
                $email->name = $request->input('subject');
            }
            
            if ($request->has('to')) {
                $email->to_addrs = implode(';', $request->input('to'));
            }
            
            if ($request->has('cc')) {
                $email->cc_addrs = implode(';', $request->input('cc'));
            }
            
            if ($request->has('bcc')) {
                $email->bcc_addrs = implode(';', $request->input('bcc'));
            }
            
            $email->save();
            
            // Update email text
            if ($email->emailText) {
                $emailText = $email->emailText;
                
                if ($request->has('to')) {
                    $emailText->to_addrs_names = implode(', ', $request->input('to'));
                }
                
                if ($request->has('cc')) {
                    $emailText->cc_addrs_names = implode(', ', $request->input('cc'));
                }
                
                if ($request->has('bcc')) {
                    $emailText->bcc_addrs_names = implode(', ', $request->input('bcc'));
                }
                
                if ($request->has('body')) {
                    $emailText->description = $request->input('body');
                }
                
                if ($request->has('body_html')) {
                    $emailText->description_html = $request->input('body_html');
                }
                
                $emailText->save();
            }
            
            DB::commit();
            
            return response()->json([
                'data' => $email->load('emailText'),
                'message' => 'Draft updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update draft',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete email
     * DELETE /api/crm/emails/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $email = Email::where('deleted', 0)->find($id);
        
        if (!$email) {
            return response()->json(['error' => 'Email not found'], 404);
        }
        
        $email->deleted = 1;
        $email->save();
        
        return response()->json(['message' => 'Email deleted successfully']);
    }
    
    /**
     * Format email for list view
     */
    private function formatEmail(Email $email): array
    {
        return [
            'id' => $email->id,
            'subject' => $email->name,
            'from' => [
                'address' => $email->from_addr,
                'name' => $email->emailText->from_addr_name ?? $email->from_addr_name
            ],
            'to' => $email->emailText->to_addrs_names ?? '',
            'date_sent' => $email->date_sent,
            'status' => $email->status,
            'type' => $email->type,
            'has_attachments' => $email->attachments()->exists(),
            'parent_type' => $email->parent_type,
            'parent_id' => $email->parent_id,
            'preview' => substr($email->emailText->description ?? '', 0, 100) . '...'
        ];
    }
    
    /**
     * Format email with full details
     */
    private function formatEmailDetail(Email $email): array
    {
        $attachments = $email->attachments->map(function ($note) {
            return [
                'id' => $note->id,
                'name' => $note->name,
                'filename' => $note->filename,
                'mime_type' => $note->file_mime_type
            ];
        });
        
        return [
            'id' => $email->id,
            'subject' => $email->name,
            'date_sent' => $email->date_sent,
            'from' => [
                'address' => $email->from_addr,
                'name' => $email->emailText->from_addr_name ?? $email->from_addr_name
            ],
            'to' => $email->emailText->to_addrs_names ?? '',
            'cc' => $email->emailText->cc_addrs_names ?? '',
            'bcc' => $email->emailText->bcc_addrs_names ?? '',
            'body_text' => $email->emailText->description ?? '',
            'body_html' => $email->emailText->description_html ?? '',
            'attachments' => $attachments,
            'parent_type' => $email->parent_type,
            'parent_id' => $email->parent_id,
            'status' => $email->status,
            'type' => $email->type
        ];
    }
}