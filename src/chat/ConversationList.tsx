import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { C } from '../../styles';
import { listingCount, money, listingPrice } from '../../marketplace';
import { Conversation } from './types';
import UserAvatar from '../profile/UserAvatar';

const statusLabel: Record<Conversation['status'], string> = {
  inquiry: 'Görüşme',
  pending: 'Onay bekliyor',
  accepted: 'Rezerve',
  rejected: 'Reddedildi',
  cancelled: 'Rezervasyon iptal edildi',
  completed: 'Tamamlandı',
};

export default function ConversationList({ conversations, open }: { conversations: Conversation[]; open: (conversation: Conversation) => void }) {
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
      ) : conversations.map(conversation => (
        <Pressable key={conversation.id} onPress={() => open(conversation)} style={x.card}>
          <UserAvatar uri={conversation.counterpart.avatarUrl} name={conversation.counterpart.name} size={48} style={{ marginRight: 12 }} />
          <View style={x.copy}>
            <View style={x.nameRow}>
              <Text style={x.name}>{conversation.counterpart.name}</Text>
              <Text style={x.time}>{conversation.lastMessage?.time}</Text>
            </View>
            <Text style={x.listing}>{listingCount(conversation.listing)} ambalaj · {money(listingPrice(conversation.listing))}</Text>
            <Text numberOfLines={1} style={x.preview}>{conversation.lastMessage?.body || 'Görüşme başlatıldı.'}</Text>
            <View style={x.statusRow}>
              <Text style={x.status}>{statusLabel[conversation.status]}</Text>
              {conversation.unreadCount > 0 && <View style={x.unread}><Text style={x.unreadText}>{conversation.unreadCount}</Text></View>}
            </View>
          </View>
        </Pressable>
      ))}
    </ScrollView>
  );
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  content: { padding: 20, paddingBottom: 30 },
  eyebrow: { color: C.green, fontSize: 12, letterSpacing: 1.7, fontWeight: '900', marginTop: 8 },
  title: { color: C.ink, fontSize: 24, fontWeight: '800', marginTop: 5, marginBottom: 18 },
  card: { flexDirection: 'row', padding: 14, borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, marginBottom: 11 },
  avatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  avatarText: { color: C.dark, fontSize: 18, fontWeight: '900' },
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