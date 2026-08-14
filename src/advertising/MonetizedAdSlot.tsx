import React, { useState } from 'react';
import { Platform, StyleSheet, useWindowDimensions, View, ViewStyle } from 'react-native';
import GoogleNativeAdCard from './GoogleNativeAdCard';
import SponsoredCard from './SponsoredCard';
import { AdvertisementPlacement } from './types';
import { useAdvertisements } from './useAdvertisements';

export default function MonetizedAdSlot({ placement, token, slotIndex = 1, itemCount, style, active = true }: { placement: AdvertisementPlacement; token?: string | null; slotIndex?: number; itemCount?: number; style?: ViewStyle; active?: boolean }) {
  const { width: screenWidth } = useWindowDimensions();
  const collection = useAdvertisements(placement, token);
  const [googleUnavailable, setGoogleUnavailable] = useState(false);
  if (!collection || !collection.meta.enabled || (itemCount !== undefined && itemCount < collection.meta.minItems)) return null;
  const directAd = collection.data.length ? collection.data[(slotIndex - 1) % collection.data.length] : null;
  const unitId = Platform.OS === 'ios' ? collection.meta.adMobIosUnitId : collection.meta.adMobAndroidUnitId;
  const source = collection.meta.sourceOrder.find(candidate => candidate === 'direct' ? !!directAd : candidate === 'admob' ? !!unitId && !googleUnavailable : false);
  if (!source) return null;
  return <View style={[local.slot, { width: Math.min(screenWidth - 40, 560) }, style]}>
    {source === 'direct' && directAd ? <SponsoredCard advertisement={directAd} placement={placement} slotIndex={slotIndex} token={token} /> : null}
    {source === 'admob' ? <GoogleNativeAdCard unitId={unitId} active={active} onUnavailable={() => setGoogleUnavailable(true)} /> : null}
  </View>;
}

const local = StyleSheet.create({ slot: { alignSelf: 'center', marginVertical: 10 } });
