import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, AppState, Linking, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import { enablePushNotifications, getPushNotificationState, PushNotificationState } from '../push/notifications';
import { NotificationPreferences } from './types';

const rows: { key: keyof NotificationPreferences; title: string; text: string }[] = [
  { key: 'messagesEnabled', title: 'Mesajlar', text: 'Yeni kullanıcı mesajları' },
  { key: 'pickupRequestsEnabled', title: 'Alım talepleri', text: 'Talep, kabul, ret ve iptal işlemleri' },
  { key: 'deliveryEnabled', title: 'Teslimatlar', text: 'Teslimat ve önemli işlem durumları' },
  { key: 'reviewsEnabled', title: 'Değerlendirmeler', text: 'Hatırlatmalar ve yeni puanlar' },
  { key: 'listingUpdatesEnabled', title: 'İlan güncellemeleri', text: 'İlan süresi ve favori durumları' },
  { key: 'marketingEnabled', title: 'Duyuru ve kampanyalar', text: 'İsteğe bağlı platform haberleri' },
];

const pushInfo: Record<PushNotificationState, [string, string]> = {
  checking: ['Bildirim durumu kontrol ediliyor', 'Lütfen kısa bir süre bekle.'],
  expo_go: ['Development build gerekli', 'Uzaktan bildirimler Expo Go içinde kullanılamaz.'],
  physical_device_required: ['Fiziksel telefon gerekli', 'Uzaktan bildirimler gerçek bir telefonda kullanılabilir.'],
  not_configured: ['Bildirim kurulumu tamamlanmadı', 'FCM veya APNs yapılandırmasının tamamlanması gerekiyor.'],
  disabled: ['Telefon bildirimleri kapalı', 'Mesaj, talep ve teslimat bildirimlerini almak için izin ver.'],
  blocked: ['Bildirimler telefon ayarlarında kapalı', 'Bildirimleri açmak için telefon ayarlarına git.'],
  enabled: ['Telefon bildirimleri açık', 'Kapatmak veya sistem iznini değiştirmek için telefon ayarlarını aç.'],
};

export default function NotificationPreferencesScreen({ token, back }: { token: string; back: () => void }) {
  const { showNotice } = useNotice();
  const [preferences, setPreferences] = useState<NotificationPreferences | null>(null);
  const [pushState, setPushState] = useState<PushNotificationState>('checking');
  const [loading, setLoading] = useState(true);
  const [savingKey, setSavingKey] = useState<keyof NotificationPreferences | null>(null);
  const [savedKey, setSavedKey] = useState<keyof NotificationPreferences | null>(null);

  const refreshPush = useCallback(async (sync = false) => {
    const state = await getPushNotificationState();
    setPushState(state);
    if (state === 'enabled' && sync) await enablePushNotifications(token);
  }, [token]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiRequest<{ data: NotificationPreferences }>('/notification-preferences', { token });
      setPreferences(response.data);
    } catch (error) {
      showNotice({ tone: 'error', title: 'Tercihler alınamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    } finally {
      setLoading(false);
    }
  }, [showNotice, token]);

  useEffect(() => {
    void load();
    void refreshPush(true);
    const subscription = AppState.addEventListener('change', state => {
      if (state === 'active') void refreshPush(true);
    });
    return () => subscription.remove();
  }, [load, refreshPush]);

  const handlePushCard = async () => {
    if (pushState === 'enabled' || pushState === 'blocked') {
      await Linking.openSettings();
      return;
    }
    if (pushState !== 'disabled') return;
    setPushState('checking');
    const result = await enablePushNotifications(token);
    await refreshPush(result.ok);
    showNotice(result.ok
      ? { tone: 'success', title: 'Bildirimler açıldı', message: 'Bildirim tercihlerini artık aşağıdan yönetebilirsin.' }
      : { tone: 'warning', title: 'Bildirimler açılamadı', message: result.message });
  };

  const updatePreference = async (key: keyof NotificationPreferences, value: boolean) => {
    if (!preferences || pushState !== 'enabled' || savingKey) return;
    const previous = preferences;
    const next = { ...preferences, [key]: value };
    setPreferences(next);
    setSavingKey(key);
    setSavedKey(null);
    try {
      const response = await apiRequest<{ data: NotificationPreferences }>('/notification-preferences', { method: 'PATCH', token, body: next });
      setPreferences(response.data);
      setSavedKey(key);
      setTimeout(() => setSavedKey(current => current === key ? null : current), 1600);
    } catch (error) {
      setPreferences(previous);
      showNotice({ tone: 'error', title: 'Tercih kaydedilemedi', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    } finally {
      setSavingKey(null);
    }
  };

  const categoriesEnabled = pushState === 'enabled';
  const pushCardActionable = ['disabled', 'blocked', 'enabled'].includes(pushState);

  return (
    <View style={x.screen}>
      <View style={x.header}>
        <Pressable onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable>
        <View><Text style={x.eyebrow}>TELEFON BİLDİRİMLERİ</Text><Text style={x.title}>Bildirim tercihleri</Text></View>
      </View>
      {loading || !preferences ? <View style={x.loading}><ActivityIndicator color={C.green} /></View> : (
        <ScrollView contentContainerStyle={x.content}>
          <Pressable accessibilityRole="button" disabled={!pushCardActionable} onPress={() => void handlePushCard()} style={x.pushCard}>
            <View style={[x.pushDot, categoriesEnabled && x.pushDotEnabled]} />
            <View style={x.pushCopy}><Text style={x.pushTitle}>{pushInfo[pushState][0]}</Text><Text style={x.pushText}>{pushInfo[pushState][1]}</Text></View>
            {pushCardActionable && <Text style={x.arrow}>›</Text>}
          </Pressable>
          <Text style={x.sectionTitle}>HANGİ BİLDİRİMLERİ ALMAK İSTERSİN?</Text>
          <Text style={x.intro}>{categoriesEnabled ? 'Bir seçeneği değiştirdiğinde tercihin otomatik olarak kaydedilir.' : 'Önce telefon bildirimlerini aç. Ardından almak istediğin bildirim türlerini buradan seçebilirsin.'}</Text>
          {rows.map(row => {
            const isSaving = savingKey === row.key;
            const isSaved = savedKey === row.key;
            return <View key={row.key} style={[x.row, !categoriesEnabled && x.rowInactive]}>
              <View style={x.rowCopy}>
                <View style={x.rowTitleLine}><Text style={x.rowTitle}>{row.title}</Text>{isSaving && <Text style={x.status}>Kaydediliyor…</Text>}{isSaved && <Text style={x.savedStatus}>Kaydedildi</Text>}</View>
                <Text style={x.rowText}>{row.text}</Text>
              </View>
              <Switch accessibilityLabel={`${row.title} bildirimleri`} disabled={!categoriesEnabled || savingKey !== null} value={categoriesEnabled && preferences[row.key]} onValueChange={value => void updatePreference(row.key, value)} trackColor={{ false: '#CFD7D1', true: '#8FC7A4' }} thumbColor={categoriesEnabled && preferences[row.key] ? C.green : '#F4F4F4'} />
            </View>;
          })}
          <Text style={x.footerNote}>Telefon bildirimlerini kapatsan bile uygulama içindeki bildirim geçmişin görünmeye devam eder.</Text>
        </ScrollView>
      )}
    </View>
  );
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  header: { minHeight: 76, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg, marginRight: 11 },
  backText: { color: C.ink, fontSize: 30, lineHeight: 33 },
  eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.3, fontWeight: '900' },
  title: { color: C.ink, fontSize: 20, fontWeight: '900', marginTop: 2 },
  loading: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  content: { padding: 18, paddingBottom: 34 },
  pushCard: { minHeight: 84, borderRadius: 20, backgroundColor: C.dark, padding: 16, flexDirection: 'row', alignItems: 'center', marginBottom: 18 },
  pushDot: { width: 13, height: 13, borderRadius: 7, backgroundColor: '#D8A448', marginRight: 12 },
  pushDotEnabled: { backgroundColor: C.lime },
  pushCopy: { flex: 1 },
  pushTitle: { color: C.white, fontSize: 14, fontWeight: '900' },
  pushText: { color: '#B9CEC1', fontSize: 12, lineHeight: 18, marginTop: 4 },
  arrow: { color: C.lime, fontSize: 24, marginLeft: 8 },
  sectionTitle: { color: C.green, fontSize: 11, letterSpacing: 1.1, fontWeight: '900', marginBottom: 6 },
  intro: { color: C.muted, fontSize: 12, lineHeight: 19, marginBottom: 14 },
  row: { minHeight: 72, padding: 14, marginBottom: 9, borderRadius: 17, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, flexDirection: 'row', alignItems: 'center' },
  rowInactive: { opacity: .5 },
  rowCopy: { flex: 1, paddingRight: 10 },
  rowTitleLine: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  rowTitle: { color: C.ink, fontSize: 13, fontWeight: '900' },
  rowText: { color: C.muted, fontSize: 11, lineHeight: 16, marginTop: 4 },
  status: { color: C.muted, fontSize: 10, fontWeight: '800' },
  savedStatus: { color: C.green, fontSize: 10, fontWeight: '900' },
  footerNote: { color: C.muted, fontSize: 11, lineHeight: 17, textAlign: 'center', marginTop: 10, paddingHorizontal: 12 },
});
