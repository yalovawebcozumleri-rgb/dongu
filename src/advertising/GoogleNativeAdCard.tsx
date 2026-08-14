import React, { useEffect, useRef, useState } from 'react';
import { AppState, Image, InteractionManager, StyleSheet, Text, View } from 'react-native';
import { googleAds, initializeGoogleAds, nativeUnitId } from './googleMobileAds';
import { acquireNativeAd, NativeAdLease } from './nativeAdManager';

const LOAD_SETTLE_DELAY_MS = 350;
const AD_REFRESH_MS = 55 * 60 * 1000;

export default function GoogleNativeAdCard({ unitId: configuredUnitId, onUnavailable, active = true }: { unitId?: string | null; onUnavailable: () => void; active?: boolean }) {
  const [nativeAd, setNativeAd] = useState<any>(null);
  const [appIsActive, setAppIsActive] = useState(AppState.currentState === 'active');
  const [reloadKey, setReloadKey] = useState(0);
  const unavailableRef = useRef(onUnavailable);
  unavailableRef.current = onUnavailable;
  const module = googleAds();
  const unitId = nativeUnitId(configuredUnitId);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', state => setAppIsActive(state === 'active'));
    return () => subscription.remove();
  }, []);

  useEffect(() => {
    let mounted = true;
    let settleTimer: ReturnType<typeof setTimeout> | null = null;
    let refreshTimer: ReturnType<typeof setTimeout> | null = null;
    let lease: NativeAdLease | null = null;
    const interaction = InteractionManager.runAfterInteractions(() => {
      settleTimer = setTimeout(() => {
        if (!mounted || !active || !appIsActive || !module || !unitId) return;
        void initializeGoogleAds(unitId)
          .then(ready => {
            if (!ready) throw new Error('Ad consent is unavailable');
            if (!mounted) return null;
            lease = acquireNativeAd(module, unitId);
            return lease.promise;
          })
          .then(ad => {
            if (!mounted || !ad) return;
            setNativeAd(ad);
            refreshTimer = setTimeout(() => setReloadKey(current => current + 1), AD_REFRESH_MS);
          })
          .catch(() => {
            if (mounted) unavailableRef.current();
          });
      }, LOAD_SETTLE_DELAY_MS);
    });

    return () => {
      mounted = false;
      interaction.cancel();
      if (settleTimer) clearTimeout(settleTimer);
      if (refreshTimer) clearTimeout(refreshTimer);
      lease?.release();
      setNativeAd(null);
    };
  }, [active, appIsActive, module, reloadKey, unitId]);

  if (!active || !module || !nativeAd) return null;
  const { NativeAdView, NativeAsset, NativeAssetType, NativeMediaView } = module;
  return <NativeAdView nativeAd={nativeAd} style={local.nativeRoot}>
    <View style={local.card} collapsable={false}>
      <View style={local.labelRow}>
        <Text style={local.label}>REKLAM</Text>
        {!!nativeAd.advertiser && <NativeAsset assetType={NativeAssetType.ADVERTISER}><Text style={local.advertiser} numberOfLines={1}>{nativeAd.advertiser}</Text></NativeAsset>}
      </View>
      <View style={local.main}>{!!nativeAd.icon?.url && <NativeAsset assetType={NativeAssetType.ICON}><Image source={{ uri: nativeAd.icon.url }} style={local.icon} /></NativeAsset>}<View style={local.copy}><NativeAsset assetType={NativeAssetType.HEADLINE}><Text style={local.headline} numberOfLines={2}>{nativeAd.headline}</Text></NativeAsset>{!!nativeAd.body && <NativeAsset assetType={NativeAssetType.BODY}><Text style={local.body} numberOfLines={2}>{nativeAd.body}</Text></NativeAsset>}</View></View>
      {nativeAd.mediaContent && <NativeMediaView style={local.media} resizeMode="cover" />}
      {!!nativeAd.callToAction && <NativeAsset assetType={NativeAssetType.CALL_TO_ACTION}><Text style={local.button}>{nativeAd.callToAction}</Text></NativeAsset>}
    </View>
  </NativeAdView>;
}
const local = StyleSheet.create({ nativeRoot: { width: '100%' }, card: { width: '100%', minHeight: 170, padding: 16, borderRadius: 24, backgroundColor: '#FFFFFF', borderWidth: 1, borderColor: '#DFE6E0' }, labelRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 9, paddingRight: 36 }, label: { minWidth: 15, minHeight: 15, fontSize: 10, lineHeight: 15, fontWeight: '900', color: '#647168', borderWidth: 1, borderColor: '#B9C4BB', borderRadius: 4, paddingHorizontal: 5, paddingVertical: 1 }, advertiser: { flexShrink: 1, marginLeft: 10, fontSize: 11, lineHeight: 16, fontWeight: '700', color: '#647168' }, main: { flexDirection: 'row', gap: 11 }, icon: { width: 48, height: 48, borderRadius: 12 }, copy: { flex: 1 }, headline: { color: '#172019', fontSize: 16, lineHeight: 21, fontWeight: '900' }, body: { color: '#667169', fontSize: 13, lineHeight: 18, marginTop: 4 }, media: { height: 120, marginTop: 12, borderRadius: 15, overflow: 'hidden' }, button: { alignSelf: 'flex-end', marginTop: 12, borderRadius: 12, overflow: 'hidden', backgroundColor: '#147A49', paddingHorizontal: 18, paddingVertical: 10, color: '#FFFFFF', fontSize: 13, lineHeight: 18, fontWeight: '900' } });
