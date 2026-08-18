import { useCallback, useEffect, useRef } from 'react';
import { adEnvironmentForUnitId, googleAds, initializeGoogleAds, interstitialUnitId } from './googleMobileAds';
import { reportAdDiagnostic } from './adDiagnostics';

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
    const diagnosticContext = {
      environment: adEnvironmentForUnitId(unitId),
      format: 'interstitial' as const,
      placement: 'pickup_request',
      unitId,
    };
    void initializeGoogleAds(diagnosticContext).then(ready => {
      if (!ready) return;
      const ad = module.InterstitialAd.createForAdRequest(unitId);
      adRef.current = ad;
      cleanupRef.current = [
        ad.addAdEventListener(module.AdEventType.LOADED, () => {
          loadedRef.current = true;
          if (showWhenLoaded) {
            loadedRef.current = false;
            void ad.show().catch(error => {
              reportAdDiagnostic('interstitial_show_failed', diagnosticContext, {}, error);
              prepare(remoteUnitId);
            });
          }
        }),
        ad.addAdEventListener(module.AdEventType.CLOSED, () => prepare(remoteUnitId)),
        ad.addAdEventListener(module.AdEventType.ERROR, error => {
          loadedRef.current = false;
          reportAdDiagnostic('interstitial_load_failed', diagnosticContext, {}, error);
        }),
      ];
      ad.load();
    }).catch(error => reportAdDiagnostic('interstitial_initialize_failed', diagnosticContext, {}, error));
  }, [enabled]);

  useEffect(() => {
    if (__DEV__) prepare();
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
    void adRef.current.show().catch((error: unknown) => {
      reportAdDiagnostic('interstitial_show_failed', {
        environment: adEnvironmentForUnitId(requestedUnitId),
        format: 'interstitial',
        placement: 'pickup_request',
        unitId: requestedUnitId,
      }, {}, error);
      prepare(remoteUnitId);
    });
    return true;
  }, [prepare]);
}
