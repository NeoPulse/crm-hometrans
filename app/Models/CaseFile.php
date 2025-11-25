<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseFile extends Model
{
    use HasFactory;

    /**
     * Explicitly define the table to avoid reserved keyword conflicts.
     */
    protected $table = 'cases';

    /**
     * Allow mass assignment for editable fields.
     */
    protected $fillable = [
        'postal_code',
        'sell_legal_id',
        'sell_client_id',
        'buy_legal_id',
        'buy_client_id',
        'deadline',
        'property',
        'status',
        'headline',
        'notes',
        'public_link',
    ];

    /**
     * Provide casts for date and text columns.
     */
    protected $casts = [
        'deadline' => 'date',
    ];

    /**
     * Relationship: retrieve all attention flags linked to this case.
     */
    public function attentions()
    {
        return $this->hasMany(Attention::class, 'target_id')->where('target_type', 'case');
    }

    /**
     * Relationship: resolve the selling side legal representative.
     */
    public function sellLegal()
    {
        return $this->belongsTo(User::class, 'sell_legal_id');
    }

    /**
     * Relationship: resolve the selling side client participant.
     */
    public function sellClient()
    {
        return $this->belongsTo(User::class, 'sell_client_id');
    }

    /**
     * Relationship: resolve the buying side legal representative.
     */
    public function buyLegal()
    {
        return $this->belongsTo(User::class, 'buy_legal_id');
    }

    /**
     * Relationship: resolve the buying side client participant.
     */
    public function buyClient()
    {
        return $this->belongsTo(User::class, 'buy_client_id');
    }
}
