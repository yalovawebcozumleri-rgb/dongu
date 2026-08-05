import React, { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { C } from '../../styles';

export type NoticeTone = 'info' | 'success' | 'warning' | 'error';

export type NoticeOptions = {
  title: string;
  message: string;
  eyebrow?: string;
  tone?: NoticeTone;
  primaryLabel?: string;
  secondaryLabel?: string;
};

type ActiveNotice = NoticeOptions & {
  confirm: boolean;
  resolve?: (accepted: boolean) => void;
};

type NoticeContextValue = {
  showNotice: (options: NoticeOptions) => void;
  confirmNotice: (options: NoticeOptions) => Promise<boolean>;
};

const NoticeContext = createContext<NoticeContextValue | null>(null);

const tones: Record<NoticeTone, { accent: string; soft: string; icon: string; eyebrow: string }> = {
  info: { accent: C.green, soft: '#E8F4E9', icon: 'i', eyebrow: 'BİLGİ' },
  success: { accent: C.green, soft: '#E8F4E9', icon: '✓', eyebrow: 'TAMAMLANDI' },
  warning: { accent: '#B87516', soft: '#FFF3DD', icon: '!', eyebrow: 'DİKKAT' },
  error: { accent: '#B54735', soft: '#FCEBE7', icon: '!', eyebrow: 'İŞLEM TAMAMLANAMADI' },
};

export function NoticeProvider({ children }: React.PropsWithChildren) {
  const [active, setActive] = useState<ActiveNotice | null>(null);

  const showNotice = useCallback((options: NoticeOptions) => {
    setActive({ ...options, confirm: false });
  }, []);

  const confirmNotice = useCallback((options: NoticeOptions) => new Promise<boolean>(resolve => {
    setActive({ ...options, confirm: true, resolve });
  }), []);

  const close = useCallback((accepted = false) => {
    setActive(current => {
      current?.resolve?.(accepted);
      return null;
    });
  }, []);

  const value = useMemo(() => ({ showNotice, confirmNotice }), [confirmNotice, showNotice]);
  const tone = tones[active?.tone ?? 'info'];

  return (
    <NoticeContext.Provider value={value}>
      {children}
      <Modal visible={Boolean(active)} transparent animationType="fade" statusBarTranslucent onRequestClose={() => close(false)}>
        <View style={n.backdrop}>
          <View style={n.card}>
            <View style={[n.glow, { backgroundColor: tone.soft }]} />
            <View style={[n.icon, { backgroundColor: tone.accent, borderColor: tone.soft }]}>
              <Text style={n.iconText}>{tone.icon}</Text>
            </View>
            <Text style={[n.eyebrow, { color: tone.accent }]}>{active?.eyebrow ?? tone.eyebrow}</Text>
            <Text style={n.title}>{active?.title}</Text>
            <Text style={n.message}>{active?.message}</Text>
            <Pressable onPress={() => close(true)} style={[n.primary, { backgroundColor: tone.accent }]}>
              <Text style={n.primaryText}>{active?.primaryLabel ?? (active?.confirm ? 'Onayla' : 'Tamam')}</Text>
            </Pressable>
            {!!active?.confirm && (
              <Pressable onPress={() => close(false)} style={n.secondary}>
                <Text style={[n.secondaryText, { color: tone.accent }]}>{active.secondaryLabel ?? 'Vazgeç'}</Text>
              </Pressable>
            )}
          </View>
        </View>
      </Modal>
    </NoticeContext.Provider>
  );
}

export function useNotice() {
  const context = useContext(NoticeContext);
  if (!context) throw new Error('useNotice, NoticeProvider içinde kullanılmalıdır.');
  return context;
}

const n = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(8,25,16,.66)', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 24 },
  card: { width: '100%', maxWidth: 390, borderRadius: 30, backgroundColor: C.white, padding: 24, alignItems: 'center', overflow: 'hidden', shadowColor: '#07160E', shadowOffset: { width: 0, height: 16 }, shadowOpacity: .28, shadowRadius: 28, elevation: 16 },
  glow: { position: 'absolute', width: 210, height: 210, borderRadius: 105, top: -125, right: -50 },
  icon: { width: 68, height: 68, borderRadius: 34, borderWidth: 7, alignItems: 'center', justifyContent: 'center' },
  iconText: { color: C.white, fontSize: 29, lineHeight: 33, fontWeight: '900' },
  eyebrow: { fontSize: 12, letterSpacing: 1.8, fontWeight: '900', marginTop: 16 },
  title: { color: C.ink, fontSize: 21, lineHeight: 27, fontWeight: '900', textAlign: 'center', letterSpacing: -.4, marginTop: 7 },
  message: { color: C.muted, fontSize: 12, lineHeight: 19, textAlign: 'center', marginTop: 10 },
  primary: { width: '100%', minHeight: 54, borderRadius: 17, alignItems: 'center', justifyContent: 'center', marginTop: 22, paddingHorizontal: 16 },
  primaryText: { color: C.white, fontSize: 13, fontWeight: '900' },
  secondary: { height: 44, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 20, marginTop: 3 },
  secondaryText: { fontSize: 12, fontWeight: '900' },
});