import React, { memo, useEffect } from 'react';
import { Image, Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { C, s } from '../../styles';
import { apiRequest } from '../lib/api';
import { Advertisement, AdvertisementPlacement } from './types';

const sessionKey = `mobile-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;

function SponsoredCard({ advertisement, placement, slotIndex, token }: { advertisement: Advertisement; placement: AdvertisementPlacement; slotIndex: number; token?: string | null; }) {
  const record = (event: 'impressions' | 'clicks') => { void apiRequest(`/advertisements/${advertisement.id}/${event}`, { method: 'POST', token, body: { sessionKey, placement, slotIndex } }).catch(() => undefined); };
  useEffect(() => { record('impressions'); }, [advertisement.id, placement, slotIndex]);
  const open = () => { if (!advertisement.targetUrl) return; record('clicks'); void Linking.openURL(advertisement.targetUrl); };
  const content = <>
    {!!advertisement.imageUrl && <Image source={{ uri: advertisement.imageUrl }} resizeMode="cover" style={local.image} accessibilityIgnoresInvertColors />}
    <View style={s.adTopRow}><Text style={s.adSponsor}>{advertisement.sponsorName}</Text><Text style={s.adLabel}>SPONSORLU</Text></View>
    <Text style={s.adHeadline}>{advertisement.headline}</Text><Text style={s.adBody}>{advertisement.body}</Text>
    {!!advertisement.targetUrl && !!advertisement.ctaLabel && <Text style={s.adCta}>{advertisement.ctaLabel} ›</Text>}
  </>;
  const cardStyle = [s.adCard, { backgroundColor: advertisement.backgroundColor || C.soft }];
  if (!advertisement.targetUrl) return <View accessible accessibilityLabel={`Sponsorlu içerik. ${advertisement.sponsorName}. ${advertisement.headline}. ${advertisement.body}`} style={cardStyle}>{content}</View>;
  return <Pressable accessibilityRole="link" accessibilityLabel={`Sponsorlu içerik. ${advertisement.sponsorName}. ${advertisement.headline}`} accessibilityHint="Reklam bağlantısını açar" onPress={open} style={({ pressed }) => [...cardStyle, pressed && s.pressed]}>{content}</Pressable>;
}
const local = StyleSheet.create({ image: { width: '100%', height: 148, borderRadius: 16, marginBottom: 13, backgroundColor: '#DCE6DE' } });
export default memo(SponsoredCard);
