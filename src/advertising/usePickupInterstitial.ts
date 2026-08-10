import { useCallback, useEffect, useRef } from 'react';
import { googleAds, initializeGoogleAds, interstitialUnitId } from './googleMobileAds';

export function usePickupInterstitial(enabled: boolean) {
  const adRef = useRef<any>(null);
  const loadedRef = useRef(false);
  const unitIdRef = useRef<string | null>(null);
  const cleanupRef = useRef<(() => void)[]>([]);

  const prepare = useCallback((remoteUnitId?: string | null, showWhenLoaded = false) => {
    cleanupRef.current.forEach(cleanup => cleanup());
    cleanupRef.current = [];
    loadedRef.current = false;
    const module = googleAds();
    const unitId = interstitialUnitId(remoteUnitId);
    if (!enabled || !module || !unitId) return;
    unitIdRef.current = unitId;
    void initializeGoogleAds().then(ready => {
      if (!ready) return;
      const ad = module.InterstitialAd.createForAdRequest(unitId, { requestNonPersonalizedAdsOnly: true });
      adRef.current = ad;
      cleanupRef.current = [
        ad.addAdEventListener(module.AdEventType.LOADED, () => {
          loadedRef.current = true;
          if (showWhenLoaded) {
            loadedRef.current = false;
            void ad.show().catch(() => prepare(remoteUnitId));
          }
        }),
        ad.addAdEventListener(module.AdEventType.CLOSED, () => prepare(remoteUnitId)),
        ad.addAdEventListener(module.AdEventType.ERROR, () => { loadedRef.current = false; }),
      ];
      ad.load();
    }).catch(() => undefined);
  }, [enabled]);

  useEffect(() => {
    prepare();
    return () => { cleanupRef.current.forEach(cleanup => cleanup()); cleanupRef.current = []; };
  }, [prepare]);

  return useCallback((remoteUnitId?: string | null) => {
    const requestedUnitId = interstitialUnitId(remoteUnitId);
    if (requestedUnitId && requestedUnitId !== unitIdRef.current) {
      prepare(remoteUnitId, true);
      return true;
    }
    if (!loadedRef.current || !adRef.current) return false;
    loadedRef.current = false;
    void adRef.current.show().catch(() => prepare(remoteUnitId));
    return true;
  }, [prepare]);
}
