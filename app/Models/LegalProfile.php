<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalProfile extends Model
{
    use HasFactory;

    /**
     * Allow mass assignment for solicitor-specific attributes.
     */
    protected $fillable = [
        'user_id',
        'company',
        'website',
        'locality',
        'person',
        'office',
    ];

    /**
     * Relationship: connect the profile back to the owning user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
