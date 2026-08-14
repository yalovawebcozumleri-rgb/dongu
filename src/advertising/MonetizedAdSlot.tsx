import React, { useEffect, useState } from 'react';
import { Platform, StyleSheet, useWindowDimensions, View, ViewStyle } from 'react-native';
import GoogleNativeAdCard from './GoogleNativeAdCard';
import { countAdvertisementSlots } from './listSlots';
import SponsoredCard from './SponsoredCard';
import { AdvertisementPlacement } from './types';
import { useAdvertisements } from './useAdvertisements';
import { useNativeAdSessionKey, useNativeAdSessionPreload } from './useNativeAdSession';

export default function MonetizedAdSlot({
  placement,
  token,
  slotIndex = 1,
  itemCount,
  style,
  sessionKey,
}: {
  placement: AdvertisementPlacement;
  token?: string | null;
  slotIndex?: number;
  itemCount?: number;
  style?: ViewStyle;
  sessionKey?: string;
}) {
  const { width: screenWidth } = useWindowDimensions();
  const collection = useAdvertisements(placement, token);
  const effectiveSessionKey = useNativeAdSessionKey(placement, 0, sessionKey);
  const [googleUnavailable, setGoogleUnavailable] = useState(false);

  useEffect(() => setGoogleUnavailable(false), [effectiveSessionKey]);

  const countFromRule = countAdvertisementSlots(itemCount ?? 0, collection?.meta);
  const totalSlots = Math.max(slotIndex, countFromRule || (itemCount === undefined ? 1 : 0));
  useNativeAdSessionPreload(effectiveSessionKey, collection, itemCount, totalSlots);

  if (!collection || !collection.meta.enabled || (itemCount !== undefined && itemCount < collection.meta.minItems)) return null;
  const directAd = collection.data.length ? collection.data[(slotIndex - 1) % collection.data.length] : null;
  const unitId = Platform.OS === 'ios' ? collection.meta.adMobIosUnitId : collection.meta.adMobAndroidUnitId;
  const source = collection.meta.sourceOrder.find(candidate => candidate === 'direct'
    ? !!directAd
    : candidate === 'admob'
      ? !!unitId && !googleUnavailable
      : false);
  if (!source) return null;

  const containerStyle = [local.slot, { width: Math.min(screenWidth - 40, 560) }, style];
  if (source === 'direct' && directAd) {
    return (
      <View style={containerStyle}>
        <SponsoredCard advertisement={directAd} placement={placement} slotIndex={slotIndex} token={token} />
      </View>
    );
  }

  return (
    <GoogleNativeAdCard
      unitId={unitId}
      sessionKey={effectiveSessionKey}
      slotIndex={slotIndex}
      totalSlots={totalSlots}
      containerStyle={containerStyle}
      onUnavailable={() => setGoogleUnavailable(true)}
    />
  );
}

const local = StyleSheet.create({ slot: { alignSelf: 'center', marginVertical: 10 } });
