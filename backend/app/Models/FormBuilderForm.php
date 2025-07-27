<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class FormBuilderForm extends BaseModel
{
    protected $table = 'form_builder_forms';
    
    protected $fillable = [
        'name',
        'form_id',
        'fields',
        'settings',
        'status',
        'embed_code',
        'created_by'
    ];
    
    protected $casts = [
        'fields' => 'array',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public $timestamps = true;
    
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_id', 'form_id');
    }
    
    public function getSubmissionCountAttribute(): int
    {
        return $this->submissions()->count();
    }
    
    public function getConversionRateAttribute(): ?float
    {
        // This would need view count tracking
        return null;
    }
    
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    public function generateEmbedCode(): string
    {
        return sprintf(
            '<div data-sassy-form="%s"></div><script src="%s/js/forms-embed.js"></script>',
            $this->form_id,
            $_ENV['APP_URL'] ?? 'http://localhost:8080'
        );
    }
}