<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attention extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_type',
        'target_id',
        'type',
        'user_id',
    ];

    /**
     * User receiving the attention marker.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
