import { useEffect, useMemo, useRef, useState } from 'react';
import { Platform } from 'react-native';
import { adEnvironmentForUnitId, googleAds, initializeGoogleAds, nativeUnitId } from './googleMobileAds';
import { countAdvertisementSlots } from './listSlots';
import { prepareNativeAdSession, releaseNativeAdSession } from './nativeAdManager';
import { AdvertisementCollectionResponse, AdvertisementPlacement } from './types';

const SESSION_TTL_MS = 55 * 60 * 1000;
let nextSessionId = 1;

export function useNativeAdSessionKey(
  placement: AdvertisementPlacement,
  generation = 0,
  externalSessionKey?: string,
) {
  const instanceId = useRef(nextSessionId++).current;
  const [ttlGeneration, setTtlGeneration] = useState(0);

  useEffect(() => {
    if (externalSessionKey) return undefined;
    const timer = setTimeout(() => setTtlGeneration(current => current + 1), SESSION_TTL_MS);
    return () => clearTimeout(timer);
  }, [externalSessionKey, generation, placement, ttlGeneration]);

  const ownedSessionKey = useMemo(
    () => `native-${placement}-${instanceId}-${generation}-${ttlGeneration}`,
    [generation, instanceId, placement, ttlGeneration],
  );
  const sessionKey = externalSessionKey ?? ownedSessionKey;

  useEffect(() => {
    if (externalSessionKey) return undefined;
    return () => releaseNativeAdSession(sessionKey);
  }, [externalSessionKey, sessionKey]);

  return sessionKey;
}

export function useNativeAdSessionPreload(
  sessionKey: string,
  collection: AdvertisementCollectionResponse | null,
  itemCount?: number,
  minimumSlots = 0,
) {
  const platform = Platform.OS === 'ios' ? 'ios' : 'android';
  const module = googleAds();
  const remoteUnitId = platform === 'ios'
    ? collection?.meta.adMobIosUnitId
    : collection?.meta.adMobAndroidUnitId;
  const unitId = nativeUnitId(remoteUnitId);
  const directAd = collection?.data.length ? collection.data[0] : null;
  const source = collection?.meta.sourceOrder.find(candidate => candidate === 'direct'
    ? !!directAd
    : candidate === 'admob'
      ? !!unitId
      : false);
  const ruleSlots = countAdvertisementSlots(itemCount ?? 0, collection?.meta);
  const slotCount = Math.max(minimumSlots, ruleSlots);

  useEffect(() => {
    let active = true;
    if (!collection?.meta.enabled || source !== 'admob' || !module || !unitId || slotCount <= 0) {
      return () => { active = false; };
    }
    void initializeGoogleAds({ environment: adEnvironmentForUnitId(unitId), format: 'native', placement: sessionKey, unitId }).then(ready => {
      if (active && ready) prepareNativeAdSession(module, unitId, sessionKey, slotCount);
    });
    return () => { active = false; };
  }, [collection?.meta.enabled, module, sessionKey, slotCount, source, unitId]);
}
