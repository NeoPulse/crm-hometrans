<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    use HasFactory;

    /**
     * Allow mass assignment for key stage attributes.
     */
    protected $fillable = [
        'case_id',
        'name',
    ];

    /**
     * Relationship: each stage belongs to a single case file.
     */
    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    /**
     * Relationship: a stage owns many tasks that track completion.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class)->orderBy('id');
    }

    /**
     * Relationship: attention markers tied to the stage for per-user badges.
     */
    public function attentions()
    {
        return $this->hasMany(Attention::class, 'target_id')->where('target_type', 'stage');
    }
}
