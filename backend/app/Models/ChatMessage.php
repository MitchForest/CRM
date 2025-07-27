<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends BaseModel
{
    protected $table = 'ai_chat_messages';
    
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'metadata',
        'date_entered'
    ];
    
    protected $casts = [
        'metadata' => 'json',
        'date_entered' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id', 'conversation_id');
    }
    
    public function isUserMessage(): bool
    {
        return $this->role === 'user';
    }
    
    public function isAssistantMessage(): bool
    {
        return $this->role === 'assistant';
    }
    
    public function isSystemMessage(): bool
    {
        return $this->role === 'system';
    }
}