<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'email', 'password', 'persona_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The guard name for Spatie permissions.
     * Forcing 'web' ensures Sanctum uses the default web permissions.
     */
    protected string $guard_name = 'web';

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
     * Get the persona associated with the user.
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Get an array of sector IDs the user has access to, based on their persona.
     */
    public function getAllowedSectorIds(): array
    {
        if (!$this->persona_id) {
            return [];
        }

        $persona = $this->persona;

        // Sectors assigned directly
        $directSectorIds = $persona->sectorPersonas()->pluck('sector_id')->toArray();

        // Sectors from assigned bases
        $baseSectorIds = \App\Models\Base::whereIn('id', $persona->basePersonas()->pluck('base_id'))
                            ->pluck('sector_id')->toArray();

        return array_unique(array_merge($directSectorIds, $baseSectorIds));
    }
    /**
     * Get an array of base IDs the user has direct access to, based on their persona.
     */
    public function getAllowedBaseIds(): array
    {
        if (!$this->persona_id) {
            return [];
        }

        return $this->persona->basePersonas()->pluck('base_id')->toArray();
    }
}
