<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'letter',
    ];

    /**
     * Link to owning user account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cases tied to the client on the sell side.
     */
    public function sellSideCases()
    {
        return $this->hasMany(CaseFile::class, 'sell_client_id', 'user_id');
    }

    /**
     * Cases tied to the client on the buy side.
     */
    public function buySideCases()
    {
        return $this->hasMany(CaseFile::class, 'buy_client_id', 'user_id');
    }
}
