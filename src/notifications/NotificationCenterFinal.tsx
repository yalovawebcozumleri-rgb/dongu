import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  FlatList,
  Modal,
  PanResponder,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import { AppNotification } from './types';

type NotificationCategory = 'all' | 'listings' | 'messages' | 'announcements';

type NotificationResponse = {
  data: AppNotification[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    unreadCount: number;
    categoryUnreadCounts: Record<NotificationCategory, number>;
  };
};

const categoryLabels: Record<NotificationCategory, string> = {
  all: 'T\u00fcm\u00fc',
  listings: '\u0130lanlar',
  messages: 'Mesajlar',
  announcements: 'Duyurular',
};

const emptyCopy: Record<NotificationCategory, { title: string; message: string }> = {
  all: { title: 'Hen\u00fcz bildirimin yok', message: '\u0130lan, mesaj ve duyuru hareketlerin burada g\u00f6r\u00fcnecek.' },
  listings: { title: '\u0130lan bildirimin yok', message: 'Al\u0131m talebi, rezervasyon ve teslimat hareketlerin burada g\u00f6r\u00fcnecek.' },
  messages: { title: 'Mesaj bildirimin yok', message: 'Yeni sohbet mesajlar\u0131n burada tek kartta toplanacak.' },
  announcements: { title: 'Duyuru bulunmuyor', message: 'D\u00f6ng\u00fc duyurular\u0131 ve y\u00f6netim bilgilendirmeleri burada g\u00f6r\u00fcnecek.' },
};

const iconFor = (category: AppNotification['category'], type: string) => {
  if (category === 'messages') return '\u2709';
  if (category === 'announcements') return type.includes('moderation') ? '!' : '\u2139';
  if (type.includes('review')) return '\u2605';
  if (type.includes('delivery')) return '\u2713';
  return '\u21bb';
};

function SwipeNotificationRow({
  notification,
  onDelete,
  children,
}: {
  notification: AppNotification;
  onDelete: (notification: AppNotification) => Promise<void>;
  children: React.ReactNode;
}) {
  const translateX = useRef(new Animated.Value(0)).current;
  const widthRef = useRef(0);
  const deletingRef = useRef(false);

  const animateTo = (value: number) => {
    Animated.spring(translateX, {
      toValue: value,
      useNativeDriver: true,
      bounciness: 0,
      speed: 20,
    }).start();
  };

  const commitDelete = async () => {
    if (deletingRef.current) return;
    deletingRef.current = true;
    await onDelete(notification);
    deletingRef.current = false;
  };

  const deleteWithAnimation = () => {
    const width = Math.max(widthRef.current, 280);
    Animated.timing(translateX, {
      toValue: -width,
      duration: 190,
      useNativeDriver: true,
    }).start(({ finished }) => {
      if (finished) void commitDelete();
    });
  };

  const responder = useRef(PanResponder.create({
    onMoveShouldSetPanResponder: (_, gesture) => gesture.dx < -12 && Math.abs(gesture.dx) > Math.abs(gesture.dy),
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
  })).current;

  return (
    <View style={x.swipeRow}>
      <View style={x.deleteBackground} />
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Bildirimi sil"
        onPress={deleteWithAnimation}
        style={x.deleteAction}
      >
        <Text style={x.deleteActionText}>Sil</Text>
      </Pressable>
      <Animated.View
        onLayout={event => { widthRef.current = event.nativeEvent.layout.width; }}
        style={{ transform: [{ translateX }] }}
        {...responder.panHandlers}
      >
        {children}
      </Animated.View>
    </View>
  );
}

export default function NotificationCenterFinal({
  token,
  back,
  onUnreadCount,
  openNotification,
  refreshSignal,
}: {
  token: string;
  back: () => void;
  onUnreadCount: (count: number) => void;
  openNotification: (notification: AppNotification) => void;
  refreshSignal: number;
}) {
  const [items, setItems] = useState<AppNotification[]>([]);
  const [category, setCategory] = useState<NotificationCategory>('all');
  const [onlyUnread, setOnlyUnread] = useState(false);
  const [counts, setCounts] = useState<Record<NotificationCategory, number>>({ all: 0, listings: 0, messages: 0, announcements: 0 });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [page, setPage] = useState(0);
  const [lastPage, setLastPage] = useState(0);
  const [error, setError] = useState('');
  const [selectedNotification, setSelectedNotification] = useState<AppNotification | null>(null);
  const [recentlyDeleted, setRecentlyDeleted] = useState<AppNotification | null>(null);
  const requestId = useRef(0);
  const undoTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const { showNotice } = useNotice();

  const load = useCallback(async (nextPage = 1, mode: 'replace' | 'refresh' | 'more' = 'replace') => {
    const currentRequest = ++requestId.current;
    if (mode === 'replace') setLoading(true);
    if (mode === 'refresh') setRefreshing(true);
    if (mode === 'more') setLoadingMore(true);

    try {
      const categoryQuery = category === 'all' ? '' : `&category=${category}`;
      const response = await apiRequest<NotificationResponse>(
        `/notifications?page=${nextPage}&per_page=20${onlyUnread ? '&unread=1' : ''}${categoryQuery}`,
        { token },
      );
      if (currentRequest !== requestId.current) return;
      setItems(current => mode === 'more'
        ? [...current, ...response.data.filter(item => !current.some(known => known.id === item.id))]
        : response.data);
      setPage(response.meta.current_page);
      setLastPage(response.meta.last_page);
      setCounts(response.meta.categoryUnreadCounts);
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
        // Filtre değişimiyle gerçek zamanlı yenileme üst üste gelirse eski
        // isteğin loading durumu ekranda takılı kalmamalı.
        setLoading(false);
        setRefreshing(false);
        setLoadingMore(false);
      }
    }
  }, [category, onUnreadCount, onlyUnread, token]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { if (refreshSignal > 0) void load(1, 'refresh'); }, [load, refreshSignal]);
  useEffect(() => () => {
    if (undoTimer.current) clearTimeout(undoTimer.current);
  }, []);

  const markRead = async (notification: AppNotification) => {
    if (notification.read) return notification;
    const readNotification = { ...notification, read: true };
    setItems(current => current.map(item => item.id === notification.id ? readNotification : item));
    setCounts(current => ({
      ...current,
      all: Math.max(0, current.all - 1),
      [notification.category]: Math.max(0, current[notification.category] - 1),
    }));
    onUnreadCount(Math.max(0, counts.all - 1));
    try {
      await apiRequest(`/notifications/${notification.id}/read`, { method: 'PATCH', token });
    } catch {
      void load(1, 'refresh');
    }
    return readNotification;
  };

  const open = async (notification: AppNotification) => {
    const next = await markRead(notification);
    if (next.category === 'announcements') {
      setSelectedNotification(next);
      return;
    }
    openNotification(next);
  };

  const removeNotification = async (notification: AppNotification) => {
    setItems(current => current.filter(item => item.id !== notification.id));
    try {
      await apiRequest(`/notifications/${notification.id}`, { method: 'DELETE', token });
      if (!notification.read) {
        setCounts(current => ({
          ...current,
          all: Math.max(0, current.all - 1),
          [notification.category]: Math.max(0, current[notification.category] - 1),
        }));
        onUnreadCount(Math.max(0, counts.all - 1));
      }
      if (undoTimer.current) clearTimeout(undoTimer.current);
      setRecentlyDeleted(notification);
      undoTimer.current = setTimeout(() => setRecentlyDeleted(null), 5000);
    } catch (deleteError) {
      void load(1, 'refresh');
      showNotice({
        tone: 'error',
        title: 'Bildirim silinemedi',
        message: deleteError instanceof ApiError ? deleteError.message : 'Sunucuya ula\u015f\u0131lamad\u0131.',
      });
    }
  };

  const undoDelete = async () => {
    const notification = recentlyDeleted;
    if (!notification) return;
    if (undoTimer.current) clearTimeout(undoTimer.current);
    setRecentlyDeleted(null);
    try {
      await apiRequest(`/notifications/${notification.id}/restore`, { method: 'POST', token });
      await load(1, 'refresh');
    } catch (restoreError) {
      showNotice({
        tone: 'error',
        title: 'Bildirim geri al\u0131namad\u0131',
        message: restoreError instanceof ApiError ? restoreError.message : 'Sunucuya ula\u015f\u0131lamad\u0131.',
      });
    }
  };

  const markAll = async () => {
    if (!counts.all) return;
    setItems(current => current.map(item => ({ ...item, read: true })));
    setCounts({ all: 0, listings: 0, messages: 0, announcements: 0 });
    onUnreadCount(0);
    try {
      await apiRequest('/notifications/read-all', { method: 'PATCH', token });
    } catch {
      void load(1, 'refresh');
    }
  };

  const displayTitle = (item: AppNotification) => {
    if (item.category !== 'messages' || item.messageCount <= 1) return item.title;
    const sender = item.body.split(':')[0]?.trim();
    return sender ? `${sender} \u00b7 ${item.messageCount} yeni mesaj` : `${item.messageCount} yeni mesaj`;
  };

  const selectedEmpty = onlyUnread
    ? { title: 'Okunmam\u0131\u015f bildirimin yok', message: 'Yeni bildirimlerin bu filtrede g\u00f6r\u00fcnecek.' }
    : emptyCopy[category];

  return (
    <View style={x.screen}>
      <View style={x.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Ana sayfaya d\u00f6n" onPress={back} style={x.back}>
          <Text style={x.backText}>{"\u2039"}</Text>
        </Pressable>
        <View style={x.headerCopy}>
          <Text style={x.eyebrow}>HAREKETLER</Text>
          <Text style={x.title}>Bildirimler</Text>
        </View>
        <Pressable disabled={!counts.all} onPress={() => void markAll()} style={x.markAll}>
          <Text style={[x.markAllText, !counts.all && x.muted]}>T\u00fcm\u00fcn\u00fc oku</Text>
        </Pressable>
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={x.tabs}>
        {(Object.keys(categoryLabels) as NotificationCategory[]).map(tab => (
          <Pressable
            key={tab}
            onPress={() => { setItems([]); setCategory(tab); }}
            style={[x.tab, category === tab && x.tabActive]}
          >
            <Text style={[x.tabText, category === tab && x.tabTextActive]}>
              {categoryLabels[tab]}{counts[tab] > 0 ? ` (${counts[tab] > 99 ? '99+' : counts[tab]})` : ''}
            </Text>
          </Pressable>
        ))}
      </ScrollView>

      <View style={x.filterRow}>
        <Text style={x.resultText}>{onlyUnread ? 'Yaln\u0131zca okunmam\u0131\u015f bildirimler' : categoryLabels[category]}</Text>
        <Pressable
          accessibilityRole="switch"
          accessibilityState={{ checked: onlyUnread }}
          onPress={() => setOnlyUnread(value => !value)}
          style={[x.unreadFilter, onlyUnread && x.unreadFilterActive]}
        >
          <View style={[x.filterDot, onlyUnread && x.filterDotActive]} />
          <Text style={[x.unreadFilterText, onlyUnread && x.unreadFilterTextActive]}>Okunmam\u0131\u015f</Text>
        </Pressable>
      </View>

      <FlatList
        data={items}
        keyExtractor={item => String(item.id)}
        contentContainerStyle={items.length ? x.list : x.emptyList}
        renderItem={({ item }) => (
          <SwipeNotificationRow notification={item} onDelete={removeNotification}>
            <Pressable onPress={() => void open(item)} style={({ pressed }) => [x.row, !item.read && x.rowUnread, pressed && x.pressed]}>
              <View style={[x.icon, !item.read && x.iconUnread]}>
                <Text style={[x.iconText, !item.read && x.iconTextUnread]}>{iconFor(item.category, item.type)}</Text>
              </View>
              <View style={x.copy}>
                <View style={x.titleRow}>
                  <Text style={x.rowTitle} numberOfLines={1}>{displayTitle(item)}</Text>
                  {!item.read && <View style={x.dot} />}
                </View>
                <Text style={x.body} numberOfLines={2}>{item.body}</Text>
                <Text style={x.time}>{item.time}</Text>
              </View>
              <Text style={x.arrow}>{"\u203a"}</Text>
            </Pressable>
          </SwipeNotificationRow>
        )}
        refreshing={refreshing}
        onRefresh={() => void load(1, 'refresh')}
        onEndReached={() => {
          if (!loading && !refreshing && !loadingMore && page < lastPage) void load(page + 1, 'more');
        }}
        onEndReachedThreshold={0.35}
        ListEmptyComponent={loading ? (
          <View style={x.empty}><ActivityIndicator color={C.green} /></View>
        ) : error ? (
          <View style={x.empty}>
            <Text style={x.emptyTitle}>Bildirimler al\u0131namad\u0131</Text>
            <Text style={x.emptyText}>{error}</Text>
            <Pressable onPress={() => void load()} style={x.retry}><Text style={x.retryText}>Tekrar dene</Text></Pressable>
          </View>
        ) : (
          <View style={x.empty}>
            <Text style={x.emptyIcon}>{"\uD83D\uDD14"}</Text>
            <Text style={x.emptyTitle}>{selectedEmpty.title}</Text>
            <Text style={x.emptyText}>{selectedEmpty.message}</Text>
          </View>
        )}
        ListFooterComponent={loadingMore ? <ActivityIndicator color={C.green} style={x.footer} /> : null}
        initialNumToRender={8}
        maxToRenderPerBatch={8}
        windowSize={7}
        showsVerticalScrollIndicator={false}
      />

      {!!recentlyDeleted && (
        <View style={x.undoBar}>
          <Text style={x.undoText}>Bildirim silindi</Text>
          <Pressable onPress={() => void undoDelete()} style={x.undoButton}>
            <Text style={x.undoButtonText}>Geri al</Text>
          </Pressable>
        </View>
      )}

      <Modal visible={!!selectedNotification} transparent animationType="fade" onRequestClose={() => setSelectedNotification(null)}>
        <View style={x.modalOverlay}>
          <Pressable style={x.modalDismissArea} onPress={() => setSelectedNotification(null)} />
          <View style={x.modalCard}>
            <View style={x.modalHeader}>
              <Text style={x.modalEyebrow}>{selectedNotification?.type.includes('moderation') ? 'Y\u00d6NET\u0130M B\u0130LG\u0130LEND\u0130RMES\u0130' : 'DUYURU'}</Text>
              <Pressable accessibilityLabel="Duyuruyu kapat" onPress={() => setSelectedNotification(null)} style={x.modalClose}>
                <Text style={x.modalCloseText}>{"\u00d7"}</Text>
              </Pressable>
            </View>
            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={x.modalScroll}>
              <Text style={x.modalTitle}>{selectedNotification?.title}</Text>
              <Text style={x.modalTime}>{selectedNotification?.time}</Text>
              <Text style={x.modalBody}>{selectedNotification?.body}</Text>
            </ScrollView>
            <Pressable onPress={() => setSelectedNotification(null)} style={x.modalButton}>
              <Text style={x.modalButtonText}>Kapat</Text>
            </Pressable>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  header: { minHeight: 78, paddingHorizontal: 15, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { width: 42, height: 42, borderRadius: 21, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center' },
  backText: { color: C.ink, fontSize: 29, lineHeight: 32 },
  headerCopy: { flex: 1, marginLeft: 11 },
  eyebrow: { color: C.green, fontSize: 10, letterSpacing: 1.4, fontWeight: '900' },
  title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 },
  markAll: { minHeight: 42, justifyContent: 'center', paddingLeft: 8 },
  markAllText: { color: C.green, fontSize: 12, fontWeight: '900' },
  muted: { color: C.muted },
  tabs: { gap: 8, paddingHorizontal: 14, paddingTop: 14, paddingBottom: 8 },
  tab: { minHeight: 38, paddingHorizontal: 16, borderRadius: 19, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, alignItems: 'center', justifyContent: 'center' },
  tabActive: { backgroundColor: C.dark, borderColor: C.dark },
  tabText: { color: C.muted, fontSize: 12, fontWeight: '800' },
  tabTextActive: { color: C.white },
  filterRow: { minHeight: 44, paddingHorizontal: 15, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  resultText: { color: C.muted, fontSize: 11, fontWeight: '800' },
  unreadFilter: { minHeight: 32, flexDirection: 'row', alignItems: 'center', gap: 7, borderRadius: 16, paddingHorizontal: 11, backgroundColor: C.white, borderWidth: 1, borderColor: C.line },
  unreadFilterActive: { backgroundColor: C.soft, borderColor: '#B8D8C2' },
  unreadFilterText: { color: C.muted, fontSize: 11, fontWeight: '800' },
  unreadFilterTextActive: { color: C.green },
  filterDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: C.line },
  filterDotActive: { backgroundColor: C.green },
  list: { paddingHorizontal: 14, paddingBottom: 90 },
  emptyList: { flexGrow: 1, paddingHorizontal: 14, paddingBottom: 90 },
  swipeRow: { position: 'relative', marginBottom: 8, borderRadius: 18, overflow: 'hidden' },
  deleteBackground: { ...StyleSheet.absoluteFill, backgroundColor: '#A94636' },
  deleteAction: { position: 'absolute', right: 0, top: 0, bottom: 0, width: 104, alignItems: 'center', justifyContent: 'center' },
  deleteActionText: { color: C.white, fontSize: 14, fontWeight: '900' },
  row: { minHeight: 92, padding: 13, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, flexDirection: 'row', alignItems: 'center' },
  rowUnread: { borderColor: '#B8D8C2', backgroundColor: '#FBFDFB' },
  pressed: { opacity: 0.82 },
  icon: { width: 45, height: 45, borderRadius: 15, backgroundColor: '#EEF1EE', alignItems: 'center', justifyContent: 'center', marginRight: 11 },
  iconUnread: { backgroundColor: C.soft },
  iconText: { color: C.muted, fontSize: 20, fontWeight: '900' },
  iconTextUnread: { color: C.green },
  copy: { flex: 1 },
  titleRow: { flexDirection: 'row', alignItems: 'center' },
  rowTitle: { flex: 1, color: C.ink, fontSize: 13, fontWeight: '900' },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#D95442', marginLeft: 6 },
  body: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 4 },
  time: { color: '#95A199', fontSize: 10, marginTop: 5 },
  arrow: { color: C.muted, fontSize: 24, marginLeft: 7 },
  empty: { flex: 1, minHeight: 320, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, alignItems: 'center', justifyContent: 'center', padding: 28 },
  emptyIcon: { color: C.green, fontSize: 48 },
  emptyTitle: { color: C.ink, fontSize: 16, fontWeight: '900', marginTop: 10 },
  emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 6 },
  retry: { minHeight: 42, marginTop: 15, paddingHorizontal: 17, borderRadius: 14, backgroundColor: C.green, justifyContent: 'center' },
  retryText: { color: C.white, fontSize: 12, fontWeight: '900' },
  footer: { marginVertical: 18 },
  undoBar: { position: 'absolute', left: 16, right: 16, bottom: 18, minHeight: 54, borderRadius: 17, backgroundColor: C.dark, paddingHorizontal: 17, flexDirection: 'row', alignItems: 'center', shadowColor: '#000', shadowOpacity: 0.18, shadowRadius: 12, shadowOffset: { width: 0, height: 5 }, elevation: 7 },
  undoText: { flex: 1, color: C.white, fontSize: 13, fontWeight: '800' },
  undoButton: { minHeight: 38, paddingHorizontal: 12, alignItems: 'center', justifyContent: 'center' },
  undoButtonText: { color: '#9DDBAD', fontSize: 13, fontWeight: '900' },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(15, 34, 24, 0.42)', justifyContent: 'center', padding: 18 },
  modalDismissArea: { ...StyleSheet.absoluteFill },
  modalCard: { maxHeight: '78%', borderRadius: 24, backgroundColor: C.white, padding: 20, shadowColor: '#000', shadowOpacity: 0.18, shadowRadius: 20, shadowOffset: { width: 0, height: 8 }, elevation: 8 },
  modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  modalEyebrow: { color: C.green, fontSize: 11, fontWeight: '900', letterSpacing: 1.2 },
  modalClose: { width: 34, height: 34, borderRadius: 17, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center' },
  modalCloseText: { color: C.ink, fontSize: 23, lineHeight: 25 },
  modalScroll: { paddingVertical: 14 },
  modalTitle: { color: C.ink, fontSize: 20, lineHeight: 27, fontWeight: '900' },
  modalTime: { color: C.muted, fontSize: 11, marginTop: 6 },
  modalBody: { color: C.ink, fontSize: 15, lineHeight: 24, marginTop: 18 },
  modalButton: { minHeight: 46, borderRadius: 14, backgroundColor: C.dark, alignItems: 'center', justifyContent: 'center' },
  modalButtonText: { color: C.white, fontSize: 13, fontWeight: '900' },
});
