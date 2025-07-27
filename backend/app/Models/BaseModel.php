<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

abstract class BaseModel extends Model
{
    // SuiteCRM uses custom timestamps
    const CREATED_AT = 'date_entered';
    const UPDATED_AT = 'date_modified';
    
    // SuiteCRM uses string IDs
    public $incrementing = false;
    protected $keyType = 'string';
    
    // Soft deletes using 'deleted' column
    protected static function boot()
    {
        parent::boot();
        
        // Auto-generate UUID on create
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Uuid::uuid4()->toString();
            }
        });
        
        // Apply soft delete scope
        static::addGlobalScope('notDeleted', function ($builder) {
            $builder->where('deleted', 0);
        });
    }
    
    // SuiteCRM soft delete
    public function delete()
    {
        $this->deleted = 1;
        return $this->save();
    }
}
