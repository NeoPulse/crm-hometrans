<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'name',
        'side',
        'status',
        'deadline',
    ];

    /**
     * Stage parent relation.
     */
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Related attentions for the task.
     */
    public function attentions()
    {
        return $this->hasMany(Attention::class, 'target_id')->where('target_type', 'task');
    }
}
