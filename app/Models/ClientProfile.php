<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    use HasFactory;

    /**
     * Allow mass assignment for client profile attributes.
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'letter',
    ];

    /**
     * Relationship: connect the profile back to the owning user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
