import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';

type RollingQuota = { used: number; limit: number; remaining: number; nextAvailableAt: string | null };
type CapacityQuota = { used: number; limit: number };
type UsageData = {
  isNewAccount: boolean;
  newAccountEndsAt: string | null;
  listings: RollingQuota;
  contacts: RollingQuota;
  messageConversations: RollingQuota;
  pickups: RollingQuota;
  messages: RollingQuota & { perMinute: number; perHour: number; unansweredLimit: number };
  activeListings: CapacityQuota;
  activePickups: CapacityQuota;
};

const formatDate = (value: string | null) => value
  ? new Date(value).toLocaleString('tr-TR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
  : null;

export default function UsageLimitsScreen({ token, back }: { token: string; back: () => void }) {
  const [data, setData] = useState<UsageData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await apiRequest<{ data: UsageData }>('/usage-policy', { token, retry: false });
      setData(response.data);
    } catch (loadError) {
      setError(loadError instanceof ApiError ? loadError.message : 'Kullanım haklarına ulaşılamadı.');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => { void load(); }, [load]);

  return (
    <View style={x.screen}>
      <View style={x.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Profile dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable>
        <View style={x.headerCopy}><Text style={x.eyebrow}>KULLANIM HAKLARI</Text><Text style={x.title}>Limitlerim</Text></View>
      </View>
      {loading ? (
        <View style={x.center}><ActivityIndicator color={C.green} size="large" /><Text style={x.centerText}>Güncel hakların hesaplanıyor…</Text></View>
      ) : error || !data ? (
        <View style={x.center}><Text style={x.errorTitle}>Limitler alınamadı</Text><Text style={x.centerText}>{error}</Text><Pressable onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Tekrar dene</Text></Pressable></View>
      ) : (
        <ScrollView contentContainerStyle={x.content} showsVerticalScrollIndicator={false}>
          <View style={[x.accountCard, data.isNewAccount && x.accountCardNew]}>
            <Text style={x.accountEyebrow}>{data.isNewAccount ? 'YENİ HESAP DÖNEMİ' : 'STANDART HESAP'}</Text>
            <Text style={x.accountTitle}>{data.isNewAccount ? 'Yeni kullanıcı limitleri uygulanıyor' : 'Standart kullanım limitleri uygulanıyor'}</Text>
            <Text style={x.accountText}>{data.isNewAccount && data.newAccountEndsAt
              ? `Yeni hesap dönemin ${formatDate(data.newAccountEndsAt)} tarihinde sona erecek. Sonrasında standart limitlere otomatik geçeceksin.`
              : 'Hakların son 24 saat içindeki kullanımına göre hareketli olarak yenilenir.'}</Text>
          </View>

          <Text style={x.sectionTitle}>SON 24 SAATLİK HAKLAR</Text>
          <QuotaCard title="İlan oluşturma" quota={data.listings} />
          <QuotaCard title="Yeni görüşme başlatma" quota={data.contacts} />
          <QuotaCard title="Mesaj amaçlı görüşme" quota={data.messageConversations} />
          <QuotaCard title="Alım talebi gönderme" quota={data.pickups} />
          <QuotaCard title="Mesaj gönderme" quota={data.messages} detail={`Dakikada ${data.messages.perMinute} · Saatte ${data.messages.perHour} · Yanıt gelmeden en fazla ${data.messages.unansweredLimit}`} />

          <Text style={x.sectionTitle}>AKTİF KONTENJANLAR</Text>
          <CapacityCard title="Aktif ilanlar" quota={data.activeListings} />
          <CapacityCard title="Aktif alım talepleri" quota={data.activePickups} />
          <Text style={x.footerText}>Bu ekran yalnızca açıldığında sunucudan güncel bilgi alır. Haklar yönetici ayarlarına göre anında hesaplanır.</Text>
        </ScrollView>
      )}
    </View>
  );
}

function QuotaCard({ title, quota, detail }: { title: string; quota: RollingQuota; detail?: string }) {
  const ratio = quota.limit > 0 ? Math.min(1, quota.used / quota.limit) : 0;
  const next = quota.remaining === 0 ? formatDate(quota.nextAvailableAt) : null;
  return <View style={x.card}>
    <View style={x.cardTop}><Text style={x.cardTitle}>{title}</Text><Text style={[x.remaining, quota.remaining === 0 && x.exhausted]}>{quota.remaining} kaldı</Text></View>
    <Text style={x.count}>{quota.used} / {quota.limit} kullanıldı</Text>
    <View style={x.track}><View style={[x.progress, { width: `${ratio * 100}%` }, quota.remaining === 0 && x.progressExhausted]} /></View>
    {next && <Text style={x.next}>İlk hak {next} tarihinde yenilenir.</Text>}
    {detail && <Text style={x.detail}>{detail}</Text>}
  </View>;
}

function CapacityCard({ title, quota }: { title: string; quota: CapacityQuota }) {
  const remaining = Math.max(0, quota.limit - quota.used);
  return <View style={x.card}><View style={x.cardTop}><Text style={x.cardTitle}>{title}</Text><Text style={[x.remaining, remaining === 0 && x.exhausted]}>{remaining} boş yer</Text></View><Text style={x.count}>{quota.used} / {quota.limit} aktif</Text></View>;
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  header: { minHeight: 82, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg },
  backText: { color: C.ink, fontSize: 30, lineHeight: 33 },
  headerCopy: { flex: 1, marginLeft: 12 }, eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.4, fontWeight: '900' }, title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 },
  content: { padding: 16, paddingBottom: 32 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28 }, centerText: { color: C.muted, fontSize: 12, lineHeight: 19, textAlign: 'center', marginTop: 10 }, errorTitle: { color: C.ink, fontSize: 17, fontWeight: '900' }, retry: { marginTop: 16, minHeight: 44, paddingHorizontal: 20, borderRadius: 14, backgroundColor: C.green, justifyContent: 'center' }, retryText: { color: C.white, fontSize: 12, fontWeight: '900' },
  accountCard: { padding: 18, borderRadius: 22, backgroundColor: C.dark, marginBottom: 20 }, accountCardNew: { backgroundColor: '#28553A' }, accountEyebrow: { color: C.lime, fontSize: 10, letterSpacing: 1.2, fontWeight: '900' }, accountTitle: { color: C.white, fontSize: 18, lineHeight: 24, fontWeight: '900', marginTop: 7 }, accountText: { color: '#D5E5D9', fontSize: 12, lineHeight: 19, marginTop: 7 },
  sectionTitle: { color: C.green, fontSize: 11, letterSpacing: 1.2, fontWeight: '900', marginLeft: 3, marginBottom: 9, marginTop: 4 },
  card: { padding: 15, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, marginBottom: 9 }, cardTop: { flexDirection: 'row', alignItems: 'center', gap: 10 }, cardTitle: { flex: 1, color: C.ink, fontSize: 13, fontWeight: '900' }, remaining: { color: C.green, fontSize: 12, fontWeight: '900' }, exhausted: { color: '#A94636' }, count: { color: C.muted, fontSize: 12, marginTop: 6 }, track: { height: 7, borderRadius: 4, backgroundColor: '#E6EBE7', overflow: 'hidden', marginTop: 10 }, progress: { height: '100%', borderRadius: 4, backgroundColor: C.green }, progressExhausted: { backgroundColor: '#A94636' }, next: { color: '#8A5A19', fontSize: 11, lineHeight: 17, fontWeight: '800', marginTop: 9 }, detail: { color: C.muted, fontSize: 11, lineHeight: 17, marginTop: 7 }, footerText: { color: C.muted, fontSize: 11, lineHeight: 17, textAlign: 'center', marginTop: 10, paddingHorizontal: 14 },
});