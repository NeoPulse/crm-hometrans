<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'sender_id',
        'sender_alias',
        'body',
        'attachment_path',
    ];

    /**
     * Parent case relation.
     */
    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    /**
     * Sender relation when message is authored by a user.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
