<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company',
        'website',
        'locality',
        'person',
        'office',
    ];

    /**
     * Link to owning user account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cases where the legal acts on the sell side.
     */
    public function sellSideCases()
    {
        return $this->hasMany(CaseFile::class, 'sell_legal_id', 'user_id');
    }

    /**
     * Cases where the legal acts on the buy side.
     */
    public function buySideCases()
    {
        return $this->hasMany(CaseFile::class, 'buy_legal_id', 'user_id');
    }
}
