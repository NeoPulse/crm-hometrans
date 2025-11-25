<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    /**
     * Allow mass assignment for chat message attributes.
     */
    protected $fillable = [
        'case_id',
        'user_id',
        'sender_label',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
    ];

    /**
     * Relationship: link the message to its parent case file.
     */
    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    /**
     * Relationship: resolve the author of the chat message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: fetch attention flags tied to this chat entry.
     */
    public function attentions()
    {
        return $this->hasMany(Attention::class, 'target_id')->where('target_type', 'chat_message');
    }
}
