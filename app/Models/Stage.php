<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'name',
    ];

    /**
     * Parent case relationship.
     */
    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    /**
     * Tasks attached to the stage.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'stage_id');
    }

    /**
     * Shortcut for progress calculation.
     */
    public function completedTaskRatio(): float
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return 0.0;
        }

        $done = $this->tasks()->where('status', 'done')->count();

        return round(($done / $total) * 100, 2);
    }
}
