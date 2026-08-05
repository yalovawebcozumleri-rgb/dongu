import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import UserAvatar from './UserAvatar';

type BlockedUser = { id: number; name: string; avatarUrl?: string | null; blockedAt: string | null };

export default function BlockedUsersScreen({ token, back, onBlocksChanged }: { token: string; back: () => void; onBlocksChanged: () => Promise<void> }) {
  const { showNotice, confirmNotice } = useNotice();
  const [users, setUsers] = useState<BlockedUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [pendingId, setPendingId] = useState<number | null>(null);
  const load = useCallback(async () => {
    setLoading(true);
    try { setUsers((await apiRequest<{ data: BlockedUser[] }>('/blocks', { token })).data); }
    catch (error) { showNotice({ tone: 'error', title: 'Engellenenler alınamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' }); }
    finally { setLoading(false); }
  }, [showNotice, token]);
  useEffect(() => { void load(); }, [load]);

  const unblock = async (user: BlockedUser) => {
    if (!await confirmNotice({ tone: 'info', title: 'Engeli kaldırmak istiyor musun?', message: `${user.name} ile yeniden iletişim kurabilirsin.`, primaryLabel: 'Engeli kaldır' })) return;
    setPendingId(user.id);
    try {
      await apiRequest(`/users/${user.id}/block`, { method: 'DELETE', token });
      setUsers(current => current.filter(item => item.id !== user.id));
      await onBlocksChanged();
      showNotice({ tone: 'success', title: 'Engel kaldırıldı', message: `${user.name} artık engellenenler listende değil.` });
    } catch (error) { showNotice({ tone: 'error', title: 'Engel kaldırılamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' }); }
    finally { setPendingId(null); }
  };

  return <View style={x.screen}><Header back={back} />{loading ? <View style={x.loading}><ActivityIndicator color={C.green} /></View> : <ScrollView contentContainerStyle={x.content}>{!users.length ? <View style={x.empty}><Text style={x.emptyIcon}>✓</Text><Text style={x.emptyTitle}>Engellediğin kimse yok</Text><Text style={x.emptyText}>Engellediğin hesapları daha sonra buradan yönetebilirsin.</Text></View> : users.map(user => <View key={user.id} style={x.row}><UserAvatar uri={user.avatarUrl} name={user.name} size={44} /><View style={x.copy}><Text style={x.name}>{user.name}</Text><Text style={x.meta}>Engellenen kullanıcı</Text></View><Pressable disabled={pendingId === user.id} onPress={() => void unblock(user)} style={x.unblock}>{pendingId === user.id ? <ActivityIndicator size="small" color="#913F32" /> : <Text style={x.unblockText}>Engeli kaldır</Text>}</Pressable></View>)}</ScrollView>}</View>;
}

function Header({ back }: { back: () => void }) { return <View style={x.header}><Pressable onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable><View><Text style={x.eyebrow}>GİZLİLİK</Text><Text style={x.title}>Engellediğim kullanıcılar</Text></View></View>; }
const x = StyleSheet.create({ screen: { flex: 1, backgroundColor: C.bg }, header: { minHeight: 76, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line }, back: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg, marginRight: 11 }, backText: { color: C.ink, fontSize: 30, lineHeight: 33 }, eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.3, fontWeight: '900' }, title: { color: C.ink, fontSize: 20, fontWeight: '900', marginTop: 2 }, loading: { flex: 1, alignItems: 'center', justifyContent: 'center' }, content: { padding: 18 }, row: { flexDirection: 'row', alignItems: 'center', padding: 14, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, marginBottom: 9 }, avatar: { width: 44, height: 44, borderRadius: 22, backgroundColor: C.soft, alignItems: 'center', justifyContent: 'center' }, avatarText: { color: C.dark, fontWeight: '900' }, copy: { flex: 1, marginLeft: 11 }, name: { color: C.ink, fontWeight: '900' }, meta: { color: C.muted, fontSize: 12, marginTop: 3 }, unblock: { minHeight: 38, minWidth: 90, borderRadius: 12, borderWidth: 1, borderColor: '#A54A3A', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 10 }, unblockText: { color: '#913F32', fontSize: 12, fontWeight: '900' }, empty: { alignItems: 'center', padding: 32, borderRadius: 22, backgroundColor: C.white }, emptyIcon: { width: 54, height: 54, borderRadius: 27, backgroundColor: C.soft, color: C.green, textAlign: 'center', textAlignVertical: 'center', fontSize: 24, fontWeight: '900' }, emptyTitle: { color: C.ink, fontSize: 16, fontWeight: '900', marginTop: 13 }, emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 6, textAlign: 'center' } });
