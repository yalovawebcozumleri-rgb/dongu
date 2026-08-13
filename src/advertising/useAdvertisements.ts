import { useEffect, useState } from 'react';
import { Platform } from 'react-native';
import { apiRequest } from '../lib/api';
import { AdvertisementCollectionResponse, AdvertisementPlacement } from './types';

const CACHE_TTL_MS = 15_000;
const cache = new Map<string, { response: AdvertisementCollectionResponse; storedAt: number }>();
const pending = new Map<string, Promise<AdvertisementCollectionResponse>>();

export function useAdvertisements(placement: AdvertisementPlacement, token?: string | null) {
  const platform = Platform.OS === 'ios' ? 'ios' : 'android';
  const cacheKey = `${platform}:${placement}`;
  const cachedAtStart = cache.get(cacheKey);
  const [collection, setCollection] = useState<AdvertisementCollectionResponse | null>(() => cachedAtStart?.response ?? null);

  useEffect(() => {
    let active = true;
    const cached = cache.get(cacheKey);
    if (cached && Date.now() - cached.storedAt < CACHE_TTL_MS) {
      setCollection(cached.response);
      return () => { active = false; };
    }

    if (cached) setCollection(cached.response);

    let request = pending.get(cacheKey);
    if (!request) {
      request = apiRequest<AdvertisementCollectionResponse>(`/advertisements?placement=${encodeURIComponent(placement)}&platform=${platform}`, token ? { token } : {});
      pending.set(cacheKey, request);
    }

    request.then(response => {
      cache.set(cacheKey, { response, storedAt: Date.now() });
      if (active) setCollection(response);
    }).catch(() => {
      if (active) setCollection({ data: [], meta: { placement, enabled: false, sourceOrder: [], firstAfter: 0, repeatEvery: 0, maxPerSession: 0, minItems: 0, adMobAndroidUnitId: null, adMobIosUnitId: null, androidEnabled: false, iosEnabled: false } });
    }).finally(() => pending.delete(cacheKey));

    return () => { active = false; };
  }, [cacheKey, placement, platform, token]);

  return collection;
}