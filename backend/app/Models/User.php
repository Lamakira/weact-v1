<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property int $id
 * @property string $email
 * @property string|null $password
 * @property string|null $google_id
 * @property \Illuminate\Support\Carbon|null $google_linked_at
 * @property bool $is_active
 * @property string|null $userable_type
 * @property int|null $userable_id
 * @property \Illuminate\Support\Carbon|null $consent_given_at
 * @property string|null $consent_version
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Face|Producer|null $userable
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately WITHOUT `google_id`: User::create/update is called with
     * request-derived arrays in UserDataController and EmailChangeController, and
     * keeping the identity column out of mass assignment removes a whole class of
     * "attach an arbitrary Google identity" bug. Assign it explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'pending_email',
        'password',
        'userable_type',
        'userable_id',
        'is_active',
        'consent_given_at',
        'consent_ip',
        'consent_version',
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
            'consent_given_at' => 'datetime',
            'google_linked_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the owning userable model (Face or Producer).
     */
    public function userable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all messages sent by this user.
     */
    public function sentMessages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        // Sent after the response (not queued): keeps the response time independent of
        // whether the account exists, without writing the plaintext reset token to `jobs`.
        dispatch(fn () => $this->notify(new ResetPasswordNotification($token)))->afterResponse();
    }

    /**
     * Get all bookings where this user is the Face.
     */
    public function bookingsAsFace(): HasMany
    {
        return $this->hasMany(Booking::class, 'face_id');
    }

    /**
     * Get all bookings where this user is the Producer.
     */
    public function bookingsAsProducer(): HasMany
    {
        return $this->hasMany(Booking::class, 'producer_id');
    }

    /**
     * Get all wallet transactions for this user.
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Get all ratings given by this user.
     */
    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    /**
     * Check if the user's email is verified.
     */
    public function isEmailVerified(): bool
    {
        return $this->hasVerifiedEmail();
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * First-send flows (registration): the verification mail goes out after the response
     * — registration does not wait for SMTP — and is never queued, so the signed link is
     * not written to `jobs`. A mail failure must neither reach terminate() nor fail the
     * signup. The resend endpoint keeps the synchronous method above so a failure is
     * surfaced and the user can retry.
     */
    public function sendEmailVerificationNotificationAfterResponse(): void
    {
        dispatch(function (): void {
            try {
                $this->notify(new VerifyEmailNotification);
            } catch (\Throwable $e) {
                Log::warning('Failed to send verification email: '.$e->getMessage(), [
                    'user_id' => $this->getKey(),
                ]);
            }
        })->afterResponse();
    }
}
