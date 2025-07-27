<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class ChatMessage extends Model
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
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Uuid::uuid4()->toString();
            }
        });
    }
    
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