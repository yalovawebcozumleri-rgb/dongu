import { useEffect, useState } from 'react';
import { Image, Platform } from 'react-native';
import { apiRequest } from '../lib/api';
import type { SponsoredBanner, SponsoredBannerPlacement, SponsoredBannerResponse } from './sponsoredBannerTypes';

const cache = new Map<string, SponsoredBanner | null>();
const pending = new Map<string, Promise<SponsoredBanner | null>>();

async function loadBanner(placement: SponsoredBannerPlacement, sessionKey: string, token?: string | null): Promise<SponsoredBanner | null> {
  const platform = Platform.OS === 'ios' ? 'ios' : 'android';
  const cacheKey = `${platform}:${placement}:${sessionKey}`;
  if (cache.has(cacheKey)) return cache.get(cacheKey) ?? null;

  let request = pending.get(cacheKey);
  if (!request) {
    request = apiRequest<SponsoredBannerResponse>(`/sponsored-banners?placement=${encodeURIComponent(placement)}&platform=${platform}&sessionKey=${encodeURIComponent(sessionKey)}`, token ? { token } : {})
      .then(async response => {
        if (!response.data?.imageUrl) return null;
        const ready = await Image.prefetch(response.data.imageUrl).catch(() => false);
        return ready ? response.data : null;
      })
      .catch(() => null)
      .then(banner => {
        cache.set(cacheKey, banner);
        pending.delete(cacheKey);
        return banner;
      });
    pending.set(cacheKey, request);
  }
  return request;
}

export function useSponsoredBanner(placement: SponsoredBannerPlacement, sessionKey: string, token?: string | null) {
  const [banner, setBanner] = useState<SponsoredBanner | null | undefined>(() => {
    const platform = Platform.OS === 'ios' ? 'ios' : 'android';
    return cache.get(`${platform}:${placement}:${sessionKey}`);
  });

  useEffect(() => {
    let active = true;
    void loadBanner(placement, sessionKey, token).then(value => { if (active) setBanner(value); });
    return () => { active = false; };
  }, [placement, sessionKey, token]);

  return banner;
}