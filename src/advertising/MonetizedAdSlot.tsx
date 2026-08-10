import React, { useState } from 'react';
import { Platform, View, ViewStyle } from 'react-native';
import GoogleNativeAdCard from './GoogleNativeAdCard';
import HouseAdCard from './HouseAdCard';
import SponsoredCard from './SponsoredCard';
import { AdvertisementPlacement } from './types';
import { useAdvertisements } from './useAdvertisements';

export default function MonetizedAdSlot({ placement, token, slotIndex = 1, itemCount, style }: { placement: AdvertisementPlacement; token?: string | null; slotIndex?: number; itemCount?: number; compact?: boolean; style?: ViewStyle; }) {
  const collection = useAdvertisements(placement, token);
  const [googleUnavailable, setGoogleUnavailable] = useState(false);
  if (!collection || !collection.meta.enabled || (itemCount !== undefined && itemCount < collection.meta.minItems)) return null;
  const directAd = collection.data.length ? collection.data[(slotIndex - 1) % collection.data.length] : null;
  const unitId = Platform.OS === 'ios' ? collection.meta.adMobIosUnitId : collection.meta.adMobAndroidUnitId;
  const source = collection.meta.sourceOrder.find(candidate => candidate === 'direct' ? !!directAd : candidate === 'admob' ? !!unitId && !googleUnavailable : candidate === 'house');
  if (!source) return null;
  return <View style={style}>
    {source === 'direct' && directAd ? <SponsoredCard advertisement={directAd} placement={placement} slotIndex={slotIndex} token={token} /> : null}
    {source === 'admob' ? <GoogleNativeAdCard unitId={unitId} onUnavailable={() => setGoogleUnavailable(true)} /> : null}
    {source === 'house' ? <HouseAdCard /> : null}
  </View>;
}
