import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { readStaleCache, writeStaleCache } from '../lib/staleCache';
import MonetizedAdSlot from '../advertising/MonetizedAdSlot';
import UserAvatar from '../profile/UserAvatar';

type Badge = { code: string; name: string; icon: string };
type RankRow = { rank: number | null; userId: number | null; name: string | null; avatarUrl?: string | null; anonymous: boolean; isOwn?: boolean; points: number; deliveries: number; badges: Badge[] };
type Period = 'monthly' | 'all';
type Response = {
  data: RankRow[];
  own: RankRow | null;
  meta: { period: Period; periodLabel: string; totalParticipants: number; nameVisible: boolean | null; pointsRule: string };
};

const LEADERBOARD_CACHE_PREFIX = '@dongu/leaderboard/v2/';

export default function LeaderboardScreen({ token, userId, requireAuth }: { token?: string | null; userId?: string | null; requireAuth?: () => void }) {
  const [period, setPeriod] = useState<Period>('monthly');
  const [rows, setRows] = useState<RankRow[]>([]);
  const [own, setOwn] = useState<RankRow | null>(null);
  const [meta, setMeta] = useState<Response['meta'] | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [privacySaving, setPrivacySaving] = useState(false);
  const requestSequence = useRef(0);
  const cacheKey = `${LEADERBOARD_CACHE_PREFIX}${userId || 'guest'}/${period}`;

  const load = useCallback(async (refresh = false, background = false) => {
    const sequence = ++requestSequence.current;
    if (refresh) setRefreshing(true); else if (!background) setLoading(true);
    try {
      const response = await apiRequest<Response>(`/leaderboard?period=${period}`, token ? { token } : {});
      if (sequence !== requestSequence.current) return;
      setRows(response.data); setOwn(response.own); setMeta(response.meta); setError('');
      void writeStaleCache(cacheKey, response);
    } catch (loadError) {
      if (sequence === requestSequence.current && !background) setError(loadError instanceof ApiError ? loadError.message : 'Sıralamaya ulaşılamadı.');
    } finally {
      if (sequence === requestSequence.current) { setLoading(false); setRefreshing(false); }
    }
  }, [cacheKey, period, token]);

  useEffect(() => {
    const sequence = ++requestSequence.current;
    setRows([]); setOwn(null); setMeta(null); setError(''); setLoading(true);
    void (async () => {
      const cached = await readStaleCache<Response>(cacheKey, 24 * 60 * 60 * 1000);
      if (sequence !== requestSequence.current) return;
      if (cached) {
        setRows(cached.data); setOwn(cached.own); setMeta(cached.meta); setLoading(false);
      }
      await load(false, Boolean(cached));
    })();
    return () => { if (requestSequence.current === sequence) requestSequence.current += 1; };
  }, [cacheKey, load]);

  const updatePrivacy = async (nameVisible: boolean) => {
    if (!token || !meta) return requireAuth?.();
    const previous = meta.nameVisible;
    setMeta({ ...meta, nameVisible }); setPrivacySaving(true);
    try {
      await apiRequest('/leaderboard/privacy', { method: 'PATCH', token, body: { nameVisible } });
      void load(true);
    } catch {
      setMeta(current => current ? { ...current, nameVisible: previous } : current);
    } finally { setPrivacySaving(false); }
  };

  const firstThree = rows.slice(0, 3);
  const podium = [firstThree[1], firstThree[0], firstThree[2]].filter((row): row is RankRow => !!row);
  const rest = rows.slice(3);
  return (
    <ScrollView style={x.screen} contentContainerStyle={x.content} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void load(true)} tintColor={C.green} />}>
      <Text style={x.eyebrow}>DOĞAYA KATKI</Text><Text style={x.title}>Döngü sıralaması</Text>
      <View style={x.tabs}>
        <Pressable onPress={() => setPeriod('monthly')} style={[x.tab, period === 'monthly' && x.tabActive]}><Text style={[x.tabText, period === 'monthly' && x.tabTextActive]}>Bu ay</Text></Pressable>
        <Pressable onPress={() => setPeriod('all')} style={[x.tab, period === 'all' && x.tabActive]}><Text style={[x.tabText, period === 'all' && x.tabTextActive]}>Tüm zamanlar</Text></Pressable>
      </View>

      {loading && !rows.length ? <LeaderboardSkeleton /> : error && !rows.length ? (
        <View style={x.empty}><Text style={x.emptyTitle}>Sıralama yüklenemedi</Text><Text style={x.emptyText}>{error}</Text><Pressable onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Tekrar dene</Text></Pressable></View>
      ) : (
        <>
          <View style={x.periodRow}><Text style={x.periodLabel}>{meta?.periodLabel}</Text><Text style={x.participants}>{meta?.totalParticipants ?? 0} katılımcı</Text></View>
          {podium.length ? <View style={x.podium}>{podium.map(row => <Podium key={row.userId} row={row} />)}</View> : <View style={x.empty}><Text style={x.emptyTitle}>İlk puanı sen kazan</Text><Text style={x.emptyText}>Bu dönemde tamamlanan bir teslimat henüz yok.</Text></View>}
          {!!rest.length && <View style={x.list}>{rest.slice(0, 7).map(row => <RankItem key={row.userId} row={row} />)}</View>}
          <MonetizedAdSlot placement="leaderboard" token={token} itemCount={rows.length} style={x.adSlot} />
          {rest.length > 7 && <View style={x.list}>{rest.slice(7).map(row => <RankItem key={row.userId} row={row} />)}</View>}

          {token && own ? <View style={x.ownCard}>
            <View style={x.ownTop}><View style={x.ownIdentity}><UserAvatar uri={own.avatarUrl} name={own.name || 'Sen'} size={44} style={x.ownAvatar} /><View><Text style={x.ownEyebrow}>SENİN SIRAN</Text><Text style={x.ownRank}>{own.rank ? `#${own.rank}` : 'Henüz sıran yok'}</Text></View></View><View style={x.pointsPill}><Text style={x.pointsValue}>{own.points}</Text><Text style={x.pointsLabel}>PUAN</Text></View></View>
            <Text style={x.ownMeta}>{own.deliveries} tamamlanan teslimat</Text><Badges badges={own.badges} />
          </View> : !token ? <Pressable onPress={requireAuth} style={x.joinCard}><Text style={x.joinTitle}>Kendi sıranı görmek ister misin?</Text><Text style={x.joinText}>Hesabına giriş yaparak puanını, sıranı ve rozetlerini takip edebilirsin.</Text><Text style={x.joinAction}>Döngü’ye katıl →</Text></Pressable> : null}

          <View style={x.ruleCard}><Text style={x.ruleTitle}>Puan nasıl hesaplanır?</Text><Text style={x.ruleText}>{meta?.pointsRule}</Text><Text style={x.ruleNote}>Teslim koduyla tamamlanmayan işlemler puan kazandırmaz. Döngü puanı ambalajı teslim eden satıcıya verilir.</Text></View>
          {token && meta && meta.nameVisible !== null && <View style={x.privacyCard}><View style={x.privacyCopy}><Text style={x.privacyTitle}>Adımı ve fotoğrafımı göster</Text><Text style={x.privacyText}>Kapattığında puanın sıralamada kalır; diğer kişiler adın ve fotoğrafın yerine “Döngü üyesi” görür.</Text></View><Switch disabled={privacySaving} value={meta.nameVisible ?? true} onValueChange={value => void updatePrivacy(value)} trackColor={{ false: '#CBD3CE', true: '#8CC8A6' }} thumbColor={meta.nameVisible ? C.green : '#FFFFFF'} /></View>}
        </>
      )}
    </ScrollView>
  );
}

function LeaderboardSkeleton() {
  return <View accessibilityLabel="Sıralama hazırlanıyor"><View style={x.skeletonPeriod}><View style={x.skeletonPeriodLabel} /><View style={x.skeletonPeriodCount} /></View><View style={x.skeletonPodium}>{[0, 1, 2].map(item => <View key={item} style={[x.skeletonPodiumCard, item === 1 && x.skeletonPodiumFirst]}><View style={x.skeletonAvatar} /><View style={x.skeletonName} /><View style={x.skeletonScore} /></View>)}</View><View style={x.skeletonList}>{[0, 1, 2, 3].map(item => <View key={item} style={x.skeletonRow}><View style={x.skeletonRowAvatar} /><View style={x.skeletonRowCopy}><View style={x.skeletonRowTitle} /><View style={x.skeletonRowLine} /></View></View>)}</View></View>;
}

function Podium({ row }: { row: RankRow }) {
  const colors = row.rank === 1 ? ['#FFF1B8', '#8B6611'] : row.rank === 2 ? ['#EAF0ED', '#5C6B63'] : ['#F4D8C2', '#8A5637'];
  return <View style={[x.podiumCard, row.rank === 1 && x.podiumFirst, { backgroundColor: colors[0] }]}><Text style={x.medal}>{row.rank === 1 ? '🥇' : row.rank === 2 ? '🥈' : '🥉'}</Text><UserAvatar uri={row.avatarUrl} name={row.name || 'Döngü üyesi'} fallbackText={row.anonymous ? '♻' : undefined} size={row.rank === 1 ? 52 : 46} style={[x.podiumAvatar, { borderColor: colors[1] }]} /><Text style={x.podiumName} numberOfLines={1}>{row.name}</Text><Text style={x.podiumPoints}>{row.points} puan</Text><Text style={x.podiumDeliveries}>{row.deliveries} teslimat</Text><Badges badges={row.badges.slice(0, 2)} compact /></View>;
}

function RankItem({ row }: { row: RankRow }) {
  return <View style={[x.rankRow, row.isOwn && x.rankRowOwn]}><Text style={x.rankNo}>{row.rank}</Text><UserAvatar uri={row.avatarUrl} name={row.name || 'Döngü üyesi'} fallbackText={row.anonymous ? '♻' : undefined} size={38} /><View style={x.rankCopy}><Text style={x.rankName} numberOfLines={1}>{row.name}{row.isOwn ? ' · Sen' : ''}</Text><Text style={x.rankMeta}>{row.deliveries} teslimat</Text><Badges badges={row.badges.slice(0, 3)} compact /></View><Text style={x.rankPoints}>{row.points}<Text style={x.rankPointsUnit}>{`\n`}puan</Text></Text></View>;
}

function Badges({ badges, compact = false }: { badges: Badge[]; compact?: boolean }) {
  if (!badges.length) return null;
  return <View style={[x.badges, compact && x.badgesCompact]}>{badges.map(badge => <View key={badge.code} style={x.badge}><Text style={x.badgeIcon}>{badge.icon}</Text>{!compact && <Text style={x.badgeName}>{badge.name}</Text>}</View>)}</View>;
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, content: { padding: 20, paddingBottom: 115 }, eyebrow: { color: C.green, fontSize: 12, fontWeight: '900', letterSpacing: 1.7, marginTop: 8 }, title: { color: C.ink, fontSize: 24, fontWeight: '800', marginTop: 5, marginBottom: 18 },
  tabs: { flexDirection: 'row', backgroundColor: '#E6ECE8', borderRadius: 15, padding: 4 }, tab: { flex: 1, minHeight: 42, borderRadius: 12, alignItems: 'center', justifyContent: 'center' }, tabActive: { backgroundColor: C.white }, tabText: { color: C.muted, fontSize: 12, fontWeight: '800' }, tabTextActive: { color: C.dark },
  skeletonPeriod: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 18, marginBottom: 11 }, skeletonPeriodLabel: { width: 110, height: 12, borderRadius: 6, backgroundColor: '#D8E1DA' }, skeletonPeriodCount: { width: 72, height: 12, borderRadius: 6, backgroundColor: '#E0E7E2' }, skeletonPodium: { minHeight: 194, flexDirection: 'row', alignItems: 'flex-end', gap: 7 }, skeletonPodiumCard: { flex: 1, minHeight: 174, borderRadius: 20, backgroundColor: '#E8EEE9', alignItems: 'center', paddingTop: 28 }, skeletonPodiumFirst: { minHeight: 194 }, skeletonAvatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: '#D5DED7' }, skeletonName: { width: '62%', height: 10, borderRadius: 5, backgroundColor: '#D5DED7', marginTop: 15 }, skeletonScore: { width: '42%', height: 9, borderRadius: 5, backgroundColor: '#DDE5DF', marginTop: 9 }, skeletonList: { marginTop: 12, borderRadius: 20, overflow: 'hidden', backgroundColor: '#EDF2EE' }, skeletonRow: { minHeight: 72, paddingHorizontal: 13, flexDirection: 'row', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: '#E1E8E3' }, skeletonRowAvatar: { width: 38, height: 38, borderRadius: 19, backgroundColor: '#D6E0D8' }, skeletonRowCopy: { flex: 1, marginLeft: 12 }, skeletonRowTitle: { width: '48%', height: 10, borderRadius: 5, backgroundColor: '#D2DDD5' }, skeletonRowLine: { width: '30%', height: 8, borderRadius: 4, backgroundColor: '#DCE5DE', marginTop: 8 },
  periodRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 18, marginBottom: 11 }, periodLabel: { color: C.ink, fontSize: 13, fontWeight: '900', textTransform: 'capitalize' }, participants: { color: C.muted, fontSize: 12, fontWeight: '700' },
  podium: { flexDirection: 'row', alignItems: 'flex-end', gap: 7 }, podiumCard: { flex: 1, minHeight: 174, padding: 10, borderRadius: 20, alignItems: 'center', borderWidth: 1, borderColor: 'rgba(30,60,40,.07)' }, podiumFirst: { minHeight: 194, paddingTop: 13 }, medal: { fontSize: 22 }, podiumAvatar: { marginTop: 5, borderWidth: 2 }, podiumName: { color: C.ink, fontSize: 12, fontWeight: '900', marginTop: 8, maxWidth: '100%' }, podiumPoints: { color: C.dark, fontSize: 12, fontWeight: '900', marginTop: 5 }, podiumDeliveries: { color: C.muted, fontSize: 10, marginTop: 2 },
  adSlot: { marginTop: 12 },
  list: { backgroundColor: C.white, borderRadius: 20, borderWidth: 1, borderColor: C.line, marginTop: 12, overflow: 'hidden' }, rankRow: { minHeight: 72, paddingHorizontal: 13, paddingVertical: 10, flexDirection: 'row', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: C.line }, rankRowOwn: { backgroundColor: '#F1F8F3' }, rankNo: { width: 28, color: C.muted, fontSize: 12, fontWeight: '900' },  rankCopy: { flex: 1, marginLeft: 10 }, rankName: { color: C.ink, fontSize: 12, fontWeight: '900' }, rankMeta: { color: C.muted, fontSize: 11, marginTop: 3 }, rankPoints: { color: C.green, fontSize: 15, fontWeight: '900', textAlign: 'right' }, rankPointsUnit: { fontSize: 10, color: C.muted },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: 5, marginTop: 9 }, badgesCompact: { justifyContent: 'center', marginTop: 5 }, badge: { flexDirection: 'row', alignItems: 'center', gap: 3, paddingHorizontal: 6, paddingVertical: 3, minHeight: 26, borderRadius: 13, backgroundColor: 'rgba(255,255,255,.7)' }, badgeIcon: { fontSize: 12 }, badgeName: { color: C.dark, fontSize: 10, fontWeight: '800' },
  ownCard: { marginTop: 14, padding: 17, borderRadius: 21, backgroundColor: C.dark }, ownTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }, ownIdentity: { flexDirection: 'row', alignItems: 'center' }, ownAvatar: { marginRight: 10, borderWidth: 2, borderColor: '#5D8A72' }, ownEyebrow: { color: C.lime, fontSize: 11, letterSpacing: 1.2, fontWeight: '900' }, ownRank: { color: C.white, fontSize: 23, fontWeight: '900', marginTop: 3 }, ownMeta: { color: '#BFCAC3', fontSize: 12, marginTop: 4 }, pointsPill: { minWidth: 68, padding: 10, borderRadius: 16, backgroundColor: '#295A43', alignItems: 'center' }, pointsValue: { color: C.white, fontSize: 18, fontWeight: '900' }, pointsLabel: { color: C.lime, fontSize: 10, fontWeight: '900' },
  joinCard: { marginTop: 14, padding: 18, borderRadius: 21, backgroundColor: C.dark }, joinTitle: { color: C.white, fontSize: 15, fontWeight: '900' }, joinText: { color: '#C9D4CD', fontSize: 12, lineHeight: 18, marginTop: 6 }, joinAction: { color: C.lime, fontSize: 12, fontWeight: '900', marginTop: 12 }, ruleCard: { marginTop: 12, padding: 16, borderRadius: 19, backgroundColor: C.white, borderWidth: 1, borderColor: C.line }, ruleTitle: { color: C.ink, fontSize: 12, fontWeight: '900' }, ruleText: { color: C.dark, fontSize: 12, lineHeight: 18, marginTop: 6 }, ruleNote: { color: C.muted, fontSize: 11, lineHeight: 16, marginTop: 5 }, privacyCard: { marginTop: 10, padding: 15, borderRadius: 19, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, flexDirection: 'row', alignItems: 'center' }, privacyCopy: { flex: 1, paddingRight: 10 }, privacyTitle: { color: C.ink, fontSize: 12, fontWeight: '900' }, privacyText: { color: C.muted, fontSize: 11, lineHeight: 16, marginTop: 4 },
  empty: { marginTop: 20, padding: 26, borderRadius: 20, backgroundColor: C.white, alignItems: 'center' }, emptyTitle: { color: C.ink, fontSize: 14, fontWeight: '900' }, emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 6 }, retry: { marginTop: 13, paddingHorizontal: 15, height: 38, borderRadius: 12, backgroundColor: C.green, justifyContent: 'center' }, retryText: { color: C.white, fontSize: 12, fontWeight: '900' },
});
