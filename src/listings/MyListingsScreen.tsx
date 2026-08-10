import React, { memo, useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { Listing, listingCount, listingPrice, money } from '../../marketplace';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import RewardedListingBoostButton from '../advertising/RewardedListingBoostButton';
import MonetizedAdSlot from '../advertising/MonetizedAdSlot';
import { insertAdvertisementSlots } from '../advertising/listSlots';
import { useAdvertisements } from '../advertising/useAdvertisements';

type Scope = 'active' | 'history';
type ListingCollectionResponse = {
  data: Listing[];
  meta: { current_page: number; last_page: number; total: number };
  summary: { active: number; history: number };
};
type ListingResponse = { data: Listing };

const statusLabels = {
  published: 'Yayında',
  reserved: 'Rezerve',
  completed: 'Tamamlandı',
  removed: 'Kaldırıldı',
  expired: 'Süresi doldu',
} as const;

export default function MyListingsScreen({ token, userId, back, openListing, createListing, onListingUpdated, onListingRemoved }: {
  token: string;
  userId: string;
  back: () => void;
  openListing: (listing: Listing) => void;
  createListing: () => void;
  onListingUpdated: (listing: Listing) => void;
  onListingRemoved: (listingId: number) => void;
}) {
  const { showNotice, confirmNotice } = useNotice();
  const [scope, setScope] = useState<Scope>('active');
  const [summary, setSummary] = useState({ active: 0, history: 0 });
  const [listings, setListings] = useState<Listing[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [pendingIds, setPendingIds] = useState<Set<number>>(new Set());
  const [page, setPage] = useState(0);
  const [lastPage, setLastPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState('');
  const requestId = useRef(0);

  const load = useCallback(async (nextPage = 1, mode: 'replace' | 'refresh' | 'more' = 'replace') => {
    const currentRequest = ++requestId.current;
    if (mode === 'replace') setLoading(true);
    if (mode === 'refresh') setRefreshing(true);
    if (mode === 'more') setLoadingMore(true);
    try {
      const response = await apiRequest<ListingCollectionResponse>(`/my/listings?scope=${scope}&page=${nextPage}&per_page=20`, { token });
      if (requestId.current !== currentRequest) return;
      setListings(current => mode === 'more'
        ? [...current, ...response.data.filter(item => !current.some(known => known.id === item.id))]
        : response.data);
      setPage(response.meta.current_page);
      setLastPage(response.meta.last_page);
      setSummary(response.summary);
      setTotal(response.meta.total);
      setError('');
    } catch (loadError) {
      if (requestId.current !== currentRequest) return;
      if (mode === 'replace') {
        setListings([]);
        setError(loadError instanceof ApiError ? loadError.message : 'İlanlarına ulaşılamadı.');
      } else {
        showNotice({ tone: 'error', title: 'İlanlar yenilenemedi', message: 'Mevcut kayıtları göstermeye devam ediyoruz.' });
      }
    } finally {
      if (requestId.current === currentRequest) {
        if (mode === 'replace') setLoading(false);
        if (mode === 'refresh') setRefreshing(false);
        if (mode === 'more') setLoadingMore(false);
      }
    }
  }, [scope, showNotice, token]);

  useEffect(() => { setListings([]); void load(); }, [load]);

  const withPending = async (listingId: number, action: () => Promise<void>) => {
    if (pendingIds.has(listingId)) return;
    setPendingIds(current => new Set(current).add(listingId));
    try { await action(); } finally {
      setPendingIds(current => { const next = new Set(current); next.delete(listingId); return next; });
    }
  };

  const renew = (listing: Listing) => withPending(listing.id, async () => {
    try {
      const response = await apiRequest<ListingResponse>(`/listings/${listing.id}/renew`, { method: 'POST', token });
      if (scope === 'history') {
        setListings(current => current.filter(item => item.id !== listing.id));
        setTotal(current => Math.max(0, current - 1));
        setSummary(current => ({ active: current.active + 1, history: Math.max(0, current.history - 1) }));
      } else {
        setListings(current => current.map(item => item.id === listing.id ? response.data : item));
      }
      onListingUpdated(response.data);
      showNotice({ tone: 'success', title: 'İlanın yenilendi', message: `İlanın ${response.data.expiresInDays ?? 30} gün daha yayında kalacak.` });
    } catch (error) {
      showNotice({ tone: 'error', title: 'İlan yenilenemedi', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    }
  });

  const remove = async (listing: Listing) => {
    const approved = await confirmNotice({
      tone: 'warning', title: 'İlanı kaldırmak istiyor musun?',
      message: 'Bu ilan ana akıştan kaldırılacak. Devam eden bir rezervasyon varsa önce görüşmeyi sonuçlandırmalısın.',
      primaryLabel: 'İlanı kaldır', secondaryLabel: 'Vazgeç',
    });
    if (!approved) return;
    await withPending(listing.id, async () => {
      try {
        await apiRequest(`/listings/${listing.id}`, { method: 'DELETE', token });
        setListings(current => current.filter(item => item.id !== listing.id));
        onListingRemoved(listing.id);
        setTotal(current => Math.max(0, current - 1));
        setSummary(current => ({ active: Math.max(0, current.active - 1), history: current.history + 1 }));
        showNotice({ tone: 'success', title: 'İlan kaldırıldı', message: 'İlan artık ana akışta gösterilmiyor.' });
      } catch (error) {
        showNotice({ tone: 'error', title: 'İlan kaldırılamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
      }
    });
  };

  const advertisementCollection = useAdvertisements('my_listings', token);
  const listData = insertAdvertisementSlots(listings, item => String(item.id), advertisementCollection?.meta);

  return (
    <View style={x.screen}>
      <View style={x.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Profile dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable>
        <View style={x.headerCopy}><Text style={x.eyebrow}>YAYIN YÖNETİMİ</Text><Text style={x.title}>İlanlarım</Text></View>
        <View style={x.totalBadge}><Text style={x.totalText}>{total}</Text></View>
      </View>
      <View accessibilityRole="tablist" style={x.tabs}>
        <ScopeTab label="Aktif" count={summary.active} selected={scope === 'active'} onPress={() => setScope('active')} />
        <ScopeTab label="Geçmiş" count={summary.history} selected={scope === 'history'} onPress={() => setScope('history')} />
      </View>
      <FlatList
        data={listData}
        keyExtractor={item => item.key}
        renderItem={({ item }) => item.kind === 'advertisement'
          ? <MonetizedAdSlot placement="my_listings" token={token} slotIndex={item.slotIndex} itemCount={listings.length} />
          : (
          <MyListingCard
            listing={item.item}
            pending={pendingIds.has(item.item.id)}
            open={() => openListing(item.item)}
            renew={() => void renew(item.item)}
            remove={() => void remove(item.item)}
            token={token}
            userId={userId}
            onBoosted={boosted => {
              setListings(current => current.map(listing => listing.id === boosted.id ? boosted : listing));
              onListingUpdated(boosted);
            }}
          />
        )}
        contentContainerStyle={listings.length ? x.list : x.emptyList}
        refreshing={refreshing}
        onRefresh={() => void load(1, 'refresh')}
        onEndReached={() => { if (!loading && !refreshing && !loadingMore && page < lastPage) void load(page + 1, 'more'); }}
        onEndReachedThreshold={0.35}
        ListEmptyComponent={loading ? (
          <View style={x.empty}><ActivityIndicator color={C.green} /><Text style={x.emptyTitle}>İlanların yükleniyor</Text></View>
        ) : error ? (
          <View style={x.empty}><Text style={x.emptyTitle}>İlanların alınamadı</Text><Text style={x.emptyText}>{error}</Text><Pressable accessibilityRole="button" onPress={() => void load()} style={x.primary}><Text style={x.primaryText}>Tekrar dene</Text></Pressable></View>
        ) : (
          <View style={x.empty}>
            <Text style={x.emptyIcon}>{scope === 'active' ? '♻' : '✓'}</Text>
            <Text style={x.emptyTitle}>{scope === 'active' ? 'Aktif ilanın yok' : 'İlan geçmişin henüz boş'}</Text>
            <Text style={x.emptyText}>{scope === 'active' ? 'Yeni bir ilan oluşturduğunda yayın ve rezervasyon sürecini buradan yönetebilirsin.' : 'Tamamlanan, kaldırılan ve süresi dolan ilanların burada saklanacak.'}</Text>
            {scope === 'active' && <Pressable accessibilityRole="button" onPress={createListing} style={x.primary}><Text style={x.primaryText}>İlan oluştur</Text></Pressable>}
          </View>
        )}
        ListFooterComponent={loadingMore ? <ActivityIndicator color={C.green} style={x.footer} /> : null}
        initialNumToRender={6}
        maxToRenderPerBatch={6}
        windowSize={7}
        removeClippedSubviews={Platform.OS === 'android'}
      />
    </View>
  );
}

function ScopeTab({ label, count, selected, onPress }: { label: string; count: number; selected: boolean; onPress: () => void }) {
  return <Pressable accessibilityRole="tab" accessibilityState={{ selected }} onPress={onPress} style={[x.tab, selected && x.tabActive]}><Text style={[x.tabText, selected && x.tabTextActive]}>{label} ({count})</Text></Pressable>;
}

const MyListingCard = memo(function MyListingCard({ listing, token, userId, pending, open, renew, remove, onBoosted }: {
  listing: Listing; token: string; userId: string; pending: boolean; open: () => void; renew: () => void; remove: () => void; onBoosted: (listing: Listing) => void;
}) {
  const ownerState = listing.ownerState ?? (listing.status === 'reserved' ? 'reserved' : listing.status === 'completed' ? 'completed' : listing.status === 'cancelled' ? 'removed' : 'published');
  const active = ownerState === 'published';
  const renewable = (active && listing.expiresInDays !== null && listing.expiresInDays !== undefined && listing.expiresInDays <= 7) || ownerState === 'expired';
  const expiresSoon = active && renewable;
  const materials = listing.items.map(item => `${item.material} ${item.count} adet`).join(' · ');
  return (
    <View style={x.card}>
      <View style={x.cardTop}><View style={[x.status, active ? x.statusActive : x.statusMuted]}><Text style={[x.statusText, active ? x.statusTextActive : x.statusTextMuted]}>{statusLabels[ownerState]}</Text></View><Text style={x.time}>{listing.time}</Text></View>
      <Text style={x.count}>{listingCount(listing)} adet ambalaj</Text><Text style={x.materials}>{materials}</Text>
      <View style={x.summary}><View><Text style={x.summaryLabel}>Toplam fiyat</Text><Text style={x.summaryValue}>{money(listingPrice(listing))}</Text></View><View style={x.summaryRight}><Text style={x.summaryLabel}>Yayın süresi</Text><Text style={[x.expiry, expiresSoon && x.expiryWarning]}>{active ? listing.expiresInDays === null || listing.expiresInDays === undefined ? 'Süresiz' : `${listing.expiresInDays} gün kaldı` : statusLabels[ownerState]}</Text></View></View>
      <Text style={x.area}>● {listing.district}</Text>
      {pending ? <ActivityIndicator color={C.green} style={x.pending} /> : (
        <View style={x.actions}>
          {active && <Pressable accessibilityRole="button" onPress={open} style={x.secondary}><Text style={x.secondaryText}>İncele</Text></Pressable>}
          {renewable && <Pressable accessibilityRole="button" accessibilityHint="İlanın yayın süresini uzatır" onPress={renew} style={x.secondary}><Text style={x.secondaryText}>Yenile</Text></Pressable>}
          {active && <Pressable accessibilityRole="button" onPress={remove} style={x.dangerButton}><Text style={x.dangerText}>Kaldır</Text></Pressable>}
        </View>
      )}
      {active && <RewardedListingBoostButton listing={listing} token={token} userId={userId} onBoosted={onBoosted} />}
    </View>
  );
});

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, header: { minHeight: 82, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line }, back: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg }, backText: { color: C.ink, fontSize: 30, lineHeight: 33 }, headerCopy: { flex: 1, marginLeft: 12 }, eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.4, fontWeight: '900' }, title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 }, totalBadge: { minWidth: 38, minHeight: 38, paddingHorizontal: 8, borderRadius: 19, backgroundColor: C.soft, alignItems: 'center', justifyContent: 'center' }, totalText: { color: C.green, fontSize: 13, fontWeight: '900' },
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: 16, paddingVertical: 12, backgroundColor: C.white },
  tab: { flex: 1, minHeight: 44, borderRadius: 14, backgroundColor: '#F2F5F1', alignItems: 'center', justifyContent: 'center' },
  tabActive: { backgroundColor: C.dark },
  tabText: { color: C.muted, fontSize: 12, fontWeight: '900' },
  tabTextActive: { color: C.white },
  list: { padding: 16, paddingBottom: 28 }, emptyList: { flexGrow: 1, padding: 20 }, card: { padding: 16, marginBottom: 12, borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.line }, cardTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 10 }, status: { minHeight: 28, paddingHorizontal: 10, borderRadius: 14, justifyContent: 'center' }, statusActive: { backgroundColor: C.soft }, statusMuted: { backgroundColor: '#F0F2F0' }, statusText: { fontSize: 12, fontWeight: '900' }, statusTextActive: { color: C.green }, statusTextMuted: { color: C.muted }, time: { color: C.muted, fontSize: 12 }, count: { color: C.ink, fontSize: 19, fontWeight: '900', marginTop: 13 }, materials: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 4 }, summary: { flexDirection: 'row', justifyContent: 'space-between', gap: 16, marginTop: 15, padding: 13, borderRadius: 14, backgroundColor: '#F7F9F6' }, summaryRight: { alignItems: 'flex-end' }, summaryLabel: { color: C.muted, fontSize: 11, fontWeight: '700' }, summaryValue: { color: C.ink, fontSize: 16, fontWeight: '900', marginTop: 3 }, expiry: { color: C.green, fontSize: 13, fontWeight: '900', marginTop: 3 }, expiryWarning: { color: '#A55A21' }, area: { color: C.muted, fontSize: 12, marginTop: 12 }, actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 15 }, secondary: { minHeight: 44, paddingHorizontal: 16, borderRadius: 13, borderWidth: 1, borderColor: '#B8D2BF', justifyContent: 'center' }, secondaryText: { color: C.green, fontSize: 12, fontWeight: '900' }, dangerButton: { minHeight: 44, paddingHorizontal: 16, borderRadius: 13, borderWidth: 1, borderColor: '#E1B9B2', justifyContent: 'center' }, dangerText: { color: '#9A4034', fontSize: 12, fontWeight: '900' }, pending: { marginTop: 17 },
  empty: { flex: 1, minHeight: 320, alignItems: 'center', justifyContent: 'center', padding: 28, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.line }, emptyIcon: { color: C.green, fontSize: 48 }, emptyTitle: { color: C.ink, fontSize: 17, fontWeight: '900', marginTop: 10 }, emptyText: { color: C.muted, fontSize: 12, lineHeight: 19, textAlign: 'center', marginTop: 7 }, primary: { minHeight: 46, marginTop: 17, paddingHorizontal: 20, borderRadius: 14, backgroundColor: C.green, justifyContent: 'center' }, primaryText: { color: C.white, fontSize: 12, fontWeight: '900' }, footer: { marginVertical: 20 },
});
