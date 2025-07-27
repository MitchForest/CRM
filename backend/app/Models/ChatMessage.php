<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends BaseModel
{
    protected $table = 'ai_chat_messages';
    
    protected $fillable = [
        'conversation_id',
        'message_type',
        'content',
        'metadata',
        'created_at'
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id', 'conversation_id');
    }
    
    public function isUserMessage(): bool
    {
        return $this->message_type === 'user';
    }
    
    public function isAssistantMessage(): bool
    {
        return $this->message_type === 'assistant';
    }
    
    public function isSystemMessage(): bool
    {
        return $this->message_type === 'system';
    }
}