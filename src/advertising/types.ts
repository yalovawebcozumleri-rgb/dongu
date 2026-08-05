export type AdvertisementPlacement = 'home_feed' | 'leaderboard' | 'listing_detail';

export type Advertisement = {
  id: number;
  sponsorName: string;
  headline: string;
  body: string;
  ctaLabel: string | null;
  targetUrl: string | null;
  backgroundColor: string;
  format: 'native' | 'image' | 'compact';
  imageUrl: string | null;
};

export type AdvertisementCollectionResponse = {
  data: Advertisement[];
  meta: { placement: AdvertisementPlacement; firstAfter: number; repeatEvery: number; maxPerSession: number; minItems: number };
};