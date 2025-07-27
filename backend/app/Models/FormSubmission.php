<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends BaseModel
{
    protected $table = 'form_builder_submissions';
    
    protected $fillable = [
        'form_id',
        'lead_id',
        'contact_id',
        'data',
        'ip_address',
        'user_agent',
        'referrer',
        'created_at'
    ];
    
    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime'
    ];
    
    public $timestamps = false;
    
    public function form(): BelongsTo
    {
        return $this->belongsTo(FormBuilderForm::class, 'form_id', 'form_id');
    }
    
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
    
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
    
    public function getFieldValue(string $fieldName)
    {
        return $this->data[$fieldName] ?? null;
    }
}