import React, { useEffect, useState } from 'react';
import { Image, StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { C } from '../../styles';
import { avatarSourceFromUri } from './avatarCatalog';

export default function UserAvatar({ uri, name, size = 48, style, fallbackText }: { uri?: string | null; name: string; size?: number; style?: StyleProp<ViewStyle>; fallbackText?: string }) {
  const [remoteFailed, setRemoteFailed] = useState(false);
  const radius = size / 2;
  const presetSource = avatarSourceFromUri(uri);
  const remoteSource = uri && !uri.startsWith('preset://') && !remoteFailed ? { uri } : null;
  const source = presetSource || remoteSource;

  return <View accessibilityLabel={source ? `${name} avatarı` : `${name} profil simgesi`} style={[x.frame, { width: size, height: size, borderRadius: radius }, style]}>
    {source
      ? <Image source={source} onError={() => { if (remoteSource) setRemoteFailed(true); }} resizeMode="cover" style={{ width: size, height: size, borderRadius: radius }} />
      : <Text style={[x.initial, { fontSize: Math.max(14, size * .38) }]}>{fallbackText || name.trim()[0]?.toUpperCase() || 'D'}</Text>}
  </View>;
}

const x = StyleSheet.create({
  frame: { backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  initial: { color: C.dark, fontWeight: '900' },
});