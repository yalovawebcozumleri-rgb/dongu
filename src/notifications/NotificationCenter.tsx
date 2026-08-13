import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Animated, FlatList, Modal, PanResponder, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { AppNotification } from './types';
import { useNotice } from '../notice/NoticeProvider';

type NotificationResponse = {
  data: AppNotification[];
  meta: { current_page: number; last_page: number; total: number; unreadCount: number };
};

const iconFor = (type: string) => type === 'new_message' ? '\u2709' : type.startsWith('admin_') ? '\u25c6' : type.includes('moderation') ? '!' : type.includes('review') ? '\u2605' : type.includes('delivery') ? '\u2713' : '\u21bb';

type SwipeNotificationRowProps = {
  notification: AppNotification;
  onDelete: (notification: AppNotification) => Promise<void> | void;
  children: React.ReactNode;
};

function SwipeNotificationRow({ notification, onDelete, children }: SwipeNotificationRowProps) {
  const translateX = useRef(new Animated.Value(0)).current;
  const widthRef = useRef(0);
  const deletingRef = useRef(false);

  const animateTo = (value: number, callback?: () => void) => {
    Animated.spring(translateX, { toValue: value, useNativeDriver: true, bounciness: 0, speed: 20 }).start(({ finished }) => {
      if (finished) callback?.();
    });
  };

  const commitDelete = async () => {
    if (deletingRef.current) return;
    deletingRef.current = true;
    await onDelete(notification);
    deletingRef.current = false;
  };

  const deleteWithAnimation = () => {
    const width = Math.max(widthRef.current, 280);
    Animated.timing(translateX, { toValue: -width, duration: 190, useNativeDriver: true }).start(({ finished }) => {
      if (finished) void commitDelete();
    });
  };

  const responder = useRef(PanResponder.create({
    onMoveShouldSetPanResponder: (_, gesture) => gesture.dx < -8 && Math.abs(gesture.dx) > Math.abs(gesture.dy) * 1.15,
    onMoveShouldSetPanResponderCapture: (_, gesture) => gesture.dx < -8 && Math.abs(gesture.dx) > Math.abs(gesture.dy) * 1.15,
    onPanResponderGrant: () => translateX.stopAnimation(),
    onPanResponderMove: (_, gesture) => {
      const width = Math.max(widthRef.current, 280);
      translateX.setValue(Math.max(-width, Math.min(0, gesture.dx)));
    },
    onPanResponderRelease: (_, gesture) => {
      const width = Math.max(widthRef.current, 280);
      if (gesture.dx <= -width * 0.68 || gesture.vx < -1.35) {
        deleteWithAnimation();
        return;
      }
      animateTo(gesture.dx < -52 ? -104 : 0);
    },
    onPanResponderTerminate: () => animateTo(0),
    onShouldBlockNativeResponder: () => true,
  })).current;

  return (
    <View style={x.swipeRow} {...responder.panHandlers}>
      <View style={x.deleteBackground} />
      <Pressable accessibilityRole="button" accessibilityLabel="Bildirimi sil" onPress={deleteWithAnimation} style={x.deleteAction}>
        <Text style={x.deleteActionText}>Sil</Text>
      </Pressable>
      <Animated.View
        onLayout={event => { widthRef.current = event.nativeEvent.layout.width; }}
        style={[x.swipeContent, { transform: [{ translateX }] }]}
      >
        {children}
      </Animated.View>
    </View>
  );
}
export default function NotificationCenter({ token, back, onUnreadCount, openNotification, refreshSignal }: {
  token: string;
  back: () => void;
  onUnreadCount: (count: number) => void;
  openNotification: (notification: AppNotification) => void;
  refreshSignal: number;
}) {
  const [items, setItems] = useState<AppNotification[]>([]);
  const [onlyUnread, setOnlyUnread] = useState(false);
  const [category, setCategory] = useState<'all' | 'activity' | 'announcements'>('all');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [page, setPage] = useState(0);
  const [lastPage, setLastPage] = useState(0);
  const [unreadCount, setUnreadCount] = useState(0);
  const [error, setError] = useState('');
  const [selectedNotification, setSelectedNotification] = useState<AppNotification | null>(null);
  const requestId = useRef(0);
  const { showNotice } = useNotice();

  const load = useCallback(async (nextPage = 1, mode: 'replace' | 'refresh' | 'more' = 'replace') => {
    const currentRequest = ++requestId.current;
    if (mode === 'replace') setLoading(true);
    if (mode === 'refresh') setRefreshing(true);
    if (mode === 'more') setLoadingMore(true);
    try {
      const categoryQuery = category === 'all' ? '' : '&category=' + category;
      const query = '/notifications?page=' + nextPage + '&per_page=20' + (onlyUnread ? '&unread=1' : '') + categoryQuery;
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
        setError(loadError instanceof ApiError ? loadError.message : 'Bildirimlere ula\u015f\u0131lamad\u0131.');
      }
    } finally {
      if (currentRequest === requestId.current) {
        if (mode === 'replace') setLoading(false);
        if (mode === 'refresh') setRefreshing(false);
        if (mode === 'more') setLoadingMore(false);
      }
    }
  }, [category, onUnreadCount, onlyUnread, token]);

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
    if (notification.type.startsWith('admin_') || notification.type === 'moderation_action') { setSelectedNotification({ ...notification, read: true }); return; }
    openNotification({ ...notification, read: true });
  };

  const removeNotification = async (notification: AppNotification) => {
    try {
      await apiRequest(`/notifications/${notification.id}`, { method: 'DELETE', token });
      setItems(current => current.filter(item => item.id !== notification.id));
      if (!notification.read) {
        const nextCount = Math.max(0, unreadCount - 1);
        setUnreadCount(nextCount);
        onUnreadCount(nextCount);
      }
      showNotice({ tone: 'success', title: 'Bildirim silindi', message: 'Bildirim kalıcı olarak listenden kaldırıldı.' });
    } catch (deleteError) {
      showNotice({ tone: 'error', title: 'Bildirim silinemedi', message: deleteError instanceof ApiError ? deleteError.message : 'Sunucuya ulaşılamadı.' });
    }
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
      <Pressable accessibilityRole="button" accessibilityLabel={"Ana sayfaya " + String.fromCharCode(246) + "n"} onPress={back} style={x.back}><Text style={x.backText}>{String.fromCharCode(8249)}</Text></Pressable>
        <View style={x.headerCopy}><Text style={x.eyebrow}>HAREKETLER</Text><Text style={x.title}>Bildirimler</Text></View>
      <Pressable disabled={!unreadCount} onPress={() => void markAll()} style={x.markAll}><Text style={[x.markAllText, !unreadCount && x.muted]}>{'T\u00fcm\u00fcn\u00fc oku'}</Text></Pressable>
      </View>
      <View style={x.tabs}>
      <Pressable onPress={() => { setItems([]); setCategory('all'); setOnlyUnread(false); }} style={[x.tab, category === 'all' && !onlyUnread && x.tabActive]}><Text style={[x.tabText, category === 'all' && !onlyUnread && x.tabTextActive]}>{'T\u00fcm\u00fc'}</Text></Pressable>
      <Pressable onPress={() => { setItems([]); setCategory('activity'); setOnlyUnread(false); }} style={[x.tab, category === 'activity' && !onlyUnread && x.tabActive]}><Text style={[x.tabText, category === 'activity' && !onlyUnread && x.tabTextActive]}>{'\u0130lanlar ve mesajlar'}</Text></Pressable>
      <Pressable onPress={() => { setItems([]); setCategory('announcements'); setOnlyUnread(false); }} style={[x.tab, category === 'announcements' && !onlyUnread && x.tabActive]}><Text style={[x.tabText, category === 'announcements' && !onlyUnread && x.tabTextActive]}>Duyurular</Text></Pressable>
      <Pressable onPress={() => setOnlyUnread(value => !value)} style={[x.tab, onlyUnread && x.tabActive]}><Text style={[x.tabText, onlyUnread && x.tabTextActive]}>{'Okunmam\u0131\u015f ' + (unreadCount ? '(' + unreadCount + ')' : '')}</Text></Pressable>
      </View>
      <FlatList
        data={items}
        keyExtractor={item => String(item.id)}
        contentContainerStyle={items.length ? x.list : x.emptyList}
        renderItem={({ item }) => (
          <SwipeNotificationRow notification={item} onDelete={removeNotification}>
            <Pressable onPress={() => void open(item)} style={({ pressed }) => [x.row, !item.read && x.rowUnread, pressed && x.pressed]}>
              <View style={[x.icon, !item.read && x.iconUnread]}><Text style={[x.iconText, !item.read && x.iconTextUnread]}>{iconFor(item.type)}</Text></View>
              <View style={x.copy}><View style={x.titleRow}><Text style={x.rowTitle} numberOfLines={1}>{item.title}</Text>{!item.read && <View style={x.dot} />}</View><Text style={x.body} numberOfLines={2}>{item.body}</Text><Text style={x.time}>{item.time}</Text></View>
              <Text style={x.arrow}>{String.fromCharCode(8250)}</Text>
            </Pressable>
          </SwipeNotificationRow>
        )}
        refreshing={refreshing}
        onRefresh={() => void load(1, 'refresh')}
        onEndReached={() => { if (!loading && !refreshing && !loadingMore && page < lastPage) void load(page + 1, 'more'); }}
        onEndReachedThreshold={0.35}
        ListEmptyComponent={loading ? <View style={x.empty}><ActivityIndicator color={C.green} /><Text style={x.emptyTitle}>{'Bildirimler y\u00fckleniyor'}</Text></View> : error ? <View style={x.empty}><Text style={x.emptyTitle}>{'Bildirimler al\u0131namad\u0131'}</Text><Text style={x.emptyText}>{error}</Text><Pressable onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Tekrar dene</Text></Pressable></View> : <View style={x.empty}><Text style={x.emptyIcon}>{String.fromCharCode(9826)}</Text><Text style={x.emptyTitle}>{onlyUnread ? 'Okunmam\u0131\u015f bildirimin yok' : 'Hen\u00fcz bildirimin yok'}</Text><Text style={x.emptyText}>{'Talep, mesaj ve teslimat hareketlerin burada g\u00f6r\u00fcnecek.'}</Text></View>}
        ListFooterComponent={loadingMore ? <ActivityIndicator color={C.green} style={x.footer} /> : null}
        initialNumToRender={8}
        maxToRenderPerBatch={8}
        windowSize={7}
        showsVerticalScrollIndicator={false}
        directionalLockEnabled
      />
      <Modal visible={!!selectedNotification} transparent animationType="fade" onRequestClose={() => setSelectedNotification(null)}>
        <View style={x.modalOverlay}>
          <Pressable style={x.modalDismissArea} onPress={() => setSelectedNotification(null)} />
          <View style={x.modalCard}>
            <View style={x.modalHeader}><Text style={x.modalEyebrow}>{selectedNotification?.type === 'moderation_action' ? 'YÖNETİM UYARISI' : 'DUYURU'}</Text><Pressable onPress={() => setSelectedNotification(null)} style={x.modalClose}><Text style={x.modalCloseText}>×</Text></Pressable></View>
            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={x.modalScroll}><Text style={x.modalTitle}>{selectedNotification?.title}</Text><Text style={x.modalTime}>{selectedNotification?.time}</Text><Text style={x.modalBody}>{selectedNotification?.body}</Text></ScrollView>
            <Pressable onPress={() => setSelectedNotification(null)} style={x.modalButton}><Text style={x.modalButtonText}>Kapat</Text></Pressable>
          </View>
        </View>
      </Modal>    </View>
  );
}

const x = StyleSheet.create({
  swipeRow: { position: 'relative', marginBottom: 8, borderRadius: 18, overflow: 'hidden' },
  swipeContent: { zIndex: 1, backgroundColor: C.bg },
  deleteBackground: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, borderRadius: 18, backgroundColor: '#A94636' },
  deleteAction: { position: 'absolute', right: 0, top: 0, bottom: 0, width: 104, alignItems: 'center', justifyContent: 'center' },
  deleteActionText: { color: '#FFFFFF', fontSize: 14, fontWeight: '900' },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(15, 34, 24, 0.42)', justifyContent: 'center', padding: 18 }, modalDismissArea: { ...StyleSheet.absoluteFill }, modalCard: { maxHeight: '78%', borderRadius: 24, backgroundColor: C.white, padding: 20, shadowColor: '#000', shadowOpacity: 0.18, shadowRadius: 20, shadowOffset: { width: 0, height: 8 }, elevation: 8 }, modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }, modalEyebrow: { color: C.green, fontSize: 11, fontWeight: '900', letterSpacing: 1.3 }, modalClose: { width: 34, height: 34, borderRadius: 17, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center' }, modalCloseText: { color: C.ink, fontSize: 23, lineHeight: 25 }, modalScroll: { paddingVertical: 14 }, modalTitle: { color: C.ink, fontSize: 20, lineHeight: 27, fontWeight: '900' }, modalTime: { color: C.muted, fontSize: 11, marginTop: 6 }, modalBody: { color: C.ink, fontSize: 15, lineHeight: 24, marginTop: 18 }, modalButton: { minHeight: 46, borderRadius: 14, backgroundColor: C.dark, alignItems: 'center', justifyContent: 'center' }, modalButtonText: { color: C.white, fontSize: 13, fontWeight: '900' },
  screen: { flex: 1, backgroundColor: C.bg }, header: { minHeight: 78, paddingHorizontal: 15, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { width: 42, height: 42, borderRadius: 21, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center' }, backText: { color: C.ink, fontSize: 29, lineHeight: 32 }, headerCopy: { flex: 1, marginLeft: 11 }, eyebrow: { color: C.green, fontSize: 10, letterSpacing: 1.4, fontWeight: '900' }, title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 }, markAll: { minHeight: 42, justifyContent: 'center', paddingLeft: 8 }, markAllText: { color: C.green, fontSize: 12, fontWeight: '900' }, muted: { color: C.muted },
  tabs: { flexDirection: 'row', gap: 8, padding: 14, paddingBottom: 8 }, tab: { minHeight: 38, paddingHorizontal: 16, borderRadius: 19, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, alignItems: 'center', justifyContent: 'center' }, tabActive: { backgroundColor: C.dark, borderColor: C.dark }, tabText: { color: C.muted, fontSize: 12, fontWeight: '800' }, tabTextActive: { color: C.white },
  list: { padding: 14, paddingBottom: 28 }, emptyList: { flexGrow: 1, padding: 14 }, row: { minHeight: 92, padding: 13, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, flexDirection: 'row', alignItems: 'center' }, rowUnread: { borderColor: '#B8D8C2', backgroundColor: '#FBFDFB' }, pressed: { opacity: .82 }, icon: { width: 45, height: 45, borderRadius: 15, backgroundColor: '#EEF1EE', alignItems: 'center', justifyContent: 'center', marginRight: 11 }, iconUnread: { backgroundColor: C.soft }, iconText: { color: C.muted, fontSize: 20, fontWeight: '900' }, iconTextUnread: { color: C.green }, copy: { flex: 1 }, titleRow: { flexDirection: 'row', alignItems: 'center' }, rowTitle: { flex: 1, color: C.ink, fontSize: 13, fontWeight: '900' }, dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#D95442', marginLeft: 6 }, body: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 4 }, time: { color: '#95A199', fontSize: 10, marginTop: 5 }, rowActions: { marginLeft: 7, alignItems: 'center', gap: 5 }, arrow: { color: C.muted, fontSize: 24 }, deleteButton: { minWidth: 38, minHeight: 28, paddingHorizontal: 8, borderRadius: 10, backgroundColor: '#FCEBE7', alignItems: 'center', justifyContent: 'center' }, deleteButtonText: { color: '#A94636', fontSize: 10, fontWeight: '900' },
  empty: { flex: 1, minHeight: 320, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, alignItems: 'center', justifyContent: 'center', padding: 28 }, emptyIcon: { color: C.green, fontSize: 48 }, emptyTitle: { color: C.ink, fontSize: 16, fontWeight: '900', marginTop: 10 }, emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 6 }, retry: { minHeight: 42, marginTop: 15, paddingHorizontal: 17, borderRadius: 14, backgroundColor: C.green, justifyContent: 'center' }, retryText: { color: C.white, fontSize: 12, fontWeight: '900' }, footer: { marginVertical: 18 },
});
