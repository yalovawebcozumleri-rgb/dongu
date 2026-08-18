import React, { memo, useEffect, useRef, useState } from 'react';
import { AppState, Image, Linking, Platform, Pressable, StyleSheet, Text, useWindowDimensions, View } from 'react-native';
import { apiRequest } from '../lib/api';
import type { SponsoredBannerPlacement } from './sponsoredBannerTypes';
import { useSponsoredBanner } from './useSponsoredBanner';

function createSessionKey(placement: string) {
  return `sponsor-${placement}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

function SponsoredBannerSlot({ placement, token, sessionKey }: { placement: SponsoredBannerPlacement; token?: string | null; sessionKey?: string }) {
  const internalSessionKey = useRef(sessionKey || createSessionKey(placement)).current;
  const banner = useSponsoredBanner(placement, internalSessionKey, token);
  const [imageFailed, setImageFailed] = useState(false);
  const viewRef = useRef<View>(null);
  const impressionRecorded = useRef(false);
  const visibleSince = useRef<number | null>(null);
  const { height: windowHeight, width: windowWidth } = useWindowDimensions();
  const platform = Platform.OS === 'ios' ? 'ios' : 'android';

  useEffect(() => {
    setImageFailed(false);
    impressionRecorded.current = false;
    visibleSince.current = null;
  }, [banner?.id, internalSessionKey]);

  useEffect(() => {
    if (!banner || imageFailed || impressionRecorded.current) return;
    const interval = setInterval(() => {
      if (AppState.currentState !== 'active') {
        visibleSince.current = null;
        return;
      }
      viewRef.current?.measureInWindow((_x, y, _width, height) => {
        if (height <= 0) return;
        const visibleHeight = Math.max(0, Math.min(y + height, windowHeight) - Math.max(y, 0));
        if (visibleHeight / height < 0.5) {
          visibleSince.current = null;
          return;
        }
        visibleSince.current ??= Date.now();
        if (Date.now() - visibleSince.current < 1000 || impressionRecorded.current) return;
        impressionRecorded.current = true;
        void apiRequest(`/sponsored-banners/${banner.id}/impressions`, {
          method: 'POST', token, body: { placement, platform, sessionKey: internalSessionKey },
        }).catch(() => undefined);
      });
    }, 300);
    return () => clearInterval(interval);
  }, [banner, imageFailed, internalSessionKey, placement, platform, token, windowHeight]);

  if (!banner || imageFailed) return null;

  const open = () => {
    if (!banner.targetUrl) return;
    void apiRequest(`/sponsored-banners/${banner.id}/clicks`, {
      method: 'POST', token, body: { placement, platform, sessionKey: internalSessionKey },
    }).catch(() => undefined);
    void Linking.openURL(banner.targetUrl).catch(() => undefined);
  };

  const content = (
    <View ref={viewRef} collapsable={false} style={[local.banner, { width: Math.min(windowWidth - 40, 560) }]}>
      <Image source={{ uri: banner.imageUrl }} resizeMode="cover" style={StyleSheet.absoluteFill} onError={() => setImageFailed(true)} accessibilityIgnoresInvertColors />
      <View style={local.label}><Text style={local.labelText}>SPONSORLU</Text></View>
    </View>
  );

  if (!banner.targetUrl) return <View accessible accessibilityLabel={`Sponsorlu içerik. ${banner.sponsorName}. ${banner.headline}. ${banner.body}`} style={local.wrap}>{content}</View>;
  return <Pressable accessibilityRole="link" accessibilityLabel={`Sponsorlu içerik. ${banner.sponsorName}. ${banner.headline}`} accessibilityHint="Sponsor bağlantısını açar" onPress={open} style={({ pressed }) => [local.wrap, pressed && local.pressed]}>{content}</Pressable>;
}

const local = StyleSheet.create({
  wrap: { alignSelf: 'center', marginVertical: 12 },
  banner: { aspectRatio: 2, overflow: 'hidden', borderRadius: 22, backgroundColor: '#E6ECE7', borderWidth: StyleSheet.hairlineWidth, borderColor: '#D5DED7' },
  label: { position: 'absolute', top: 10, right: 10, borderRadius: 8, backgroundColor: 'rgba(8, 32, 23, 0.78)', paddingHorizontal: 8, paddingVertical: 5 },
  labelText: { color: '#FFFFFF', fontSize: 9, fontWeight: '800', letterSpacing: 0.8 },
  pressed: { opacity: 0.92 },
});

export default memo(SponsoredBannerSlot);