<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    protected $fillable = [
        'source_file_name',
        'text',
        'embedding',
        'embedding_provider',
        'embedding_dimension'
    ];

    protected $casts = [
        'embedding' => 'array'
    ];

    public function setEmbeddingAttribute($value)
    {
        $this->attributes['embedding'] = DB::raw("'[" . implode(',', $value) . "]'::vector");
    }

    public function getEmbeddingAttribute($value)
    {
        if (is_string($value)) {
            $value = trim($value, '[]');
            return array_map('floatval', explode(',', $value));
        }
        return $value;
    }
}
