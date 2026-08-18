import type { AdvertisementPlacement } from './types';

export type SponsoredBanner = {
  id: number;
  sponsorName: string;
  headline: string;
  body: string;
  ctaLabel: string | null;
  targetUrl: string | null;
  imageUrl: string;
};

export type SponsoredBannerResponse = { data: SponsoredBanner | null };
export type SponsoredBannerPlacement = AdvertisementPlacement;