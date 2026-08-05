import { useEffect, useState } from 'react';
import { apiRequest } from '../lib/api';
import { AdvertisementCollectionResponse, AdvertisementPlacement } from './types';

const cache = new Map<AdvertisementPlacement, AdvertisementCollectionResponse>();
const pending = new Map<AdvertisementPlacement, Promise<AdvertisementCollectionResponse>>();

export function useAdvertisements(placement: AdvertisementPlacement, token?: string | null) {
  const [collection, setCollection] = useState<AdvertisementCollectionResponse | null>(() => cache.get(placement) ?? null);

  useEffect(() => {
    let active = true;
    const cached = cache.get(placement);
    if (cached) {
      setCollection(cached);
      return () => { active = false; };
    }
    let request = pending.get(placement);
    if (!request) {
      request = apiRequest<AdvertisementCollectionResponse>(`/advertisements?placement=${placement}`, token ? { token } : {});
      pending.set(placement, request);
    }
    request.then(response => {
      cache.set(placement, response);
      if (active) setCollection(response);
    }).catch(() => {
      if (active) setCollection({ data: [], meta: { placement, firstAfter: 0, repeatEvery: 0, maxPerSession: 0, minItems: 0 } });
    }).finally(() => pending.delete(placement));
    return () => { active = false; };
  }, [placement, token]);

  return collection;
}
