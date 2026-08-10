import React, { useEffect, useState } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import { googleAds, initializeGoogleAds, nativeUnitId } from './googleMobileAds';

export default function GoogleNativeAdCard({ unitId: configuredUnitId, onUnavailable }: { unitId?: string | null; onUnavailable: () => void }) {
  const [nativeAd, setNativeAd] = useState<any>(null);
  const module = googleAds();
  const unitId = nativeUnitId(configuredUnitId);
  useEffect(() => {
    let active = true; let loadedAd: any = null;
    if (!module || !unitId) { onUnavailable(); return; }
    void initializeGoogleAds().then(ready => { if (!ready) throw new Error('Ad consent is unavailable'); return module.NativeAd.createForAdRequest(unitId, { requestNonPersonalizedAdsOnly: true }); })
      .then(ad => { if (!active) return ad.destroy(); loadedAd = ad; setNativeAd(ad); }).catch(onUnavailable);
    return () => { active = false; loadedAd?.destroy(); };
  }, [module, unitId]);
  if (!module || !nativeAd) return null;
  const { NativeAdView, NativeAsset, NativeAssetType, NativeMediaView } = module;
  return <NativeAdView nativeAd={nativeAd} style={local.card}>
    <View style={local.labelRow}><Text style={local.label}>REKLAM</Text><Text style={local.advertiser}>{nativeAd.advertiser || 'Sponsorlu'}</Text></View>
    <View style={local.main}>{!!nativeAd.icon?.url && <NativeAsset assetType={NativeAssetType.ICON}><Image source={{ uri: nativeAd.icon.url }} style={local.icon} /></NativeAsset>}<View style={local.copy}><NativeAsset assetType={NativeAssetType.HEADLINE}><Text style={local.headline} numberOfLines={2}>{nativeAd.headline}</Text></NativeAsset>{!!nativeAd.body && <NativeAsset assetType={NativeAssetType.BODY}><Text style={local.body} numberOfLines={2}>{nativeAd.body}</Text></NativeAsset>}</View></View>
    {nativeAd.mediaContent && <NativeMediaView style={local.media} resizeMode="cover" />}
    {!!nativeAd.callToAction && <NativeAsset assetType={NativeAssetType.CALL_TO_ACTION}><View style={local.button}><Text style={local.buttonText}>{nativeAd.callToAction}</Text></View></NativeAsset>}
  </NativeAdView>;
}
const local = StyleSheet.create({ card: { marginHorizontal: 30, marginVertical: 10, minHeight: 170, padding: 16, borderRadius: 24, backgroundColor: '#FFFFFF', borderWidth: 1, borderColor: '#DFE6E0' }, labelRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 9 }, label: { fontSize: 10, fontWeight: '900', color: '#647168', borderWidth: 1, borderColor: '#B9C4BB', borderRadius: 4, paddingHorizontal: 5, paddingVertical: 2 }, advertiser: { fontSize: 11, fontWeight: '700', color: '#647168' }, main: { flexDirection: 'row', gap: 11 }, icon: { width: 48, height: 48, borderRadius: 12 }, copy: { flex: 1 }, headline: { color: '#172019', fontSize: 16, lineHeight: 21, fontWeight: '900' }, body: { color: '#667169', fontSize: 13, lineHeight: 18, marginTop: 4 }, media: { height: 120, marginTop: 12, borderRadius: 15, overflow: 'hidden' }, button: { alignSelf: 'flex-end', marginTop: 12, borderRadius: 12, backgroundColor: '#147A49', paddingHorizontal: 18, paddingVertical: 10 }, buttonText: { color: '#FFFFFF', fontSize: 13, fontWeight: '900' } });
