export type AdvertisementPlacement = 'home_feed' | 'leaderboard' | 'listing_detail' | 'favorites' | 'public_profile' | 'my_listings' | 'purchase_requests' | 'messages_list' | 'transaction_history' | 'transaction_detail' | 'notifications' | 'profile_home' | 'usage_limits';
export type AdvertisementSource = 'direct' | 'admob';

export type Advertisement = { id: number; sponsorName: string; headline: string; body: string; ctaLabel: string | null; targetUrl: string | null; backgroundColor: string; format: 'native'; imageUrl: string | null; };

export type AdvertisementCollectionResponse = {
  data: Advertisement[];
  meta: { placement: AdvertisementPlacement; enabled: boolean; sourceOrder: AdvertisementSource[]; firstAfter: number; repeatEvery: number; maxPerSession: number; minItems: number; adMobAndroidUnitId: string | null; adMobIosUnitId: string | null; androidEnabled?: boolean; iosEnabled?: boolean; };
};
