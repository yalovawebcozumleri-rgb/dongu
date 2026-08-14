import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { Listing } from '../../marketplace';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import ListingCard from '../listings/ListingCard';
import MonetizedAdSlot from '../advertising/MonetizedAdSlot';

import { insertAdvertisementSlots } from '../advertising/listSlots';
import { useAdvertisements } from '../advertising/useAdvertisements';
import { useNativeAdSessionKey, useNativeAdSessionPreload } from '../advertising/useNativeAdSession';
type FavoriteResponse = {
  data: Listing[];
  meta: { current_page: number; last_page: number; total: number };
};

export default function FavoritesScreen({
  token,
  userId,
  back,
  openListing,
  toggleFavorite,
  pendingIds,
}: {
  token: string;
  userId: string;
  back: () => void;
  openListing: (listing: Listing) => void;
  toggleFavorite: (listing: Listing) => Promise<boolean>;
  pendingIds: Set<number>;
}) {
  const [listings, setListings] = useState<Listing[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [adSessionGeneration, setAdSessionGeneration] = useState(0);
  const [loadingMore, setLoadingMore] = useState(false);
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
      const response = await apiRequest<FavoriteResponse>(`/favorites?page=${nextPage}&per_page=20`, { token });
      if (currentRequest !== requestId.current) return;
      setListings(current => mode === 'more'
        ? [...current, ...response.data.filter(item => !current.some(known => known.id === item.id))]
        : response.data);
      setPage(response.meta.current_page);
      setLastPage(response.meta.last_page);
      setTotal(response.meta.total);
      setError('');
    } catch (loadError) {
      if (currentRequest !== requestId.current) return;
      if (mode === 'replace') {
        setListings([]);
        setError(loadError instanceof ApiError ? loadError.message : 'Favorilere ulaşılamadı.');
      }
    } finally {
      if (currentRequest === requestId.current) {
        if (mode === 'replace') setLoading(false);
        if (mode === 'refresh') setRefreshing(false);
        if (mode === 'more') setLoadingMore(false);
      }
    }
  }, [token]);

  useEffect(() => { void load(); }, [load]);

  const advertisementCollection = useAdvertisements('favorites', token);
  const adSessionKey = useNativeAdSessionKey('favorites', adSessionGeneration);
  useNativeAdSessionPreload(adSessionKey, advertisementCollection, listings.length);
  const listData = insertAdvertisementSlots(listings, item => String(item.id), advertisementCollection?.meta);

  const remove = async (listing: Listing) => {
    if (!await toggleFavorite(listing)) return;
    setListings(current => current.filter(item => item.id !== listing.id));
    setTotal(current => Math.max(0, current - 1));
  };

  return (
    <View style={x.screen}>
      <View style={x.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Profile dön" onPress={back} style={x.back}>
          <Text style={x.backText}>‹</Text>
        </Pressable>
        <View style={x.headerCopy}>
          <Text style={x.eyebrow}>KAYDETTİKLERİN</Text>
          <Text style={x.title}>Favorilerim</Text>
        </View>
        <View style={x.totalBadge}><Text style={x.totalText}>{total}</Text></View>
      </View>
      <FlatList
        data={listData}
        keyExtractor={item => item.key}
        renderItem={({ item }) => item.kind === 'advertisement'
          ? <MonetizedAdSlot placement="favorites" token={token} slotIndex={item.slotIndex} itemCount={listings.length} sessionKey={adSessionKey} style={x.adSlot} />
          : <ListingCard
              item={item.item}
              center={null}
              isOwn={String(item.item.sellerId) === userId}
              open={() => openListing(item.item)}
              favoritePending={pendingIds.has(item.item.id)}
              toggleFavorite={() => void remove(item.item)}
            />
        }
        contentContainerStyle={listings.length ? x.list : x.emptyList}
        refreshing={refreshing}
        onRefresh={() => { setAdSessionGeneration(current => current + 1); void load(1, 'refresh'); }}
        onEndReached={() => {
          if (!loading && !refreshing && !loadingMore && page < lastPage) void load(page + 1, 'more');
        }}
        onEndReachedThreshold={0.35}
        ListEmptyComponent={loading ? (
          <View style={x.empty}><ActivityIndicator color={C.green} /><Text style={x.emptyTitle}>Favorilerin yükleniyor</Text></View>
        ) : error ? (
          <View style={x.empty}><Text style={x.emptyTitle}>Favoriler alınamadı</Text><Text style={x.emptyText}>{error}</Text><Pressable onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Tekrar dene</Text></Pressable></View>
        ) : (
          <View style={x.empty}><Text style={x.emptyIcon}>♡</Text><Text style={x.emptyTitle}>Henüz favorin yok</Text><Text style={x.emptyText}>Ana sayfadaki kalp simgesine dokunduğun ilanlar burada görünecek.</Text></View>
        )}
        ListFooterComponent={loadingMore ? <ActivityIndicator color={C.green} style={x.footer} /> : null}
        initialNumToRender={6}
        maxToRenderPerBatch={6}
        windowSize={7}
        showsVerticalScrollIndicator={false}
      />
    </View>
  );
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  header: { minHeight: 82, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg },
  backText: { color: C.ink, fontSize: 30, lineHeight: 33 },
  headerCopy: { flex: 1, marginLeft: 12 },
  eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.4, fontWeight: '900' },
  title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 },
  totalBadge: { minWidth: 38, height: 38, paddingHorizontal: 8, borderRadius: 19, backgroundColor: C.soft, alignItems: 'center', justifyContent: 'center' },
  totalText: { color: C.green, fontSize: 13, fontWeight: '900' },
  list: { paddingTop: 16, paddingBottom: 28 },
  emptyList: { flexGrow: 1, padding: 20 },
  empty: { flex: 1, minHeight: 300, alignItems: 'center', justifyContent: 'center', padding: 28, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.line },
  emptyIcon: { color: C.green, fontSize: 50, marginBottom: 10 },
  emptyTitle: { color: C.ink, fontSize: 17, fontWeight: '900', marginTop: 10 },
  emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 7 },
  retry: { minHeight: 42, marginTop: 16, paddingHorizontal: 18, borderRadius: 14, backgroundColor: C.green, justifyContent: 'center' },
  retryText: { color: C.white, fontSize: 12, fontWeight: '900' },
  adSlot: { marginTop: 4, marginBottom: 16 },
  footer: { marginVertical: 20 },
});
