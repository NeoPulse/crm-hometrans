<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    /**
     * Permit mass assignment on core task columns.
     */
    protected $fillable = [
        'stage_id',
        'name',
        'side',
        'status',
        'deadline',
    ];

    /**
     * Cast date fields for consistent formatting.
     */
    protected $casts = [
        'deadline' => 'date',
    ];

    /**
     * Relationship: each task belongs to a stage.
     */
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Relationship: attention markers scoped to this task and user.
     */
    public function attentions()
    {
        return $this->hasMany(Attention::class, 'target_id')->where('target_type', 'task');
    }
}
