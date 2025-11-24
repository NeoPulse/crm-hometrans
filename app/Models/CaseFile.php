<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CaseFile extends Model
{
    use HasFactory;

    protected $table = 'cases';

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

    protected $casts = [
        'deadline' => 'datetime',
    ];

    /**
     * Auto-generate a public link when absent.
     */
    protected static function booted(): void
    {
        static::creating(function (CaseFile $case) {
            if (empty($case->public_link)) {
                $case->public_link = Str::random(random_int(8, 16));
            }
        });
    }

    public function sellLegal()
    {
        return $this->belongsTo(User::class, 'sell_legal_id');
    }

    public function sellClient()
    {
        return $this->belongsTo(User::class, 'sell_client_id');
    }

    public function buyLegal()
    {
        return $this->belongsTo(User::class, 'buy_legal_id');
    }

    public function buyClient()
    {
        return $this->belongsTo(User::class, 'buy_client_id');
    }

    public function stages()
    {
        return $this->hasMany(Stage::class, 'case_id');
    }

    public function attentions()
    {
        return $this->hasMany(Attention::class, 'target_id')->where('target_type', 'case');
    }

    public function chatMessages()
    {
        return $this->hasMany(CaseChatMessage::class, 'case_id');
    }
}
