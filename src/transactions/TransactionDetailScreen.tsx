import React, { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { money } from '../../marketplace';
import { Conversation } from '../chat/types';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import UserAvatar from '../profile/UserAvatar';
import { C } from '../../styles';
import MonetizedAdSlot from '../advertising/MonetizedAdSlot';
import SponsoredBannerSlot from '../advertising/SponsoredBannerSlot';

const statusLabels: Record<Conversation['status'], string> = {
  inquiry: 'Görüşme',
  pending: 'Satıcı yanıtı bekleniyor',
  accepted: 'Teslimat için rezerve',
  rejected: 'Talep reddedildi',
  cancelled: 'Talep geri çekildi',
  completed: 'Teslimat tamamlandı',
  closed: 'Görüşme kapandı',
};

const formatDate = (value: string | null | undefined) => value
  ? new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
  : '—';

export default function TransactionDetailScreen({ item, token, back, openMessages, onUpdated }: {
  item: Conversation;
  token: string;
  back: () => void;
  openMessages: (conversation: Conversation) => void;
  onUpdated: (conversation: Conversation) => void;
}) {
  const { showNotice } = useNotice();
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const [busy, setBusy] = useState(false);
  const listing = item.listingSummary ?? item.listing;
  const materials = Array.isArray(listing?.items) ? listing.items : [];
  const itemCount = materials.reduce((sum, material) => sum + material.count, 0);
  const totalPrice = materials.reduce((sum, material) => sum + material.count * material.unitPrice, 0);
  const statusLabel = item.status === 'cancelled' && item.cancelledByRole === 'seller'
    ? 'Satıcı rezervasyonu iptal etti'
    : statusLabels[item.status];

  const submitReview = async () => {
    if (!rating || busy) {
      if (!rating) showNotice({ tone: 'warning', title: 'Puanını seç', message: 'Değerlendirmeyi göndermek için 1 ile 5 arasında yıldız seçmelisin.' });
      return;
    }
    setBusy(true);
    try {
      const response = await apiRequest<{ data: Conversation }>(`/pickup-requests/${item.id}/review`, {
        method: 'POST',
        token,
        body: { rating, comment: comment.trim() || null },
      });
      onUpdated(response.data);
      showNotice({ tone: 'success', title: 'Değerlendirmen alındı', message: 'Deneyimini paylaştığın için teşekkür ederiz.' });
    } catch (error) {
      showNotice({ tone: 'error', title: 'Değerlendirme gönderilemedi', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={x.screen}>
      <View style={x.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Alım taleplerine dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable>
        <View style={x.headerCopy}><Text style={x.eyebrow}>İŞLEM KAYDI</Text><Text style={x.title}>İşlem detayı</Text></View>
      </View>
      <ScrollView contentContainerStyle={x.content} showsVerticalScrollIndicator={false}>
        <View style={x.statusCard}>
          <Text style={x.status}>{statusLabel}</Text>
          <Text style={x.date}>{formatDate(item.updatedAt)}</Text>
        </View>
        <SponsoredBannerSlot placement="transaction_detail" token={token} />

        <View style={x.card}>
          <Text style={x.sectionLabel}>İŞLEM YAPILAN KULLANICI</Text>
          <View style={x.personRow}>
            <UserAvatar uri={item.counterpart.avatarUrl} name={item.counterpart.name} size={48} />
            <View style={x.personCopy}><Text style={x.personName}>{item.counterpart.name}</Text><Text style={x.personMeta}>{item.counterpart.ratingCount ? `${item.counterpart.rating?.toFixed(1) ?? '—'} puan · ${item.counterpart.ratingCount} değerlendirme` : 'Henüz değerlendirme yok'}</Text></View>
          </View>
        </View>

        <View style={x.card}>
          <Text style={x.sectionLabel}>İLAN ÖZETİ</Text>
          <Text style={x.listingTitle}>{itemCount} adet ambalaj</Text>
          <Text style={x.materials}>{listing?.items.map(material => `${material.material} ${material.count} adet`).join(' · ') || 'İlan özeti bulunmuyor'}</Text>
          <View style={x.summaryRow}>
            <View><Text style={x.summaryLabel}>İlan bedeli</Text><Text style={x.summaryValue}>{money(totalPrice)}</Text></View>
            <View style={x.summaryRight}><Text style={x.summaryLabel}>Bölge</Text><Text style={x.summaryValueSmall}>{listing?.district || '—'}</Text></View>
          </View>
          {!item.listingAvailable && <View style={x.info}><Text style={x.infoText}>İlan artık yayında değil. İşlem sırasında kaydedilen ilan özeti gösteriliyor.</Text></View>}
        </View>

        <View style={x.card}>
          <Text style={x.sectionLabel}>İŞLEM ZAMANLARI</Text>
          <TimelineRow label="Son hareket" value={formatDate(item.updatedAt)} />
          {!!item.acceptedAt && <TimelineRow label="Talep kabul edildi" value={formatDate(item.acceptedAt)} />}
          {!!item.cancelledAt && <TimelineRow label="İşlem iptal edildi" value={formatDate(item.cancelledAt)} />}
          {!!item.completedAt && <TimelineRow label="Teslimat tamamlandı" value={formatDate(item.completedAt)} />}
          {!!item.closedAt && <TimelineRow label="Görüşme kapandı" value={formatDate(item.closedAt)} />}
        </View>

        {item.canReview && (
          <View style={x.card}>
            <Text style={x.sectionLabel}>DEĞERLENDİRME</Text>
            <Text style={x.cardTitle}>{item.counterpart.name} ile işlemin nasıldı?</Text>
            <Text style={x.help}>Teslimattan sonraki 24 saat içinde değerlendirme yapabilirsin.</Text>
            <View style={x.stars}>{[1, 2, 3, 4, 5].map(value => <Pressable key={value} onPress={() => setRating(value)}><Text style={[x.star, value <= rating && x.starActive]}>★</Text></Pressable>)}</View>
            <TextInput value={comment} onChangeText={setComment} multiline maxLength={500} placeholder="Yorumun (isteğe bağlı)" placeholderTextColor="#87948C" style={x.input} />
            <Pressable disabled={busy} onPress={() => void submitReview()} style={[x.primary, busy && x.disabled]}>{busy ? <ActivityIndicator color={C.white} /> : <Text style={x.primaryText}>Değerlendirmeyi gönder</Text>}</Pressable>
          </View>
        )}

        {!item.canReview && item.reviewed && <View style={x.success}><Text style={x.successText}>✓ Bu işlem için değerlendirmen alındı.</Text></View>}

        {item.canOpenConversation ? (
          <Pressable accessibilityRole="button" onPress={() => openMessages(item)} style={x.secondary}><Text style={x.secondaryText}>Mesaj geçmişini görüntüle</Text></Pressable>
        ) : item.conversationHidden && item.hasMessages ? (
          <View style={x.info}><Text style={x.infoText}>Bu sohbeti daha önce mesaj listesinden kaldırdın. İşlem kaydın korunmaya devam ediyor.</Text></View>
        ) : (
          <View style={x.info}><Text style={x.infoText}>Bu işlem için görüntülenecek bir mesaj geçmişi bulunmuyor.</Text></View>
        )}
      </ScrollView>
        <MonetizedAdSlot placement="transaction_detail" token={token} />
    </View>
  );
}

function TimelineRow({ label, value }: { label: string; value: string }) {
  return <View style={x.timelineRow}><Text style={x.timelineLabel}>{label}</Text><Text style={x.timelineValue}>{value}</Text></View>;
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  header: { minHeight: 82, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg },
  backText: { color: C.ink, fontSize: 30, lineHeight: 33 },
  headerCopy: { flex: 1, marginLeft: 12 },
  eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.4, fontWeight: '900' },
  title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 },
  content: { padding: 16, paddingBottom: 36, gap: 12 },
  statusCard: { padding: 16, borderRadius: 18, backgroundColor: C.dark, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12 },
  status: { flex: 1, color: C.white, fontSize: 15, fontWeight: '900' },
  date: { color: '#D6E2D7', fontSize: 11, fontWeight: '700' },
  card: { padding: 16, borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.line },
  sectionLabel: { color: C.green, fontSize: 10, letterSpacing: 1.2, fontWeight: '900' },
  personRow: { flexDirection: 'row', alignItems: 'center', marginTop: 13 },
  personCopy: { flex: 1, marginLeft: 11 },
  personName: { color: C.ink, fontSize: 16, fontWeight: '900' },
  personMeta: { color: C.muted, fontSize: 11, marginTop: 4 },
  listingTitle: { color: C.ink, fontSize: 20, fontWeight: '900', marginTop: 12 },
  materials: { color: C.muted, fontSize: 12, lineHeight: 19, marginTop: 4 },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 16, marginTop: 15, padding: 13, borderRadius: 14, backgroundColor: '#F7F9F6' },
  summaryRight: { flex: 1, alignItems: 'flex-end' },
  summaryLabel: { color: C.muted, fontSize: 10, fontWeight: '800' },
  summaryValue: { color: C.ink, fontSize: 16, fontWeight: '900', marginTop: 3 },
  summaryValueSmall: { color: C.ink, fontSize: 12, fontWeight: '900', marginTop: 3, textAlign: 'right' },
  info: { padding: 13, borderRadius: 14, backgroundColor: '#F1F5F0', marginTop: 12 },
  infoText: { color: C.muted, fontSize: 12, lineHeight: 18, fontWeight: '700' },
  timelineRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 16, paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: C.line },
  timelineLabel: { color: C.muted, fontSize: 12, fontWeight: '700' },
  timelineValue: { flex: 1, color: C.ink, fontSize: 12, fontWeight: '800', textAlign: 'right' },
  cardTitle: { color: C.ink, fontSize: 17, fontWeight: '900', marginTop: 12 },
  help: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 5 },
  stars: { flexDirection: 'row', gap: 8, marginTop: 14 },
  star: { color: '#C9CEC9', fontSize: 34 },
  starActive: { color: '#E3A72F' },
  input: { minHeight: 92, marginTop: 12, padding: 13, borderRadius: 14, borderWidth: 1, borderColor: C.line, color: C.ink, fontSize: 13, textAlignVertical: 'top' },
  primary: { minHeight: 48, marginTop: 12, borderRadius: 14, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center' },
  disabled: { opacity: 0.6 },
  primaryText: { color: C.white, fontSize: 12, fontWeight: '900' },
  secondary: { minHeight: 48, borderRadius: 14, borderWidth: 1, borderColor: '#A9C9B0', backgroundColor: C.white, alignItems: 'center', justifyContent: 'center' },
  secondaryText: { color: C.green, fontSize: 12, fontWeight: '900' },
  success: { padding: 14, borderRadius: 14, backgroundColor: '#E8F3EA' },
  successText: { color: C.green, fontSize: 12, fontWeight: '900' },
});
