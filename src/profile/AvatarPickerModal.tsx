import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { C } from '../../styles';
import { AVATAR_OPTIONS, AvatarKey, avatarKeyFromUri } from './avatarCatalog';
import UserAvatar from './UserAvatar';

export default function AvatarPickerModal({ visible, currentUri, saving, close, select }: {
  visible: boolean;
  currentUri?: string | null;
  saving: boolean;
  close: () => void;
  select: (key: AvatarKey) => Promise<void>;
}) {
  const insets = useSafeAreaInsets();
  const currentKey = avatarKeyFromUri(currentUri);
  const [selectedKey, setSelectedKey] = useState<AvatarKey>(currentKey || 'avatar_01');

  useEffect(() => {
    if (visible) setSelectedKey(currentKey || 'avatar_01');
  }, [currentKey, visible]);

  return <Modal visible={visible} animationType="slide" onRequestClose={close}>
    <View style={[x.screen, { paddingTop: Math.max(insets.top, 10) }]}>
      <View style={x.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Avatar seçimini kapat" onPress={close} disabled={saving} style={x.close}><Text style={x.closeText}>‹</Text></Pressable>
        <View style={x.headerCopy}><Text style={x.eyebrow}>PROFİL GÖRÜNÜMÜ</Text><Text style={x.title}>Avatarını seç</Text></View>
      </View>
      <ScrollView contentContainerStyle={[x.content, { paddingBottom: 24 + insets.bottom }]} showsVerticalScrollIndicator={false}>
        <Text style={x.lead}>Seni temsil edecek avatarı seç. Seçimin profilinde, ilanlarında, mesajlarında ve sıralamada görünür.</Text>
        <View style={x.grid}>
          {AVATAR_OPTIONS.map((option, index) => {
            const selected = option.key === selectedKey;
            return <Pressable
              key={option.key}
              accessibilityRole="radio"
              accessibilityState={{ selected }}
              accessibilityLabel={`Avatar ${index + 1}`}
              onPress={() => setSelectedKey(option.key)}
              style={[x.option, selected && x.optionSelected]}
            >
              <UserAvatar uri={`preset://${option.key}`} name={`Avatar ${index + 1}`} size={104} />
              <View style={[x.radio, selected && x.radioSelected]}>{selected && <Text style={x.check}>✓</Text>}</View>
            </Pressable>;
          })}
        </View>
        <Pressable disabled={saving || selectedKey === currentKey} onPress={() => void select(selectedKey)} style={[x.save, (saving || selectedKey === currentKey) && x.saveDisabled]}>
          {saving ? <ActivityIndicator color={C.white} /> : <Text style={x.saveText}>{selectedKey === currentKey ? 'Bu avatar kullanılıyor' : 'Avatarı kullan'}</Text>}
        </Pressable>
      </ScrollView>
    </View>
  </Modal>;
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  header: { minHeight: 72, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  close: { width: 44, height: 44, borderRadius: 22, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center', marginRight: 11 },
  closeText: { color: C.ink, fontSize: 30, lineHeight: 33 },
  headerCopy: { flex: 1 },
  eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.3, fontWeight: '900' },
  title: { color: C.ink, fontSize: 21, fontWeight: '900', marginTop: 2 },
  content: { padding: 18 },
  lead: { color: C.muted, fontSize: 13, lineHeight: 20, textAlign: 'center', paddingHorizontal: 10, marginBottom: 18 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  option: { width: '48%', minHeight: 142, borderRadius: 22, backgroundColor: C.white, borderWidth: 2, borderColor: C.line, alignItems: 'center', justifyContent: 'center', position: 'relative' },
  optionSelected: { borderColor: C.green, backgroundColor: '#F0F8F1' },
  radio: { position: 'absolute', right: 10, top: 10, width: 26, height: 26, borderRadius: 13, borderWidth: 2, borderColor: C.line, backgroundColor: C.white, alignItems: 'center', justifyContent: 'center' },
  radioSelected: { borderColor: C.green, backgroundColor: C.green },
  check: { color: C.white, fontSize: 15, fontWeight: '900', lineHeight: 18 },
  save: { minHeight: 54, borderRadius: 17, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center', marginTop: 18 },
  saveDisabled: { opacity: .45 },
  saveText: { color: C.white, fontSize: 14, fontWeight: '900' },
});