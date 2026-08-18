import React, { memo, useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { money } from '../../marketplace';
import { C } from '../../styles';
import { Conversation } from '../chat/types';
import UserAvatar from '../profile/UserAvatar';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import TransactionDetailScreen from './TransactionDetailScreen';
import MonetizedAdSlot from '../advertising/MonetizedAdSlot';
import SponsoredBannerSlot from '../advertising/SponsoredBannerSlot';
import { countAdvertisementSlots, insertAdvertisementSlots } from '../advertising/listSlots';
import { useAdvertisements } from '../advertising/useAdvertisements';
import { useNativeAdSessionKey, useNativeAdSessionPreload } from '../advertising/useNativeAdSession';

type Scope = 'active' | 'history';
type Response = {
  data: Conversation[] | null;
  meta: { current_page: number; last_page: number; total: number } | null;
  summary: { active: number; history: number } | null;
};

const statusLabels: Record<Conversation['status'], string> = {
  inquiry: 'Görüşme', pending: 'Satıcı yanıtı bekleniyor', accepted: 'Teslimat için rezerve',
  rejected: 'Talep reddedildi', cancelled: 'Talep geri çekildi', completed: 'Teslimat tamamlandı', closed: 'Görüşme kapandı',
};

export default function PurchaseHistoryScreen({ token, back, openConversation }: {
  token: string;
  back: () => void;
  openConversation: (conversation: Conversation) => void;
}) {
  const { showNotice } = useNotice();
  const [scope, setScope] = useState<Scope>('active');
  const [selected, setSelected] = useState<Conversation | null>(null);
  const [items, setItems] = useState<Conversation[]>([]);
  const [summary, setSummary] = useState({ active: 0, history: 0 });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [adSessionGeneration, setAdSessionGeneration] = useState(0);
  const [loadingMore, setLoadingMore] = useState(false);
  const [page, setPage] = useState(0);
  const [lastPage, setLastPage] = useState(0);
  const [error, setError] = useState('');
  const requestId = useRef(0);

  const load = useCallback(async (nextPage = 1, mode: 'replace' | 'refresh' | 'more' = 'replace') => {
    const currentRequest = ++requestId.current;
    if (mode === 'replace') setLoading(true);
    if (mode === 'refresh') setRefreshing(true);
    if (mode === 'more') setLoadingMore(true);
    try {
      const response = await apiRequest<Response>(`/my/pickup-requests?scope=${scope}&page=${nextPage}&per_page=20`, { token });
      if (currentRequest !== requestId.current) return;
      const receivedItems = Array.isArray(response.data) ? response.data.filter(Boolean) : [];
      setItems(current => mode === 'more'
        ? [...current, ...receivedItems.filter(item => !current.some(known => known.id === item.id))]
        : receivedItems);
      setSummary({ active: response.summary?.active ?? 0, history: response.summary?.history ?? 0 });
      setPage(response.meta?.current_page ?? nextPage);
      setLastPage(response.meta?.last_page ?? nextPage);
      setError('');
    } catch (loadError) {
      if (currentRequest !== requestId.current) return;
      if (mode === 'replace') {
        setItems([]);
        setError(loadError instanceof ApiError ? loadError.message : 'Alım taleplerine ulaşılamadı.');
      } else {
        showNotice({ tone: 'error', title: 'İşlemler yenilenemedi', message: 'Mevcut kayıtları göstermeye devam ediyoruz.' });
      }
    } finally {
      if (currentRequest === requestId.current) {
        if (mode === 'replace') setLoading(false);
        if (mode === 'refresh') setRefreshing(false);
        if (mode === 'more') setLoadingMore(false);
      }
    }
  }, [scope, showNotice, token]);

  useEffect(() => { setItems([]); void load(); }, [load]);

  const placement = scope === 'active' ? 'purchase_requests' : 'transaction_history';
  const advertisementCollection = useAdvertisements(placement, token);
  const adSessionKey = useNativeAdSessionKey(placement, adSessionGeneration);
  useNativeAdSessionPreload(adSessionKey, advertisementCollection, items.length);
  const listData = insertAdvertisementSlots(items, item => String(item.id), advertisementCollection?.meta);

  if (selected) {
    return (
      <TransactionDetailScreen
        item={selected}
        token={token}
        back={() => setSelected(null)}
        openMessages={openConversation}
        onUpdated={updated => {
          setSelected(updated);
          setItems(current => current.map(item => item.id === updated.id ? updated : item));
        }}
      />
    );
  }

  return (
    <View style={x.screen}>
      <View style={x.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Profile dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable>
        <View style={x.headerCopy}><Text style={x.eyebrow}>İŞLEM TAKİBİ</Text><Text style={x.title}>Alım taleplerim</Text></View>
      </View>
      <View accessibilityRole="tablist" style={x.tabs}>
        <ScopeTab label="Aktif" count={summary.active} selected={scope === 'active'} onPress={() => setScope('active')} />
        <ScopeTab label="Geçmiş" count={summary.history} selected={scope === 'history'} onPress={() => setScope('history')} />
      </View>
      <FlatList
        data={listData}
        keyExtractor={item => item.key}
        renderItem={({ item }) => item.kind === 'advertisement'
          ? <MonetizedAdSlot placement={placement} token={token} slotIndex={item.slotIndex} itemCount={items.length} sessionKey={adSessionKey} />
          : <TransactionCard item={item.item} scope={scope} open={() => scope === 'active' ? openConversation(item.item) : setSelected(item.item)} />}
        ListHeaderComponent={<SponsoredBannerSlot placement={placement} token={token} sessionKey={adSessionKey} />}
        contentContainerStyle={items.length ? x.list : x.emptyList}
        refreshing={refreshing}
        onRefresh={() => { setAdSessionGeneration(current => current + 1); void load(1, 'refresh'); }}
        onEndReached={() => { if (!loading && !refreshing && !loadingMore && page < lastPage) void load(page + 1, 'more'); }}
        onEndReachedThreshold={0.35}
        ListEmptyComponent={loading ? (
          <View style={x.empty}><ActivityIndicator color={C.green} /><Text style={x.emptyTitle}>İşlemler yükleniyor</Text></View>
        ) : error ? (
          <View style={x.empty}><Text style={x.emptyTitle}>İşlemler alınamadı</Text><Text style={x.emptyText}>{error}</Text><Pressable accessibilityRole="button" onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Tekrar dene</Text></Pressable></View>
        ) : (
          <View style={x.empty}><Text style={x.emptyIcon}>{scope === 'active' ? '◎' : '✓'}</Text><Text style={x.emptyTitle}>{scope === 'active' ? 'Aktif alım talebin yok' : 'İşlem geçmişin henüz boş'}</Text><Text style={x.emptyText}>{scope === 'active' ? 'Bir ilan için “Almak istiyorum” dediğinde süreci buradan takip edebilirsin.' : 'Sonuçlanan alım taleplerin ve tamamlanan teslimatların burada saklanacak.'}</Text></View>
        )}
        ListFooterComponent={!loading && !error && !items.length && countAdvertisementSlots(0, advertisementCollection?.meta) > 0 ? <MonetizedAdSlot placement={placement} token={token} slotIndex={1} itemCount={0} sessionKey={adSessionKey} /> : loadingMore ? <ActivityIndicator color={C.green} style={x.footer} /> : null}
        initialNumToRender={7}
        maxToRenderPerBatch={7}
        windowSize={7}
        removeClippedSubviews={Platform.OS === 'android'}
      />
    </View>
  );
}

function ScopeTab({ label, count, selected, onPress }: { label: string; count: number; selected: boolean; onPress: () => void }) {
  return <Pressable accessibilityRole="tab" accessibilityState={{ selected }} onPress={onPress} style={[x.tab, selected && x.tabActive]}><Text style={[x.tabText, selected && x.tabTextActive]}>{label} ({count})</Text></Pressable>;
}

const TransactionCard = memo(function TransactionCard({ item, scope, open }: { item: Conversation; scope: Scope; open: () => void }) {
  const date = new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(item.updatedAt));
  const completed = item.status === 'completed';
  const listing = item.listing ?? item.listingSummary;
  const materials = Array.isArray(listing?.items) ? listing.items : [];
  const itemCount = materials.reduce((total, material) => total + material.count, 0);
  const totalPrice = materials.reduce((total, material) => total + material.count * material.unitPrice, 0);
  const statusLabel = item.status === 'cancelled' && item.cancelledByRole === 'seller'
    ? 'Satıcı rezervasyonu iptal etti'
    : statusLabels[item.status];
  return (
    <View style={x.card}>
      <View style={x.cardTop}><View style={[x.status, completed && x.statusCompleted]}><Text style={[x.statusText, completed && x.statusTextCompleted]}>{statusLabel}</Text></View><Text style={x.date}>{date}</Text></View>
      <View style={x.personRow}><UserAvatar uri={item.counterpart.avatarUrl} name={item.counterpart.name} size={42} /><View style={x.personCopy}><Text style={x.personLabel}>SATICI</Text><Text style={x.personName}>{item.counterpart.name}</Text></View>{item.unreadCount > 0 && <View style={x.unread}><Text style={x.unreadText}>{item.unreadCount}</Text></View>}</View>
      <Text style={x.listingTitle}>{itemCount} adet ambalaj</Text><Text style={x.materials}>{listing?.items.map(material => `${material.material} ${material.count}`).join(' · ') || 'İlan özeti bulunmuyor'}</Text>
      <View style={x.summary}><View><Text style={x.summaryLabel}>İlan bedeli</Text><Text style={x.summaryValue}>{money(totalPrice)}</Text></View><View style={x.areaCopy}><Text style={x.summaryLabel}>Bölge</Text><Text style={x.area} numberOfLines={2}>{listing?.district || '—'}</Text></View></View>
      {item.canReview && <View style={x.reviewNotice}><Text style={x.reviewNoticeText}>Değerlendirme için 24 saatlik süren devam ediyor.</Text></View>}
      <Pressable accessibilityRole="button" accessibilityHint={scope === 'active' ? 'İlgili canlı sohbeti açar' : 'İşlem kaydını ve varsa mesaj geçmişini açar'} onPress={open} style={x.openButton}>
        <Text style={x.openButtonText}>{scope === 'active' ? 'Sohbete git' : item.canReview ? 'Değerlendir' : 'İşlem detayını gör'}  ›</Text>
      </Pressable>
    </View>
  );
});

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, header: { minHeight: 82, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line }, back: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg }, backText: { color: C.ink, fontSize: 30, lineHeight: 33 }, headerCopy: { flex: 1, marginLeft: 12 }, eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.4, fontWeight: '900' }, title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 }, tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: 16, paddingVertical: 12, backgroundColor: C.white }, tab: { flex: 1, minHeight: 44, borderRadius: 14, backgroundColor: '#F2F5F1', alignItems: 'center', justifyContent: 'center' }, tabActive: { backgroundColor: C.dark }, tabText: { color: C.muted, fontSize: 12, fontWeight: '900' }, tabTextActive: { color: C.white },
  list: { padding: 16, paddingBottom: 28 }, emptyList: { flexGrow: 1, padding: 20 }, card: { padding: 16, marginBottom: 12, borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.line }, cardTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 }, status: { minHeight: 28, paddingHorizontal: 10, borderRadius: 14, backgroundColor: C.soft, justifyContent: 'center' }, statusCompleted: { backgroundColor: '#E8F1E9' }, statusText: { color: C.green, fontSize: 12, fontWeight: '900' }, statusTextCompleted: { color: C.dark }, date: { color: C.muted, fontSize: 12 }, personRow: { flexDirection: 'row', alignItems: 'center', marginTop: 14 }, avatar: { width: 42, height: 42, borderRadius: 21, backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center' }, avatarText: { color: C.dark, fontSize: 16, fontWeight: '900' }, personCopy: { flex: 1, marginLeft: 10 }, personLabel: { color: C.muted, fontSize: 10, letterSpacing: 1, fontWeight: '900' }, personName: { color: C.ink, fontSize: 14, fontWeight: '900', marginTop: 2 }, unread: { minWidth: 24, height: 24, paddingHorizontal: 6, borderRadius: 12, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center' }, unreadText: { color: C.white, fontSize: 12, fontWeight: '900' }, listingTitle: { color: C.ink, fontSize: 18, fontWeight: '900', marginTop: 15 }, materials: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 3 }, summary: { flexDirection: 'row', justifyContent: 'space-between', gap: 16, marginTop: 14, padding: 13, borderRadius: 14, backgroundColor: '#F7F9F6' }, summaryLabel: { color: C.muted, fontSize: 11, fontWeight: '700' }, summaryValue: { color: C.ink, fontSize: 15, fontWeight: '900', marginTop: 3 }, areaCopy: { flex: 1, alignItems: 'flex-end' }, area: { color: C.ink, fontSize: 12, fontWeight: '800', marginTop: 3, textAlign: 'right' }, reviewNotice: { marginTop: 12, padding: 11, borderRadius: 12, backgroundColor: '#FFF7E8' }, reviewNoticeText: { color: '#8A5A19', fontSize: 12, lineHeight: 18, fontWeight: '800' }, openButton: { minHeight: 46, marginTop: 13, borderRadius: 14, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center' }, openButtonText: { color: C.white, fontSize: 12, fontWeight: '900' },
  adSlot: { marginTop: 4, marginBottom: 16 },
  empty: { flex: 1, minHeight: 320, alignItems: 'center', justifyContent: 'center', padding: 28, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.line }, emptyIcon: { color: C.green, fontSize: 46 }, emptyTitle: { color: C.ink, fontSize: 17, fontWeight: '900', marginTop: 10 }, emptyText: { color: C.muted, fontSize: 12, lineHeight: 19, textAlign: 'center', marginTop: 7 }, retry: { minHeight: 46, marginTop: 16, paddingHorizontal: 20, borderRadius: 14, backgroundColor: C.green, justifyContent: 'center' }, retryText: { color: C.white, fontSize: 12, fontWeight: '900' }, footer: { marginVertical: 20 },
});
