import React from 'react';
import { Image, StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { C } from '../../styles';

export default function UserAvatar({ uri, name, size = 48, style, fallbackText }: { uri?: string | null; name: string; size?: number; style?: StyleProp<ViewStyle>; fallbackText?: string }) {
  const radius = size / 2;
  return <View accessibilityLabel={uri ? `${name} profil fotoğrafı` : `${name} profil simgesi`} style={[x.frame, { width: size, height: size, borderRadius: radius }, style]}>
    {uri
      ? <Image source={{ uri }} resizeMode="cover" style={{ width: size, height: size, borderRadius: radius }} />
      : <Text style={[x.initial, { fontSize: Math.max(14, size * .38) }]}>{fallbackText || name.trim()[0]?.toUpperCase() || 'D'}</Text>}
  </View>;
}

const x = StyleSheet.create({
  frame: { backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  initial: { color: C.dark, fontWeight: '900' },
});
