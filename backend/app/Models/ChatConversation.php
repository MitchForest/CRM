<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

class ChatConversation extends Model
{
    protected $table = 'ai_chat_conversations';
    
    protected $fillable = [
        'visitor_id',
        'lead_id',
        'contact_id',
        'status',
        'channel',
        'metadata',
        'date_started',
        'date_ended',
        'date_modified'
    ];
    
    protected $casts = [
        'date_started' => 'datetime',
        'date_ended' => 'datetime',
        'date_modified' => 'datetime',
        'metadata' => 'json'
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
            $start = new \DateTime($this->started_at);
            $end = new \DateTime($this->ended_at);
            $diff = $end->getTimestamp() - $start->getTimestamp();
            return intval($diff / 60);
        }
        return null;
    }
}