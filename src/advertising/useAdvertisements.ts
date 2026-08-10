import { useEffect, useState } from 'react';
import { apiRequest } from '../lib/api';
import { AdvertisementCollectionResponse, AdvertisementPlacement } from './types';

const CACHE_TTL_MS = 15_000;
const cache = new Map<AdvertisementPlacement, { response: AdvertisementCollectionResponse; storedAt: number }>();
const pending = new Map<AdvertisementPlacement, Promise<AdvertisementCollectionResponse>>();

export function useAdvertisements(placement: AdvertisementPlacement, token?: string | null) {
  const cachedAtStart = cache.get(placement);
  const [collection, setCollection] = useState<AdvertisementCollectionResponse | null>(() => cachedAtStart?.response ?? null);
  useEffect(() => {
    let active = true;
    const cached = cache.get(placement);
    if (cached && Date.now() - cached.storedAt < CACHE_TTL_MS) {
      setCollection(cached.response);
      return () => { active = false; };
    }
    if (cached) setCollection(cached.response);
    let request = pending.get(placement);
    if (!request) {
      request = apiRequest<AdvertisementCollectionResponse>(`/advertisements?placement=${placement}`, token ? { token } : {});
      pending.set(placement, request);
    }
    request.then(response => {
      cache.set(placement, { response, storedAt: Date.now() });
      if (active) setCollection(response);
    }).catch(() => {
      if (active) setCollection({ data: [], meta: { placement, enabled: false, sourceOrder: [], firstAfter: 0, repeatEvery: 0, maxPerSession: 0, minItems: 0, adMobAndroidUnitId: null, adMobIosUnitId: null } });
    }).finally(() => pending.delete(placement));
    return () => { active = false; };
  }, [placement, token]);
  return collection;
}
