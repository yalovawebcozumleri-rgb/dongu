import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, BackHandler, FlatList, Image, Linking, Pressable, RefreshControl, StyleSheet, Text, View, ViewToken } from 'react-native';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { readStaleCache, writeStaleCache } from '../lib/staleCache';
import HouseAdCard from '../advertising/HouseAdCard';

export type SupporterRegion = { provinceCode?: string; province?: string; districtCode?: string; district?: string };

type SupporterCard = {
  id: number; slug: string; name: string; summary: string; initials: string; logoUrl: string | null;
  scope: 'district' | 'province' | 'nationwide'; areaLabel: string;
  detailTitle: string; detailBody: string; cta: { type: string; label: string; url: string };
};
type SupporterDetail = SupporterCard;
type SupporterCollection = { data: SupporterCard[]; meta: { currentPage: number; lastPage: number; total: number; contactUrl: string } };
type SupporterResponse = { data: SupporterDetail };

const VISITOR_KEY = '@dongu/supporter-visitor-id';
const SUPPORTER_CACHE_PREFIX = '@dongu/supporters/v2/';
const eventId = () => `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
async function visitorId() {
  const saved = await AsyncStorage.getItem(VISITOR_KEY);
  if (saved) return saved;
  const created = `visitor-${eventId()}`;
  await AsyncStorage.setItem(VISITOR_KEY, created);
  return created;
}

async function recordEvent(id: number, type: 'impression' | 'detail_view' | 'cta_click') {
  try {
    await apiRequest(`/supporters/${id}/events`, { method: 'POST', body: { type, visitorId: await visitorId(), eventId: eventId() }, retry: false });
  } catch {
    // Ölçüm hatası kullanıcı deneyimini veya yönlendirmeyi engellemez.
  }
}

export function SupportersDock({ locationLabel, onPress, bottomOffset }: { locationLabel: string; onPress: () => void; bottomOffset: number }) {
  const area = locationLabel && locationLabel !== 'Konum seçilmedi' ? locationLabel : 'Bölgende';
  return (
    <View style={[x.dockClearance, { bottom: bottomOffset }]}>
      <Pressable accessibilityRole="button" accessibilityLabel={`${area} çevresindeki Döngü Destekçilerini keşfet`} accessibilityHint="Bölgesel destekçiler sayfasını açar" onPress={onPress} style={({ pressed }) => [x.dock, pressed && x.pressed]}>
        <View style={x.dockIcon}><Text style={x.dockIconText}>♻</Text></View>
        <View style={x.dockCopy}><Text style={x.dockEyebrow}>DÖNGÜYÜ BİRLİKTE BÜYÜTÜYORUZ</Text><Text style={x.dockTitle}>Bölgenizdeki Döngü Destekçileri</Text></View>
        <View style={x.dockAction}><Text style={x.dockActionText}>Keşfet</Text><Text style={x.dockArrow}>›</Text></View>
      </Pressable>
    </View>
  );
}

function Logo({ item, large = false }: { item: SupporterCard; large?: boolean }) {
  return item.logoUrl
    ? <Image source={{ uri: item.logoUrl }} accessibilityLabel={`${item.name} logosu`} style={[x.logo, large && x.logoLarge]} />
    : <View style={[x.logo, x.logoFallback, large && x.logoLarge]}><Text style={[x.logoText, large && x.logoTextLarge]}>{item.initials}</Text></View>;
}

function SupporterSkeleton() {
  return <View accessibilityLabel="Destekçi listesi hazırlanıyor">{[0, 1, 2].map(item => <View key={item} style={x.skeletonCard}><View style={x.skeletonLogo} /><View style={x.skeletonCopy}><View style={x.skeletonTitle} /><View style={x.skeletonLine} /><View style={x.skeletonLineShort} /></View></View>)}</View>;
}

function Detail({ item, contactUrl, back }: { item: SupporterDetail; contactUrl: string; back: () => void }) {
  const [opening, setOpening] = useState(false);
  useEffect(() => { void recordEvent(item.id, 'detail_view'); }, [item.id]);
  const openTarget = async () => {
    if (opening) return;
    setOpening(true);
    try { await recordEvent(item.id, 'cta_click'); await Linking.openURL(item.cta.url); }
    finally { setOpening(false); }
  };
  return (
    <FlatList data={[]} renderItem={null} contentContainerStyle={x.detailContent} ListHeaderComponent={<>
      <View style={x.header}><Pressable accessibilityRole="button" accessibilityLabel="Destekçilere dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable><View style={x.headerCopy}><Text style={x.headerEyebrow}>DÖNGÜ DESTEKÇİSİ</Text><Text style={x.headerTitle}>İşletme profili</Text></View></View>
      <View style={x.detailHero}><Logo item={item} large /><View style={x.detailBadge}><Text style={x.detailBadgeText}>DÖNGÜ DESTEKÇİSİ</Text></View><Text style={x.detailName}>{item.name}</Text><Text style={x.detailArea}>{item.areaLabel}</Text></View>
      <View style={x.detailCard}><Text style={x.detailTitle}>{item.detailTitle}</Text><Text style={x.detailBody}>{item.detailBody}</Text></View>
      <Pressable accessibilityRole="link" disabled={opening} onPress={() => void openTarget()} style={({ pressed }) => [x.cta, (pressed || opening) && x.pressed]}>{opening ? <ActivityIndicator color={C.white} /> : <><Text style={x.ctaText}>{item.cta.label}</Text><Text style={x.ctaArrow}>›</Text></>}</Pressable>
      <Text style={x.externalNote}>Bu düğme işletmenin seçtiği dış iletişim kanalını açar.</Text><HouseAdCard contactUrl={contactUrl} />
    </>} />
  );
}

export default function SupportersScreen({ locationLabel, region, back }: { locationLabel: string; region: SupporterRegion; back: () => void }) {
  const [items, setItems] = useState<SupporterCard[]>([]); const [page, setPage] = useState(0); const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true); const [refreshing, setRefreshing] = useState(false); const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState(''); const [contactUrl, setContactUrl] = useState('https://wa.me/905413342219?text=Merhaba%2C%20D%C3%B6ng%C3%BC%20reklam%20ve%20destek%C3%A7i%20se%C3%A7enekleri%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.');
  const [detail, setDetail] = useState<SupporterDetail | null>(null);
  const viewed = useRef(new Set<number>());

  const query = useMemo(() => Object.entries(region).filter(([, value]) => value).map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value!)}`).join('&'), [region]);
  const cacheKey = useMemo(() => `${SUPPORTER_CACHE_PREFIX}${query || 'all'}`, [query]);
  const load = useCallback(async (nextPage = 1, mode: 'replace' | 'refresh' | 'more' | 'background' = 'replace') => {
    if (mode === 'replace') setLoading(true); else if (mode === 'refresh') setRefreshing(true); else if (mode === 'more') setLoadingMore(true);
    try {
      const response = await apiRequest<SupporterCollection>(`/supporters?${query}${query ? '&' : ''}page=${nextPage}&perPage=20`);
      setItems(current => mode === 'more' ? [...current, ...response.data.filter(item => !current.some(existing => existing.id === item.id))] : response.data);
      setPage(response.meta.currentPage); setLastPage(response.meta.lastPage); setContactUrl(response.meta.contactUrl); setError('');
      if (nextPage === 1) void writeStaleCache(cacheKey, response);
    } catch (reason) {
      if (mode !== 'background') setError(reason instanceof ApiError ? reason.message : 'Destekçiler yüklenemedi.');
    } finally { setLoading(false); setRefreshing(false); setLoadingMore(false); }
  }, [cacheKey, query]);
  useEffect(() => {
    let active = true;
    viewed.current.clear(); setLoading(true); setError('');
    void (async () => {
      const cached = await readStaleCache<SupporterCollection>(cacheKey);
      if (!active) return;
      if (cached) {
        setItems(cached.data); setPage(cached.meta.currentPage); setLastPage(cached.meta.lastPage);
        setContactUrl(cached.meta.contactUrl); setLoading(false);
      }
      await load(1, 'background');
    })();
    return () => { active = false; };
  }, [cacheKey, load]);
  useEffect(() => { if (!detail) return; const subscription = BackHandler.addEventListener('hardwareBackPress', () => { setDetail(null); return true; }); return () => subscription.remove(); }, [detail]);

  const openDetail = (item: SupporterCard) => {
    setDetail(item);
    void apiRequest<SupporterResponse>(`/supporters/${item.id}`)
      .then(response => setDetail(current => current?.id === item.id ? response.data : current))
      .catch(() => undefined);
  };
  const onViewableItemsChanged = useRef(({ viewableItems }: { viewableItems: ViewToken<SupporterCard>[] }) => {
    viewableItems.forEach(({ item, isViewable }) => { if (isViewable && !viewed.current.has(item.id)) { viewed.current.add(item.id); void recordEvent(item.id, 'impression'); } });
  }).current;
  const viewabilityConfig = useRef({ itemVisiblePercentThreshold: 60, minimumViewTime: 800 }).current;
  if (detail) return <Detail item={detail} contactUrl={contactUrl} back={() => setDetail(null)} />;

  const header = <View><View style={x.header}><Pressable accessibilityRole="button" accessibilityLabel="Ana sayfaya dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable><View style={x.headerCopy}><Text style={x.headerEyebrow}>DÖNGÜ DESTEKÇİLERİ</Text><Text style={x.headerTitle}>Bölgeni destekleyenler</Text></View></View><View style={x.hero}><View style={x.heroGlow} /><View style={x.heroIcon}><Text style={x.heroIconText}>♻</Text></View><Text style={x.heroKicker}>YAKININDAKİ DESTEKÇİLER</Text><Text style={x.heroTitle}>{locationLabel || 'Bölgen'}</Text><Text style={x.heroText}>Döngü'nün büyümesine katkı sağlayan işletmeleri keşfet, yerel ekonomiyi birlikte güçlendir.</Text></View><View style={x.sectionHeading}><View><Text style={x.sectionEyebrow}>DESTEKÇİ REHBERİ</Text><Text style={x.sectionTitle}>Bölgendeki işletmeler</Text></View></View></View>;
  return <View style={x.screen}><FlatList data={items} keyExtractor={item => String(item.id)} ListHeaderComponent={header} contentContainerStyle={x.content} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void load(1, 'refresh')} tintColor={C.green} />} onViewableItemsChanged={onViewableItemsChanged} viewabilityConfig={viewabilityConfig} onEndReached={() => { if (page < lastPage && !loadingMore) void load(page + 1, 'more'); }} onEndReachedThreshold={0.45} renderItem={({ item }) => <Pressable accessibilityRole="button" accessibilityLabel={`${item.name} profilini incele`} onPress={() => void openDetail(item)} style={({ pressed }) => [x.card, pressed && x.pressed]}><Logo item={item} /><View style={x.cardCopy}><View style={x.cardTitleRow}><Text style={x.cardTitle} numberOfLines={1}>{item.name}</Text><View style={x.supporterBadge}><Text style={x.supporterBadgeText}>DESTEKÇİ</Text></View></View><Text style={x.cardArea}>{item.areaLabel}</Text><Text style={x.cardDescription}>{item.summary}</Text><View style={x.cardFooter}><Text style={x.cardLink}>Profili incele  ›</Text></View></View></Pressable>} ListEmptyComponent={loading ? <SupporterSkeleton /> : <View style={x.empty}><Text style={x.emptyTitle}>{error ? 'Destekçiler yüklenemedi' : 'Bölgende henüz destekçi yok'}</Text><Text style={x.emptyText}>{error || 'Türkiye geneli destekçiler ve bölgesel işletmeler burada görünecek.'}</Text>{error ? <Pressable onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Yeniden dene</Text></Pressable> : null}</View>} ListFooterComponent={<View><HouseAdCard contactUrl={contactUrl} />{loadingMore && <ActivityIndicator style={{ marginTop: 14 }} color={C.green} />}</View>} /></View>;
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, pressed: { opacity: 0.84, transform: [{ scale: 0.99 }] }, dockClearance: { position: 'absolute', left: 0, right: 0, zIndex: 0, paddingHorizontal: 20 }, dock: { minHeight: 86, paddingHorizontal: 16, borderRadius: 22, backgroundColor: C.dark, flexDirection: 'row', alignItems: 'center' }, dockIcon: { width: 42, height: 42, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: C.lime }, dockIconText: { color: C.dark, fontSize: 24, lineHeight: 27, fontWeight: '900' }, dockCopy: { flex: 1, minWidth: 0, marginLeft: 9 }, dockEyebrow: { color: '#B8CEC1', fontSize: 10, letterSpacing: 0.9, fontWeight: '900' }, dockTitle: { color: C.white, fontSize: 16, lineHeight: 20, fontWeight: '900', marginTop: 2 }, dockAction: { minHeight: 36, minWidth: 64, flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end' }, dockActionText: { color: C.lime, fontSize: 12, fontWeight: '900' }, dockArrow: { color: C.lime, fontSize: 27, lineHeight: 29, marginLeft: 2 },
  content: { paddingBottom: 36, backgroundColor: C.bg }, header: { minHeight: 72, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line }, back: { width: 44, height: 44, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: C.soft, marginRight: 11 }, backText: { color: C.ink, fontSize: 34, lineHeight: 36 }, headerCopy: { flex: 1 }, headerEyebrow: { color: C.green, fontSize: 10, letterSpacing: 1.25, fontWeight: '900' }, headerTitle: { color: C.ink, fontSize: 19, lineHeight: 23, fontWeight: '900', marginTop: 2 },
  hero: { position: 'relative', overflow: 'hidden', marginHorizontal: 16, marginTop: 16, borderRadius: 25, padding: 20, backgroundColor: C.dark }, heroGlow: { position: 'absolute', width: 190, height: 190, borderRadius: 95, right: -78, top: -90, backgroundColor: '#347055', opacity: 0.72 }, heroIcon: { width: 48, height: 48, borderRadius: 17, alignItems: 'center', justifyContent: 'center', backgroundColor: C.lime }, heroIconText: { color: C.dark, fontSize: 26, fontWeight: '900' }, heroKicker: { color: C.lime, fontSize: 10, letterSpacing: 1.35, fontWeight: '900', marginTop: 17 }, heroTitle: { color: C.white, fontSize: 25, lineHeight: 30, fontWeight: '900', marginTop: 5 }, heroText: { color: '#C3D5CA', fontSize: 13, lineHeight: 20, marginTop: 9, maxWidth: 330 },
  sectionHeading: { paddingHorizontal: 18, marginTop: 23, marginBottom: 11 }, sectionEyebrow: { color: C.green, fontSize: 10, letterSpacing: 1.2, fontWeight: '900' }, sectionTitle: { color: C.ink, fontSize: 20, fontWeight: '900', marginTop: 3 }, card: { marginHorizontal: 16, marginBottom: 11, padding: 14, borderRadius: 21, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, flexDirection: 'row' }, logo: { width: 54, height: 54, borderRadius: 18, marginRight: 12 }, logoFallback: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#DCEFE3' }, logoText: { color: C.dark, fontSize: 15, fontWeight: '900' }, logoLarge: { width: 76, height: 76, borderRadius: 23, marginRight: 0 }, logoTextLarge: { fontSize: 22 }, cardCopy: { flex: 1, minWidth: 0 }, cardTitleRow: { flexDirection: 'row', alignItems: 'center', gap: 7 }, cardTitle: { flex: 1, color: C.ink, fontSize: 15, fontWeight: '900' }, supporterBadge: { borderRadius: 8, backgroundColor: C.soft, paddingHorizontal: 7, paddingVertical: 4 }, supporterBadgeText: { color: C.green, fontSize: 10, letterSpacing: 0.65, fontWeight: '900' }, cardArea: { color: C.green, fontSize: 11, fontWeight: '800', marginTop: 4 }, cardDescription: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 7 }, cardFooter: { alignItems: 'flex-end', marginTop: 11, paddingTop: 10, borderTopWidth: 1, borderTopColor: C.line }, cardLink: { color: C.green, fontSize: 11, fontWeight: '900' },
  skeletonCard: { marginHorizontal: 16, marginBottom: 11, minHeight: 96, padding: 14, borderRadius: 21, backgroundColor: '#EDF2EE', flexDirection: 'row' }, skeletonLogo: { width: 54, height: 54, borderRadius: 18, backgroundColor: '#DDE6DF' }, skeletonCopy: { flex: 1, marginLeft: 12, paddingTop: 3 }, skeletonTitle: { width: '58%', height: 13, borderRadius: 7, backgroundColor: '#D5DFD7' }, skeletonLine: { width: '86%', height: 10, borderRadius: 5, backgroundColor: '#DDE5DF', marginTop: 11 }, skeletonLineShort: { width: '66%', height: 10, borderRadius: 5, backgroundColor: '#DDE5DF', marginTop: 8 }, empty: { marginHorizontal: 16, padding: 24, borderRadius: 21, alignItems: 'center', backgroundColor: C.white, borderWidth: 1, borderColor: C.line }, emptyTitle: { color: C.ink, fontSize: 15, fontWeight: '900' }, emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 6 }, retry: { marginTop: 14, borderRadius: 12, backgroundColor: C.green, paddingHorizontal: 16, paddingVertical: 10 }, retryText: { color: C.white, fontSize: 12, fontWeight: '900' }, joinCard: { marginHorizontal: 16, marginTop: 10, borderRadius: 21, padding: 19, backgroundColor: '#E9F4EC', borderWidth: 1, borderColor: '#CDE4D2' }, joinEyebrow: { color: C.green, fontSize: 10, letterSpacing: 1, fontWeight: '900' }, joinTitle: { color: C.ink, fontSize: 18, fontWeight: '900', marginTop: 5 }, joinText: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 7 }, joinLink: { color: C.green, fontSize: 12, fontWeight: '900', marginTop: 12 },
  detailContent: { paddingBottom: 35, backgroundColor: C.bg, flexGrow: 1 }, detailHero: { alignItems: 'center', margin: 16, borderRadius: 25, padding: 24, backgroundColor: C.dark }, detailBadge: { marginTop: 15, borderRadius: 9, backgroundColor: 'rgba(205,234,121,.15)', paddingHorizontal: 9, paddingVertical: 6 }, detailBadgeText: { color: C.lime, fontSize: 10, letterSpacing: 1, fontWeight: '900' }, detailName: { color: C.white, fontSize: 25, lineHeight: 31, fontWeight: '900', textAlign: 'center', marginTop: 10 }, detailArea: { color: '#C3D5CA', fontSize: 12, fontWeight: '700', marginTop: 5 }, detailCard: { marginHorizontal: 16, borderRadius: 22, padding: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.line }, detailTitle: { color: C.ink, fontSize: 19, lineHeight: 25, fontWeight: '900' }, detailBody: { color: C.muted, fontSize: 14, lineHeight: 22, marginTop: 10 }, cta: { minHeight: 54, marginHorizontal: 16, marginTop: 14, borderRadius: 17, paddingHorizontal: 20, backgroundColor: C.green, flexDirection: 'row', alignItems: 'center', justifyContent: 'center' }, ctaText: { color: C.white, fontSize: 14, fontWeight: '900' }, ctaArrow: { color: C.white, fontSize: 27, marginLeft: 6 }, externalNote: { color: C.muted, fontSize: 10, lineHeight: 15, textAlign: 'center', marginTop: 9, paddingHorizontal: 24 }, detailLoading: { position: 'absolute', left: 24, right: 24, top: '45%', minHeight: 70, borderRadius: 18, backgroundColor: 'rgba(13,69,47,.94)', flexDirection: 'row', alignItems: 'center', justifyContent: 'center' }, detailLoadingText: { color: C.white, fontSize: 13, fontWeight: '800', marginLeft: 10 },
});
