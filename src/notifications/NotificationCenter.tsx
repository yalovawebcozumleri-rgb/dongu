import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { AppNotification } from './types';

type NotificationResponse = {
  data: AppNotification[];
  meta: { current_page: number; last_page: number; total: number; unreadCount: number };
};

const iconFor = (type: string) => type === 'new_message' ? '✉' : type.startsWith('admin_') ? '◆' : type.includes('moderation') ? '!' : type.includes('review') ? '★' : type.includes('delivery') ? '✓' : '↻';

export default function NotificationCenter({ token, back, onUnreadCount, openNotification, refreshSignal }: {
  token: string;
  back: () => void;
  onUnreadCount: (count: number) => void;
  openNotification: (notification: AppNotification) => void;
  refreshSignal: number;
}) {
  const [items, setItems] = useState<AppNotification[]>([]);
  const [onlyUnread, setOnlyUnread] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [page, setPage] = useState(0);
  const [lastPage, setLastPage] = useState(0);
  const [unreadCount, setUnreadCount] = useState(0);
  const [error, setError] = useState('');
  const requestId = useRef(0);

  const load = useCallback(async (nextPage = 1, mode: 'replace' | 'refresh' | 'more' = 'replace') => {
    const currentRequest = ++requestId.current;
    if (mode === 'replace') setLoading(true);
    if (mode === 'refresh') setRefreshing(true);
    if (mode === 'more') setLoadingMore(true);
    try {
      const query = `/notifications?page=${nextPage}&per_page=20${onlyUnread ? '&unread=1' : ''}`;
      const response = await apiRequest<NotificationResponse>(query, { token });
      if (currentRequest !== requestId.current) return;
      setItems(current => mode === 'more'
        ? [...current, ...response.data.filter(item => !current.some(known => known.id === item.id))]
        : response.data);
      setPage(response.meta.current_page);
      setLastPage(response.meta.last_page);
      setUnreadCount(response.meta.unreadCount);
      onUnreadCount(response.meta.unreadCount);
      setError('');
    } catch (loadError) {
      if (currentRequest !== requestId.current) return;
      if (mode === 'replace') {
        setItems([]);
        setError(loadError instanceof ApiError ? loadError.message : 'Bildirimlere ulaşılamadı.');
      }
    } finally {
      if (currentRequest === requestId.current) {
        if (mode === 'replace') setLoading(false);
        if (mode === 'refresh') setRefreshing(false);
        if (mode === 'more') setLoadingMore(false);
      }
    }
  }, [onUnreadCount, onlyUnread, token]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { if (refreshSignal > 0) void load(1, 'refresh'); }, [load, refreshSignal]);

  const open = async (notification: AppNotification) => {
    if (!notification.read) {
      setItems(current => current.map(item => item.id === notification.id ? { ...item, read: true } : item));
      const nextCount = Math.max(0, unreadCount - 1);
      setUnreadCount(nextCount);
      onUnreadCount(nextCount);
      try { await apiRequest(`/notifications/${notification.id}/read`, { method: 'PATCH', token }); } catch { void load(1, 'refresh'); }
    }
    openNotification({ ...notification, read: true });
  };

  const markAll = async () => {
    if (!unreadCount) return;
    setItems(current => current.map(item => ({ ...item, read: true })));
    setUnreadCount(0);
    onUnreadCount(0);
    try { await apiRequest('/notifications/read-all', { method: 'PATCH', token }); } catch { void load(1, 'refresh'); }
  };

  return (
    <View style={x.screen}>
      <View style={x.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Ana sayfaya dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable>
        <View style={x.headerCopy}><Text style={x.eyebrow}>HAREKETLER</Text><Text style={x.title}>Bildirimler</Text></View>
        <Pressable disabled={!unreadCount} onPress={() => void markAll()} style={x.markAll}><Text style={[x.markAllText, !unreadCount && x.muted]}>Tümünü oku</Text></Pressable>
      </View>
      <View style={x.tabs}>
        <Pressable onPress={() => setOnlyUnread(false)} style={[x.tab, !onlyUnread && x.tabActive]}><Text style={[x.tabText, !onlyUnread && x.tabTextActive]}>Tümü</Text></Pressable>
        <Pressable onPress={() => setOnlyUnread(true)} style={[x.tab, onlyUnread && x.tabActive]}><Text style={[x.tabText, onlyUnread && x.tabTextActive]}>Okunmamış {unreadCount ? `(${unreadCount})` : ''}</Text></Pressable>
      </View>
      <FlatList
        data={items}
        keyExtractor={item => String(item.id)}
        contentContainerStyle={items.length ? x.list : x.emptyList}
        renderItem={({ item }) => (
          <Pressable onPress={() => void open(item)} style={({ pressed }) => [x.row, !item.read && x.rowUnread, pressed && x.pressed]}>
            <View style={[x.icon, !item.read && x.iconUnread]}><Text style={[x.iconText, !item.read && x.iconTextUnread]}>{iconFor(item.type)}</Text></View>
            <View style={x.copy}><View style={x.titleRow}><Text style={x.rowTitle} numberOfLines={1}>{item.title}</Text>{!item.read && <View style={x.dot} />}</View><Text style={x.body} numberOfLines={2}>{item.body}</Text><Text style={x.time}>{item.time}</Text></View>
            <Text style={x.arrow}>›</Text>
          </Pressable>
        )}
        refreshing={refreshing}
        onRefresh={() => void load(1, 'refresh')}
        onEndReached={() => { if (!loading && !refreshing && !loadingMore && page < lastPage) void load(page + 1, 'more'); }}
        onEndReachedThreshold={0.35}
        ListEmptyComponent={loading ? <View style={x.empty}><ActivityIndicator color={C.green} /><Text style={x.emptyTitle}>Bildirimler yükleniyor</Text></View> : error ? <View style={x.empty}><Text style={x.emptyTitle}>Bildirimler alınamadı</Text><Text style={x.emptyText}>{error}</Text><Pressable onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Tekrar dene</Text></Pressable></View> : <View style={x.empty}><Text style={x.emptyIcon}>♢</Text><Text style={x.emptyTitle}>{onlyUnread ? 'Okunmamış bildirimin yok' : 'Henüz bildirimin yok'}</Text><Text style={x.emptyText}>Talep, mesaj ve teslimat hareketlerin burada görünecek.</Text></View>}
        ListFooterComponent={loadingMore ? <ActivityIndicator color={C.green} style={x.footer} /> : null}
        initialNumToRender={8}
        maxToRenderPerBatch={8}
        windowSize={7}
        showsVerticalScrollIndicator={false}
      />
    </View>
  );
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, header: { minHeight: 78, paddingHorizontal: 15, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { width: 42, height: 42, borderRadius: 21, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center' }, backText: { color: C.ink, fontSize: 29, lineHeight: 32 }, headerCopy: { flex: 1, marginLeft: 11 }, eyebrow: { color: C.green, fontSize: 10, letterSpacing: 1.4, fontWeight: '900' }, title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 }, markAll: { minHeight: 42, justifyContent: 'center', paddingLeft: 8 }, markAllText: { color: C.green, fontSize: 12, fontWeight: '900' }, muted: { color: C.muted },
  tabs: { flexDirection: 'row', gap: 8, padding: 14, paddingBottom: 8 }, tab: { minHeight: 38, paddingHorizontal: 16, borderRadius: 19, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, alignItems: 'center', justifyContent: 'center' }, tabActive: { backgroundColor: C.dark, borderColor: C.dark }, tabText: { color: C.muted, fontSize: 12, fontWeight: '800' }, tabTextActive: { color: C.white },
  list: { padding: 14, paddingBottom: 28 }, emptyList: { flexGrow: 1, padding: 14 }, row: { minHeight: 92, marginBottom: 8, padding: 13, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, flexDirection: 'row', alignItems: 'center' }, rowUnread: { borderColor: '#B8D8C2', backgroundColor: '#FBFDFB' }, pressed: { opacity: .82 }, icon: { width: 45, height: 45, borderRadius: 15, backgroundColor: '#EEF1EE', alignItems: 'center', justifyContent: 'center', marginRight: 11 }, iconUnread: { backgroundColor: C.soft }, iconText: { color: C.muted, fontSize: 20, fontWeight: '900' }, iconTextUnread: { color: C.green }, copy: { flex: 1 }, titleRow: { flexDirection: 'row', alignItems: 'center' }, rowTitle: { flex: 1, color: C.ink, fontSize: 13, fontWeight: '900' }, dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#D95442', marginLeft: 6 }, body: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 4 }, time: { color: '#95A199', fontSize: 10, marginTop: 5 }, arrow: { color: C.muted, fontSize: 24, marginLeft: 7 },
  empty: { flex: 1, minHeight: 320, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, alignItems: 'center', justifyContent: 'center', padding: 28 }, emptyIcon: { color: C.green, fontSize: 48 }, emptyTitle: { color: C.ink, fontSize: 16, fontWeight: '900', marginTop: 10 }, emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 6 }, retry: { minHeight: 42, marginTop: 15, paddingHorizontal: 17, borderRadius: 14, backgroundColor: C.green, justifyContent: 'center' }, retryText: { color: C.white, fontSize: 12, fontWeight: '900' }, footer: { marginVertical: 18 },
});
