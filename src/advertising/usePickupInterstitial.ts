import { useCallback, useEffect, useRef } from 'react';
import { googleAds, initializeGoogleAds, interstitialUnitId } from './googleMobileAds';

export function usePickupInterstitial(enabled: boolean) {
  const adRef = useRef<any>(null);
  const loadedRef = useRef(false);
  const cleanupRef = useRef<(() => void)[]>([]);

  const prepare = useCallback(() => {
    cleanupRef.current.forEach(cleanup => cleanup());
    cleanupRef.current = [];
    loadedRef.current = false;
    const module = googleAds();
    const unitId = interstitialUnitId();
    if (!enabled || !module || !unitId) return;
    void initializeGoogleAds().then(ready => {
      if (!ready) return;
      const ad = module.InterstitialAd.createForAdRequest(unitId, { requestNonPersonalizedAdsOnly: true });
      adRef.current = ad;
      cleanupRef.current = [
        ad.addAdEventListener(module.AdEventType.LOADED, () => { loadedRef.current = true; }),
        ad.addAdEventListener(module.AdEventType.CLOSED, prepare),
        ad.addAdEventListener(module.AdEventType.ERROR, () => { loadedRef.current = false; }),
      ];
      ad.load();
    }).catch(() => undefined);
  }, [enabled]);

  useEffect(() => {
    prepare();
    return () => { cleanupRef.current.forEach(cleanup => cleanup()); cleanupRef.current = []; };
  }, [prepare]);

  return useCallback(() => {
    if (!loadedRef.current || !adRef.current) return false;
    loadedRef.current = false;
    void adRef.current.show().catch(prepare);
    return true;
  }, [prepare]);
}
