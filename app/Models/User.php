<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'is_active',
        'phone',
        'address1',
        'address2',
        'headline',
        'notes',
        'avatar_path',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Legal profile relation.
     */
    public function legalProfile()
    {
        return $this->hasOne(LegalProfile::class);
    }

    /**
     * Client profile relation.
     */
    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    /**
     * Attentions destined to this user.
     */
    public function attentions()
    {
        return $this->hasMany(Attention::class);
    }

    /**
     * Chat messages authored by this user.
     */
    public function chatMessages()
    {
        return $this->hasMany(CaseChatMessage::class, 'sender_id');
    }

    /**
     * Activity log entries the user generated.
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
