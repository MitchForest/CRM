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
            if (empty($model->date_modified)) {
                $model->date_modified = new \DateTime();
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
        return $this->hasMany(ChatMessage::class, 'conversation_id', 'id')
            ->orderBy('date_entered');
    }
    
    public function getTotalMessagesAttribute(): int
    {
        return $this->messages()->count();
    }
    
    public function getDurationMinutesAttribute(): ?int
    {
        if ($this->date_started && $this->date_ended) {
            $start = new \DateTime($this->date_started);
            $end = new \DateTime($this->date_ended);
            $diff = $end->getTimestamp() - $start->getTimestamp();
            return intval($diff / 60);
        }
        return null;
    }
}