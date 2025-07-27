<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends BaseModel
{
    protected $table = 'ai_chat_conversations';
    
    protected $fillable = [
        'conversation_id',
        'visitor_id',
        'lead_id',
        'contact_id',
        'started_at',
        'ended_at',
        'status',
        'rating',
        'feedback',
        'metadata'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'rating' => 'integer',
        'metadata' => 'array'
    ];
    
    public $timestamps = false;
    
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
    
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
    
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id', 'conversation_id')
            ->orderBy('created_at');
    }
    
    public function getTotalMessagesAttribute(): int
    {
        return $this->messages()->count();
    }
    
    public function getDurationMinutesAttribute(): ?int
    {
        if ($this->started_at && $this->ended_at) {
            return $this->started_at->diffInMinutes($this->ended_at);
        }
        return null;
    }
}