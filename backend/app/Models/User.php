<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPPORTER = 'supporter';

    public const AVATAR_KEYS = [
        'avatar_01', 'avatar_02', 'avatar_03', 'avatar_04', 'avatar_05',
        'avatar_06', 'avatar_07', 'avatar_08', 'avatar_09', 'avatar_10',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar_path', 'avatar_key', 'status', 'role',
        'profile_completed_at', 'terms_accepted_at', 'terms_version', 'privacy_notice_acknowledged_at', 'privacy_notice_version', 'rating', 'rating_count',
        'completed_transactions', 'ranking_name_visible',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile_completed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_notice_acknowledged_at' => 'datetime',
            'rating' => 'decimal:2',
            'rating_count' => 'integer',
            'completed_transactions' => 'integer',
            'ranking_name_visible' => 'boolean',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'buyer_id');
    }

    public function saleRequests(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'seller_id');
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function moderationSanctions(): HasMany
    {
        return $this->hasMany(ModerationSanction::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(ListingFavorite::class);
    }

    public function blockedUsers(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function blockedByUsers(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_id');
    }
    public function supporterBusiness(): HasOne
    {
        return $this->hasOne(SupporterBusiness::class, 'owner_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSupporter(): bool
    {
        return $this->role === self::ROLE_SUPPORTER;
    }
    public function avatarReference(): ?string
    {
        return self::avatarReferenceFromKey($this->avatar_key);
    }

    public static function avatarReferenceFromKey(?string $key): ?string
    {
        return in_array($key, self::AVATAR_KEYS, true) ? 'preset://'.$key : null;
    }
}