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
        'password',
        'address1',
        'address2',
        'headline',
        'notes',
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
        ];
    }

    /**
     * Relationship: attach a single client profile with extended details.
     */
    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    /**
     * Relationship: attach a legal profile for solicitor metadata.
     */
    public function legalProfile()
    {
        return $this->hasOne(LegalProfile::class);
    }

    /**
     * Relationship: pull all attentions related to this user.
     */
    public function attentions()
    {
        return $this->hasMany(Attention::class, 'target_id')->where('target_type', 'user');
    }

    /**
     * Relationship: fetch cases where the user is the selling client.
     */
    public function sellCases()
    {
        return $this->hasMany(CaseFile::class, 'sell_client_id');
    }

    /**
     * Relationship: fetch cases where the user is the buying client.
     */
    public function buyCases()
    {
        return $this->hasMany(CaseFile::class, 'buy_client_id');
    }

    /**
     * Relationship: fetch cases where the user acts as the selling solicitor.
     */
    public function sellLegalCases()
    {
        return $this->hasMany(CaseFile::class, 'sell_legal_id');
    }

    /**
     * Relationship: fetch cases where the user acts as the buying solicitor.
     */
    public function buyLegalCases()
    {
        return $this->hasMany(CaseFile::class, 'buy_legal_id');
    }

    /**
     * Helper attribute that returns the preferred display name for the user.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->role === 'legal' && $this->legalProfile && $this->legalProfile->person) {
            return $this->legalProfile->person;
        }

        if ($this->clientProfile && ($this->clientProfile->first_name || $this->clientProfile->last_name)) {
            return trim(($this->clientProfile->first_name ?? '') . ' ' . ($this->clientProfile->last_name ?? ''));
        }

        return $this->name ?: ($this->email ?? 'Client');
    }
}
