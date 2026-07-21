<?php

namespace App\Models;

use App\Services\LutTester\DeleteLutTestUpload;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $country_code
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $terms_accepted_at
 * @property Carbon|null $privacy_accepted_at
 * @property string|null $terms_version
 * @property string|null $privacy_version
 * @property bool $is_admin
 * @property bool $is_suspended
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'country_code',
    'password',
    'terms_accepted_at',
    'privacy_accepted_at',
    'terms_version',
    'privacy_version',
    'is_admin',
    'is_suspended',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $user->lutTestUploads()
                ->get()
                ->each(fn (LutTestUpload $upload): bool => app(DeleteLutTestUpload::class)->delete($upload));
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->is_admin
            && $this->hasVerifiedEmail();
    }

    /**
     * @return HasMany<LutTestUpload, $this>
     */
    public function lutTestUploads(): HasMany
    {
        return $this->hasMany(LutTestUpload::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Entitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * @return HasMany<DownloadEvent, $this>
     */
    public function downloadEvents(): HasMany
    {
        return $this->hasMany(DownloadEvent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
