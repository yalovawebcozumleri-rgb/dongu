import React from 'react';
import { Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { C, s } from '../../styles';

const DEFAULT_CONTACT_URL = 'https://wa.me/905413342219?text=Merhaba%2C%20D%C3%B6ng%C3%BC%20reklam%20ve%20destek%C3%A7i%20se%C3%A7enekleri%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.';

function CycleLeafMark() {
  return (
    <Svg width={34} height={34} viewBox="0 0 48 48" fill="none" accessibilityElementsHidden>
      <Path d="M35.7 16.2A14 14 0 0 0 12 15" stroke="#FFFFFF" strokeWidth={3.2} strokeLinecap="round" />
      <Path d="m11.4 9.6.6 6.3 6.2-1.5" stroke="#FFFFFF" strokeWidth={3.2} strokeLinecap="round" strokeLinejoin="round" />
      <Path d="M12.3 31.8A14 14 0 0 0 36 33" stroke="#FFFFFF" strokeWidth={3.2} strokeLinecap="round" />
      <Path d="m36.6 38.4-.6-6.3-6.2 1.5" stroke="#FFFFFF" strokeWidth={3.2} strokeLinecap="round" strokeLinejoin="round" />
      <Path d="M29.8 18.4c-7.3.2-10.8 3.9-10.1 10.5 6.5.7 10.1-3 10.1-10.5Z" stroke="#D8F27B" strokeWidth={2.8} strokeLinecap="round" strokeLinejoin="round" />
      <Path d="m20.4 28.2 6.1-6" stroke="#D8F27B" strokeWidth={2.8} strokeLinecap="round" />
    </Svg>
  );
}

export default function HouseAdCard({ contactUrl, horizontalMargin = 30 }: { contactUrl?: string; horizontalMargin?: number }) {
  const open = () => void Linking.openURL(contactUrl || process.env.EXPO_PUBLIC_AD_SALES_URL || DEFAULT_CONTACT_URL);
  return (
    <Pressable accessibilityRole="link" accessibilityLabel="İşletmenizi Döngü'de tanıtın. WhatsApp'tan bilgi alın" onPress={open} style={({ pressed }) => [local.card, { marginHorizontal: horizontalMargin }, pressed && s.pressed]}>
      <View style={local.mark}><CycleLeafMark /></View>
      <View style={local.copy}>
        <View style={local.labelRow}><Text style={local.label}>DÖNGÜ REKLAM</Text><Text style={local.house}>WHATSAPP</Text></View>
        <Text style={local.title}>İşletmenizi bölgenizde görünür kılın.</Text>
        <Text style={local.body}>Döngü kullanıcılarına ulaşmak ve kurumsal reklam vermek için iletişime geçin.</Text>
        <Text style={local.cta}>WhatsApp’tan bilgi al  ›</Text>
      </View>
    </Pressable>
  );
}

const local = StyleSheet.create({
  card: { marginHorizontal: 30, marginVertical: 10, minHeight: 150, borderRadius: 24, padding: 18, flexDirection: 'row', gap: 14, backgroundColor: '#EAF5ED', borderWidth: 1, borderColor: '#CFE4D4' },
  mark: { width: 52, height: 52, borderRadius: 16, alignItems: 'center', justifyContent: 'center', backgroundColor: C.green },
  copy: { flex: 1 },
  labelRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 8, marginBottom: 7 },
  label: { color: C.green, fontSize: 11, fontWeight: '900', letterSpacing: 0.8 },
  house: { color: '#587060', fontSize: 10, fontWeight: '800' },
  title: { color: '#16251B', fontSize: 17, lineHeight: 22, fontWeight: '900' },
  body: { color: '#5C6B61', fontSize: 14, lineHeight: 20, marginTop: 5 },
  cta: { color: C.green, fontSize: 14, fontWeight: '900', marginTop: 10 },
});
