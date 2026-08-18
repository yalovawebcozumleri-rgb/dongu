<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advertisement extends Model
{
    public const PLACEMENT_HOME_FEED = 'home_feed';
    public const PLACEMENT_LEADERBOARD = 'leaderboard';
    public const PLACEMENT_LISTING_DETAIL = 'listing_detail';
    public const PLACEMENT_FAVORITES = 'favorites';
    public const PLACEMENT_PUBLIC_PROFILE = 'public_profile';
    public const PLACEMENT_MY_LISTINGS = 'my_listings';
    public const PLACEMENT_PURCHASE_REQUESTS = 'purchase_requests';
    public const PLACEMENT_MESSAGES_LIST = 'messages_list';
    public const PLACEMENT_TRANSACTION_HISTORY = 'transaction_history';
    public const PLACEMENT_TRANSACTION_DETAIL = 'transaction_detail';
    public const PLACEMENT_NOTIFICATIONS = 'notifications';
    public const PLACEMENT_PROFILE_HOME = 'profile_home';
    public const PLACEMENT_USAGE_LIMITS = 'usage_limits';

    public const FORMAT_NATIVE = 'native';
    public const FORMAT_IMAGE = 'image';
    public const FORMAT_COMPACT = 'compact';
    public const FORMAT_BANNER = 'banner';
    public const FORMATS = [self::FORMAT_NATIVE, self::FORMAT_IMAGE, self::FORMAT_COMPACT, self::FORMAT_BANNER];

    public const PLACEMENTS = [self::PLACEMENT_HOME_FEED, self::PLACEMENT_LEADERBOARD, self::PLACEMENT_LISTING_DETAIL, self::PLACEMENT_FAVORITES, self::PLACEMENT_PUBLIC_PROFILE, self::PLACEMENT_MY_LISTINGS, self::PLACEMENT_PURCHASE_REQUESTS, self::PLACEMENT_MESSAGES_LIST, self::PLACEMENT_TRANSACTION_HISTORY, self::PLACEMENT_TRANSACTION_DETAIL, self::PLACEMENT_NOTIFICATIONS, self::PLACEMENT_PROFILE_HOME, self::PLACEMENT_USAGE_LIMITS];

    public const PLACEMENT_LABELS = [
        self::PLACEMENT_HOME_FEED => 'Ana sayfa', self::PLACEMENT_LEADERBOARD => 'Döngü sıralaması', self::PLACEMENT_LISTING_DETAIL => 'İlan detayı', self::PLACEMENT_FAVORITES => 'Favoriler', self::PLACEMENT_PUBLIC_PROFILE => 'Kullanıcı profili', self::PLACEMENT_MY_LISTINGS => 'İlanlarım', self::PLACEMENT_PURCHASE_REQUESTS => 'Alım taleplerim', self::PLACEMENT_MESSAGES_LIST => 'Mesajlar', self::PLACEMENT_TRANSACTION_HISTORY => 'İşlem geçmişi', self::PLACEMENT_TRANSACTION_DETAIL => 'İşlem detayı', self::PLACEMENT_NOTIFICATIONS => 'Bildirimler', self::PLACEMENT_PROFILE_HOME => 'Profil', self::PLACEMENT_USAGE_LIMITS => 'Limitlerim',
    ];

    public const SPONSORED_PLACEMENT_HINTS = [
        self::PLACEMENT_HOME_FEED => 'Konum kartından sonra, ilan listesinden önce.',
        self::PLACEMENT_LEADERBOARD => 'İlk üç sıralama kartından sonra.',
        self::PLACEMENT_LISTING_DETAIL => 'Ambalaj ve fiyat bölümünden sonra, teslimat bilgilerinden önce.',
        self::PLACEMENT_FAVORITES => 'Sayfa özetinden sonra, favori listesinden önce.',
        self::PLACEMENT_PUBLIC_PROFILE => 'Profil özetinden sonra, aktif ilanlardan önce.',
        self::PLACEMENT_MY_LISTINGS => 'Sayfa başlığı ve filtrelerden sonra, ilan listesinden önce.',
        self::PLACEMENT_PURCHASE_REQUESTS => 'Sayfa başlığı ve filtrelerden sonra, talep listesinden önce.',
        self::PLACEMENT_MESSAGES_LIST => 'Sayfa başlığından sonra, görüşme listesinden önce.',
        self::PLACEMENT_TRANSACTION_HISTORY => 'Sayfa başlığı ve filtrelerden sonra, işlem listesinden önce.',
        self::PLACEMENT_TRANSACTION_DETAIL => 'Durum özetinden sonra, işlem ayrıntılarından önce.',
        self::PLACEMENT_NOTIFICATIONS => 'Sayfa başlığı ve filtrelerden sonra, bildirim listesinden önce.',
        self::PLACEMENT_PROFILE_HOME => 'Hesap özetinden sonra, profil menüsünden önce.',
        self::PLACEMENT_USAGE_LIMITS => 'Kullanım özeti kartlarından sonra.',
    ];
    protected $fillable = ['placement', 'format', 'sponsor_name', 'headline', 'body', 'cta_label', 'target_url', 'background_color', 'image_path', 'android_enabled', 'ios_enabled', 'is_active', 'starts_at', 'ends_at', 'priority'];

    protected function casts(): array
    {
        return ['android_enabled' => 'boolean', 'ios_enabled' => 'boolean', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'priority' => 'integer'];
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function placements(): HasMany { return $this->hasMany(AdvertisementPlacement::class); }
    public function impressions(): HasMany { return $this->hasMany(AdvertisementImpression::class); }
}
