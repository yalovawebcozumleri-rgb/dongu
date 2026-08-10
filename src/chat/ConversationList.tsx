import React, { useRef } from 'react';
import { Animated, PanResponder, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { C } from '../../styles';
import { money } from '../../marketplace';
import { Conversation } from './types';
import UserAvatar from '../profile/UserAvatar';
import MonetizedAdSlot from '../advertising/MonetizedAdSlot';
import { insertAdvertisementSlots } from '../advertising/listSlots';
import { useAdvertisements } from '../advertising/useAdvertisements';
import { formatLocalMessageTime } from './time';

const statusLabel: Record<Conversation['status'], string> = {
  inquiry: 'Görüşme',
  pending: 'Onay bekliyor',
  accepted: 'Rezerve',
  rejected: 'Reddedildi',
  cancelled: 'Rezervasyon iptal edildi',
  completed: 'Tamamlandı',
  closed: 'Görüşme kapandı',
};

const canDeleteConversation = (conversation: Conversation) => conversation.isBlocked
  || ['rejected', 'cancelled', 'completed', 'closed'].includes(conversation.status)
  || conversation.status === 'inquiry';

const listingLabel = (conversation: Conversation) => {
  const summary = conversation.listing ?? conversation.listingSummary;
  if (!summary) return 'İlan özeti bulunmuyor';
  const count = summary.items.reduce((total, item) => total + item.count, 0);
  const price = summary.items.reduce((total, item) => total + item.count * item.unitPrice, 0);
  return `${count} ambalaj · ${money(price)}`;
};
type HideConversation = (conversation: Conversation) => Promise<boolean> | boolean;

function SwipeRow({ conversation, onHide, children }: { conversation: Conversation; onHide?: HideConversation; children: React.ReactNode }) {
  const translateX = useRef(new Animated.Value(0)).current;
  const widthRef = useRef(0);
  const deletingRef = useRef(false);
  const canDelete = Boolean(onHide) && canDeleteConversation(conversation);
  const canDeleteRef = useRef(canDelete);
  canDeleteRef.current = canDelete;

  const animateTo = (value: number, callback?: () => void) => {
    Animated.spring(translateX, { toValue: value, useNativeDriver: true, bounciness: 0, speed: 20 }).start(({ finished }) => {
      if (finished) callback?.();
    });
  };

  const commitDelete = async () => {
    if (!onHide || deletingRef.current) return;
    deletingRef.current = true;
    const deleted = await onHide(conversation);
    deletingRef.current = false;
    if (!deleted) animateTo(0);
  };

  const deleteWithAnimation = () => {
    const width = Math.max(widthRef.current, 280);
    Animated.timing(translateX, { toValue: -width, duration: 190, useNativeDriver: true }).start(({ finished }) => {
      if (finished) void commitDelete();
    });
  };

  const responder = useRef(PanResponder.create({
    onMoveShouldSetPanResponder: (_, gesture) => canDeleteRef.current && gesture.dx < -12 && Math.abs(gesture.dx) > Math.abs(gesture.dy),
    onPanResponderMove: (_, gesture) => {
      if (!canDeleteRef.current) return;
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
      <Pressable accessibilityRole="button" accessibilityLabel="Sohbeti sil" disabled={!canDelete} onPress={deleteWithAnimation} style={x.deleteAction}>
        <Text style={x.deleteActionText}>Sil</Text>
      </Pressable>
      <Animated.View
        onLayout={event => { widthRef.current = event.nativeEvent.layout.width; }}
        style={[x.swipeContent, { transform: [{ translateX }] }]}
        {...responder.panHandlers}
      >
        {children}
      </Animated.View>
    </View>
  );
}

export default function ConversationList({ conversations, open, onHide }: { conversations: Conversation[]; open: (conversation: Conversation) => void; onHide?: HideConversation }) {
  const advertisementCollection = useAdvertisements('messages_list');
  const listData = insertAdvertisementSlots(conversations, item => String(item.id), advertisementCollection?.meta);

  return (
    <ScrollView style={x.screen} contentContainerStyle={x.content}>
      <Text style={x.eyebrow}>GÖRÜŞMELER</Text>
      <Text style={x.title}>Mesajlar</Text>
      {!conversations.length ? (
        <View style={x.empty}>
          <Text style={x.emptyIcon}>◎</Text>
          <Text style={x.emptyTitle}>Henüz mesajın yok</Text>
          <Text style={x.emptyText}>Bir ilan hakkında yazdığında veya alım talebi aldığında konuşma burada görünecek.</Text>
        </View>
      ) : listData.map(row => row.kind === 'advertisement'
        ? <MonetizedAdSlot key={row.key} placement="messages_list" slotIndex={row.slotIndex} itemCount={conversations.length} /> : ((conversation: Conversation) => (
        <SwipeRow key={conversation.id} conversation={conversation} onHide={onHide}>
          <Pressable onPress={() => open(conversation)} style={x.card}>
            <UserAvatar uri={conversation.counterpart.avatarUrl} name={conversation.counterpart.name} size={48} style={{ marginRight: 12 }} />
            <View style={x.copy}>
              <View style={x.nameRow}>
                <Text style={x.name}>{conversation.counterpart.name}</Text>
                <Text style={x.time}>{formatLocalMessageTime(conversation.lastMessage?.createdAt, conversation.lastMessage?.time)}</Text>
              </View>
              <Text style={x.listing}>{listingLabel(conversation)}</Text>
              <Text numberOfLines={1} style={x.preview}>{conversation.lastMessage?.body || 'Görüşme başlatıldı.'}</Text>
              <View style={x.statusRow}>
                <Text style={x.status}>{statusLabel[conversation.status]}</Text>
                {conversation.unreadCount > 0 && <View style={x.unread}><Text style={x.unreadText}>{conversation.unreadCount}</Text></View>}
              </View>
            </View>
          </Pressable>
        </SwipeRow>
      ))(row.item))}
    </ScrollView>
  );
}

const x = StyleSheet.create({
  swipeRow: { position: 'relative', marginBottom: 11, borderRadius: 20, overflow: 'hidden' },
  swipeContent: { zIndex: 1, backgroundColor: C.bg },
  deleteBackground: { ...StyleSheet.absoluteFill, borderRadius: 20, backgroundColor: '#A94636' },
  deleteAction: { position: 'absolute', right: 0, top: 0, bottom: 0, width: 104, alignItems: 'center', justifyContent: 'center' },
  deleteActionText: { color: '#FFFFFF', fontSize: 14, fontWeight: '900' },
  screen: { flex: 1, backgroundColor: C.bg },
  content: { padding: 20, paddingBottom: 30 },
  eyebrow: { color: C.green, fontSize: 12, letterSpacing: 1.7, fontWeight: '900', marginTop: 8 },
  title: { color: C.ink, fontSize: 24, fontWeight: '800', marginTop: 5, marginBottom: 18 },
  card: { flexDirection: 'row', padding: 14, borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.line },
  copy: { flex: 1 },
  nameRow: { flexDirection: 'row', alignItems: 'center' },
  name: { flex: 1, color: C.ink, fontSize: 16, fontWeight: '900' },
  time: { color: C.muted, fontSize: 12 },
  listing: { color: C.green, fontSize: 12, fontWeight: '800', marginTop: 4 },
  preview: { color: C.muted, fontSize: 13, lineHeight: 18, marginTop: 6 },
  statusRow: { flexDirection: 'row', alignItems: 'center', marginTop: 8 },
  status: { color: C.dark, backgroundColor: C.soft, borderRadius: 9, paddingHorizontal: 8, paddingVertical: 5, fontSize: 12, fontWeight: '900' },
  unread: { marginLeft: 'auto', minWidth: 20, height: 20, borderRadius: 10, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 5 },
  unreadText: { color: C.white, fontSize: 11, fontWeight: '900' },
  empty: { alignItems: 'center', padding: 30, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.line },
  emptyIcon: { color: C.green, fontSize: 38 },
  emptyTitle: { color: C.ink, fontSize: 16, fontWeight: '900', marginTop: 8 },
  emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 6 },
});