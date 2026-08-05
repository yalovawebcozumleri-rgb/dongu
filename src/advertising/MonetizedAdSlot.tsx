import React, { useState } from 'react';
import { View, ViewStyle } from 'react-native';
import GoogleNativeAdCard from './GoogleNativeAdCard';
import HouseAdCard from './HouseAdCard';
import SponsoredCard from './SponsoredCard';
import { AdvertisementPlacement } from './types';
import { useAdvertisements } from './useAdvertisements';

export default function MonetizedAdSlot({ placement, token, slotIndex = 1, compact = false, style }: {
  placement: AdvertisementPlacement; token?: string | null; slotIndex?: number; compact?: boolean; style?: ViewStyle;
}) {
  const collection = useAdvertisements(placement, token);
  const [googleUnavailable, setGoogleUnavailable] = useState(false);
  if (!collection) return null;
  const directAd = collection.data.length ? collection.data[(slotIndex - 1) % collection.data.length] : null;
  return <View style={style}>
    {directAd
      ? <SponsoredCard advertisement={directAd} placement={placement} slotIndex={slotIndex} token={token} />
      : googleUnavailable
        ? <HouseAdCard horizontalMargin={placement === 'leaderboard' ? 10 : 30} />
        : <GoogleNativeAdCard compact={compact} onUnavailable={() => setGoogleUnavailable(true)} />}
  </View>;
}
