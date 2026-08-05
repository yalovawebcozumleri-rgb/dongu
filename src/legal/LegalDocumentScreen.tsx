import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { LegalDocument, LegalDocumentKey } from './types';

export default function LegalDocumentScreen({ documentKey, back }: { documentKey: LegalDocumentKey; back: () => void }) {
  const insets = useSafeAreaInsets();
  const [document, setDocument] = useState<LegalDocument | null>(null);
  const [error, setError] = useState('');
  const load = useCallback(async () => {
    setError('');
    try {
      const response = await apiRequest<{ data: LegalDocument }>(`/legal-documents/${documentKey}`);
      setDocument(response.data);
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'Belge şu anda yüklenemedi. İnternet bağlantını kontrol edip yeniden dene.');
    }
  }, [documentKey]);

  useEffect(() => { void load(); }, [load]);

  return <View style={[x.screen, { paddingTop: insets.top }]}>
    <View style={x.header}>
      <Pressable accessibilityRole="button" accessibilityLabel="Geri dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable>
      <View style={x.headerCopy}><Text style={x.eyebrow}>HUKUK VE GİZLİLİK</Text><Text numberOfLines={2} style={x.headerTitle}>{document?.short_title || (documentKey === 'terms' ? 'Kullanım Şartları' : 'Gizlilik Politikası')}</Text></View>
    </View>
    {!document && !error ? <View style={x.center}><ActivityIndicator color={C.green} /><Text style={x.loading}>Belge yükleniyor…</Text></View> : null}
    {!!error ? <View style={x.center}><Text style={x.errorTitle}>Belge açılamadı</Text><Text style={x.error}>{error}</Text><Pressable onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Yeniden dene</Text></Pressable></View> : null}
    {!!document ? <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={x.content}>
      <View style={x.hero}><Text style={x.title}>{document.title}</Text><Text style={x.summary}>{document.summary}</Text><View style={x.metaRow}><Text style={x.meta}>Sürüm {document.version}</Text><Text style={x.meta}>Yürürlük: {document.effective_date}</Text></View></View>
      {document.sections.map(section => <View key={section.title} style={x.section}><Text style={x.sectionTitle}>{section.title}</Text>{section.paragraphs.map((paragraph, index) => <Text key={`${section.title}-${index}`} selectable style={x.paragraph}>{paragraph}</Text>)}</View>)}
      <View style={x.contact}><Text style={x.contactTitle}>Sorun mu var?</Text><Text selectable style={x.contactText}>{document.operator.email} adresinden bize ulaşabilirsin.</Text></View>
    </ScrollView> : null}
  </View>;
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, header: { minHeight: 72, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 18, borderBottomWidth: 1, borderBottomColor: C.line, backgroundColor: C.white }, back: { width: 44, height: 44, borderRadius: 16, alignItems: 'center', justifyContent: 'center', backgroundColor: C.soft, marginRight: 12 }, backText: { color: C.ink, fontSize: 30, lineHeight: 32 }, headerCopy: { flex: 1 }, eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.4, fontWeight: '900' }, headerTitle: { color: C.ink, fontSize: 18, lineHeight: 22, fontWeight: '900', marginTop: 2 }, center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28 }, loading: { color: C.muted, fontSize: 12, marginTop: 12 }, errorTitle: { color: C.ink, fontSize: 18, fontWeight: '900' }, error: { color: C.muted, fontSize: 12, lineHeight: 19, textAlign: 'center', marginTop: 8 }, retry: { minHeight: 46, borderRadius: 15, paddingHorizontal: 20, alignItems: 'center', justifyContent: 'center', backgroundColor: C.dark, marginTop: 17 }, retryText: { color: C.white, fontSize: 12, fontWeight: '900' }, content: { padding: 18, paddingBottom: 42 }, hero: { borderRadius: 24, padding: 22, backgroundColor: C.dark }, title: { color: C.white, fontSize: 25, lineHeight: 31, fontWeight: '900' }, summary: { color: '#D7E2DC', fontSize: 12, lineHeight: 20, marginTop: 10 }, metaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 7, marginTop: 16 }, meta: { color: C.dark, fontSize: 11, fontWeight: '900', backgroundColor: C.lime, borderRadius: 99, paddingHorizontal: 10, paddingVertical: 6 }, section: { paddingVertical: 22, borderBottomWidth: 1, borderBottomColor: C.line }, sectionTitle: { color: C.ink, fontSize: 16, lineHeight: 22, fontWeight: '900', marginBottom: 9 }, paragraph: { color: '#344B40', fontSize: 12, lineHeight: 20, marginBottom: 10 }, contact: { borderRadius: 19, padding: 18, backgroundColor: C.soft, marginTop: 22 }, contactTitle: { color: C.green, fontSize: 12, fontWeight: '900' }, contactText: { color: C.ink, fontSize: 12, lineHeight: 18, marginTop: 5 },
});
