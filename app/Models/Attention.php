<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attention extends Model
{
    use HasFactory;

    /**
     * Allow mass assignment for attention properties.
     */
    protected $fillable = [
        'target_type',
        'target_id',
        'type',
        'user_id',
    ];

    /**
     * Relationship: associate the attention entry with the owning case.
     */
    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'target_id');
    }

    /**
     * Relationship: resolve the user who created the attention flag.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
