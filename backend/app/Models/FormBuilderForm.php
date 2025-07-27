<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class FormBuilderForm extends BaseModel
{
    protected $table = 'form_builder_forms';
    
    protected $fillable = [
        'name',
        'description',
        'fields',
        'settings',
        'is_active',
        'created_by',
        'date_entered',
        'date_modified',
        'deleted'
    ];
    
    protected $casts = [
        'fields' => 'json',
        'settings' => 'json',
        'is_active' => 'integer',
        'date_entered' => 'datetime',
        'date_modified' => 'datetime',
        'deleted' => 'integer'
    ];
    
    public $timestamps = false;
    
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