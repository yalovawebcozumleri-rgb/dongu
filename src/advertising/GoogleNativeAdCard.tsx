import React, { useEffect, useRef, useState } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import type { StyleProp, ViewStyle } from 'react-native';
import { googleAds, initializeGoogleAds, nativeUnitId } from './googleMobileAds';
import { acquireNativeAd, NativeAdLease, peekNativeAd, prepareNativeAdSession } from './nativeAdManager';

type LoadedAd = { identity: string; ad: any | null };

export default function GoogleNativeAdCard({
  unitId: configuredUnitId,
  sessionKey,
  slotIndex,
  totalSlots,
  containerStyle,
  onUnavailable,
}: {
  unitId?: string | null;
  sessionKey: string;
  slotIndex: number;
  totalSlots: number;
  containerStyle?: StyleProp<ViewStyle>;
  onUnavailable: () => void;
}) {
  const unavailableRef = useRef(onUnavailable);
  unavailableRef.current = onUnavailable;
  const module = googleAds();
  const unitId = nativeUnitId(configuredUnitId);
  const identity = `${sessionKey}::${unitId ?? 'none'}::${slotIndex}`;
  const [loaded, setLoaded] = useState<LoadedAd>(() => ({
    identity,
    ad: module && unitId ? peekNativeAd(sessionKey, unitId, slotIndex) : null,
  }));

  useEffect(() => {
    let mounted = true;
    let lease: NativeAdLease | null = null;
    const cached = module && unitId ? peekNativeAd(sessionKey, unitId, slotIndex) : null;
    setLoaded({ identity, ad: cached });

    if (!module || !unitId) return () => { mounted = false; };
    void initializeGoogleAds(unitId)
      .then(ready => {
        if (!ready) throw new Error('Ad consent is unavailable');
        if (!mounted) return null;
        prepareNativeAdSession(module, unitId, sessionKey, totalSlots);
        lease = acquireNativeAd(module, unitId, sessionKey, slotIndex, 'visible');
        return lease.promise;
      })
      .then(ad => {
        if (mounted && ad) setLoaded({ identity, ad });
      })
      .catch(() => {
        if (mounted) unavailableRef.current();
      });

    return () => {
      mounted = false;
      lease?.release();
    };
  }, [identity, module, sessionKey, slotIndex, totalSlots, unitId]);

  const nativeAd = loaded.identity === identity
    ? loaded.ad
    : module && unitId
      ? peekNativeAd(sessionKey, unitId, slotIndex)
      : null;
  if (!module || !nativeAd) return null;

  const { NativeAdView, NativeAsset, NativeAssetType, NativeMediaView } = module;
  return (
    <View style={containerStyle}>
      <NativeAdView nativeAd={nativeAd} style={local.nativeRoot}>
        <View style={local.card} collapsable={false}>
          <View style={local.labelRow}>
            <Text style={local.label}>REKLAM</Text>
            {!!nativeAd.advertiser && (
              <NativeAsset assetType={NativeAssetType.ADVERTISER}>
                <Text style={local.advertiser} numberOfLines={1}>{nativeAd.advertiser}</Text>
              </NativeAsset>
            )}
          </View>
          <View style={local.main}>
            {!!nativeAd.icon?.url && (
              <NativeAsset assetType={NativeAssetType.ICON}>
                <Image source={{ uri: nativeAd.icon.url }} style={local.icon} />
              </NativeAsset>
            )}
            <View style={local.copy}>
              <NativeAsset assetType={NativeAssetType.HEADLINE}>
                <Text style={local.headline} numberOfLines={2}>{nativeAd.headline}</Text>
              </NativeAsset>
              {!!nativeAd.body && (
                <NativeAsset assetType={NativeAssetType.BODY}>
                  <Text style={local.body} numberOfLines={2}>{nativeAd.body}</Text>
                </NativeAsset>
              )}
            </View>
          </View>
          {nativeAd.mediaContent && <NativeMediaView style={local.media} resizeMode="cover" />}
          {!!nativeAd.callToAction && (
            <NativeAsset assetType={NativeAssetType.CALL_TO_ACTION}>
              <Text style={local.button}>{nativeAd.callToAction}</Text>
            </NativeAsset>
          )}
        </View>
      </NativeAdView>
    </View>
  );
}

const local = StyleSheet.create({
  nativeRoot: { width: '100%' },
  card: { width: '100%', minHeight: 170, padding: 16, borderRadius: 24, backgroundColor: '#FFFFFF', borderWidth: 1, borderColor: '#DFE6E0' },
  labelRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 9, paddingRight: 36 },
  label: { minWidth: 15, minHeight: 15, fontSize: 10, lineHeight: 15, fontWeight: '900', color: '#647168', borderWidth: 1, borderColor: '#B9C4BB', borderRadius: 4, paddingHorizontal: 5, paddingVertical: 1 },
  advertiser: { flexShrink: 1, marginLeft: 10, fontSize: 11, lineHeight: 16, fontWeight: '700', color: '#647168' },
  main: { flexDirection: 'row', gap: 11 },
  icon: { width: 48, height: 48, borderRadius: 12 },
  copy: { flex: 1 },
  headline: { color: '#172019', fontSize: 16, lineHeight: 21, fontWeight: '900' },
  body: { color: '#667169', fontSize: 13, lineHeight: 18, marginTop: 4 },
  media: { width: '100%', minWidth: 120, height: 180, minHeight: 120, marginTop: 12, borderRadius: 15, overflow: 'hidden' },
  button: { alignSelf: 'flex-end', marginTop: 12, borderRadius: 12, overflow: 'hidden', backgroundColor: '#147A49', paddingHorizontal: 18, paddingVertical: 10, color: '#FFFFFF', fontSize: 13, lineHeight: 18, fontWeight: '900' },
});
