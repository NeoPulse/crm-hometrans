<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseChatMessage extends Model
{
    use HasFactory;

    /**
     * Allow controlled mass assignment for chat messages.
     */
    protected $fillable = [
        'case_id',
        'user_id',
        'sender_label',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'attachment_mime',
    ];

    /**
     * Relationship: owning case file for the message.
     */
    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    /**
     * Relationship: author of the chat message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: unread attention markers tied to the message.
     */
    public function attentions()
    {
        return $this->hasMany(Attention::class, 'target_id')->where('target_type', 'chat');
    }
}
