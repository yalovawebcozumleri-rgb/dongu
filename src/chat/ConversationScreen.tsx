import React, { Fragment, useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Keyboard, KeyboardAvoidingView, Linking, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import * as Clipboard from 'expo-clipboard';
import { C } from '../../styles';
import { listingCount, listingPrice, money } from '../../marketplace';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import { Conversation, ConversationMessage, ConversationResponse, MessageCollectionResponse } from './types';
import UserAvatar from '../profile/UserAvatar';

const statusText: Record<Conversation['status'], string> = {
  inquiry: 'İlan hakkında görüşme',
  pending: 'Alım talebi onay bekliyor',
  accepted: 'İlan rezerve edildi',
  rejected: 'Alım talebi reddedildi',
  cancelled: 'Alım talebi geri çekildi',
  completed: 'Teslimat tamamlandı',
};

export default function ConversationScreen({
  conversation,
  token,
  back,
  onUpdated,
  onRead,
  onBlockChanged,
  onHidden,
  openProfile,
  refreshSignal,
  bottomInset,
}: {
  conversation: Conversation;
  token: string;
  back: () => void;
  onUpdated: (conversation: Conversation) => void;
  onRead: (conversationId: number) => void;
  onBlockChanged: () => Promise<void>;
  onHidden: () => void;
  openProfile: () => void;
  refreshSignal: number;
  bottomInset: number;
}) {
  const { showNotice, confirmNotice } = useNotice();
  const [messages, setMessages] = useState<ConversationMessage[]>([]);
  const [draft, setDraft] = useState('');
  const [deliveryCode, setDeliveryCode] = useState('');
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const [busy, setBusy] = useState(false);
  const [keyboardVisible, setKeyboardVisible] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [nextCursor, setNextCursor] = useState<number | null>(null);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [actionMessage, setActionMessage] = useState<ConversationMessage | null>(null);
  const [reportingMessage, setReportingMessage] = useState<ConversationMessage | null>(null);
  const scrollRef = useRef<ScrollView>(null);

  const markRead = useCallback(async (items: ConversationMessage[]) => {
    const lastIncoming = [...items].reverse().find(item => item.sender === 'other' && typeof item.id === 'number');
    if (!lastIncoming) return;
    await apiRequest(`/pickup-requests/${conversation.id}/read`, { method: 'POST', token, body: { last_message_id: lastIncoming.id } });
    if (conversation.unreadCount > 0) onRead(conversation.id);
  }, [conversation.id, conversation.unreadCount, onRead, token]);

  const loadMessages = useCallback(async (quiet = false) => {
    try {
      const response = await apiRequest<MessageCollectionResponse>(`/pickup-requests/${conversation.id}/messages?per_page=30`, { token });
      setMessages(response.data);
      setHasMore(response.meta.hasMore);
      setNextCursor(response.meta.nextCursor);
      await markRead(response.data);
    } catch (error) {
      if (!quiet) showNotice({ tone: 'error', title: 'Mesajlar alınamadı', message: error instanceof ApiError ? error.message : 'Mesaj servisine ulaşılamadı.' });
    }
  }, [conversation.id, markRead, showNotice, token]);

  const loadOlder = async () => {
    if (!nextCursor || loadingOlder) return;
    setLoadingOlder(true);
    try {
      const response = await apiRequest<MessageCollectionResponse>(`/pickup-requests/${conversation.id}/messages?per_page=30&before_id=${nextCursor}`, { token });
      setMessages(currentItems => [...response.data, ...currentItems]);
      setHasMore(response.meta.hasMore);
      setNextCursor(response.meta.nextCursor);
    } catch (error) {
      showNotice({ tone: 'error', title: 'Eski mesajlar alınamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    } finally { setLoadingOlder(false); }
  };

  useEffect(() => { void loadMessages(); }, [loadMessages]);
  useEffect(() => { if (refreshSignal > 0) void loadMessages(true); }, [refreshSignal]);

  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';
    const showSubscription = Keyboard.addListener(showEvent, () => setKeyboardVisible(true));
    const hideSubscription = Keyboard.addListener(hideEvent, () => setKeyboardVisible(false));
    return () => {
      showSubscription.remove();
      hideSubscription.remove();
    };
  }, []);
  const deliverMessage = async (clientId: string, text: string) => {
    try {
      const response = await apiRequest<{ data: ConversationMessage }>(`/pickup-requests/${conversation.id}/messages`, { method: 'POST', token, body: { message: text, client_id: clientId } });
      setMessages(current => current.map(item => item.clientId === clientId ? response.data : item));
    } catch {
      setMessages(current => current.map(item => item.clientId === clientId ? { ...item, deliveryState: 'failed' } : item));
    }
  };

  const send = async () => {
    const clean = draft.trim();
    if (!clean || busy || conversationReadOnly) return;
    const clientId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, char => { const random = Math.floor(Math.random() * 16); return (char === 'x' ? random : (random & 0x3) | 0x8).toString(16); });
    const optimistic: ConversationMessage = { id: `local-${clientId}`, clientId, sender: 'me', text: clean, time: new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }), createdAt: new Date().toISOString(), readAt: null, deliveryState: 'sending' };
    setDraft('');
    setMessages(current => [...current, optimistic]);
    await deliverMessage(clientId, clean);
  };

  const retryMessage = async (message: ConversationMessage) => {
    if (!message.clientId || message.deliveryState !== 'failed') return;
    setMessages(current => current.map(item => item.clientId === message.clientId ? { ...item, deliveryState: 'sending' } : item));
    await deliverMessage(message.clientId, message.text);
  };

  const openMessageActions = (message: ConversationMessage) => {
    if (message.sender === 'system' || message.deliveryState === 'sending') return;
    if (message.deliveryState === 'failed') {
      void retryMessage(message);
      return;
    }
    setActionMessage(message);
  };

  const copyMessage = async () => {
    if (!actionMessage) return;
    try {
      const copied = await Clipboard.setStringAsync(actionMessage.text);
      setActionMessage(null);
      showNotice(copied
        ? { tone: 'success', title: 'Mesaj kopyalandı', message: 'Mesaj metni panoya kopyalandı.' }
        : { tone: 'error', title: 'Mesaj kopyalanamadı', message: 'Telefonun pano servisine ulaşılamadı.' });
    } catch {
      setActionMessage(null);
      showNotice({ tone: 'error', title: 'Mesaj kopyalanamadı', message: 'Telefonun pano servisine ulaşılamadı.' });
    }
  };

  const chooseReportMessage = () => {
    if (!actionMessage || actionMessage.sender !== 'other' || typeof actionMessage.id !== 'number') return;
    const message = actionMessage;
    setActionMessage(null);
    setReportingMessage(message);
  };

  const reportMessage = async (reason: string) => {
    if (!reportingMessage || typeof reportingMessage.id !== 'number') return;
    try {
      await apiRequest(`/pickup-requests/${conversation.id}/messages/${reportingMessage.id}/report`, { method: 'POST', token, body: { reason } });
      setReportingMessage(null);
      showNotice({ tone: 'success', title: 'Mesaj bildirildi', message: 'Güvenlik ekibimiz bildirimi inceleyecek. Karşı tarafa bildirim gönderilmeyecek.' });
    } catch (error) {
      showNotice({ tone: 'error', title: 'Bildirim gönderilemedi', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    }
  };

  const hideConversation = async () => {
    if (!await confirmNotice({ tone: 'warning', title: 'Sohbeti listenden kaldır?', message: 'Mesajlar silinmeyecek; yalnızca senin mesaj listen gizlenecek.', primaryLabel: 'Listemden kaldır' })) return;
    try {
      await apiRequest(`/pickup-requests/${conversation.id}/conversation`, { method: 'DELETE', token });
      onHidden();
    } catch (error) {
      showNotice({ tone: 'error', title: 'Sohbet kaldırılamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    }
  };

  const runAction = async (action: 'accept' | 'reject' | 'cancel' | 'complete', body?: Record<string, unknown>) => {
    setBusy(true);
    try {
      const response = await apiRequest<ConversationResponse>(`/pickup-requests/${conversation.id}/${action}`, { method: 'POST', token, body });
      onUpdated(response.data);
      await loadMessages(true);
    } catch (error) {
      showNotice({ tone: 'error', title: 'İşlem tamamlanamadı', message: error instanceof ApiError ? error.message : 'İşlem servisine ulaşılamadı.' });
    } finally {
      setBusy(false);
    }
  };

  const reject = async () => {
    if (await confirmNotice({ tone: 'warning', title: 'Talebi reddetmek istiyor musun?', message: 'Alıcıya talebin kabul edilmediği bildirilecek.', primaryLabel: 'Talebi reddet' })) {
      await runAction('reject');
    }
  };

  const cancel = async () => {
    const sellerCancellation = conversation.role === 'seller';
    const confirmed = await confirmNotice({
      tone: 'warning',
      title: sellerCancellation ? 'Rezervasyonu iptal etmek istiyor musun?' : 'Alım talebini geri çekmek istiyor musun?',
      message: sellerCancellation
        ? 'İlan yeniden aktif hale gelecek ve teslim kodu geçersiz olacak. Mesajlaşma açık kalacak.'
        : 'Mesajlaşma açık kalacak. Rezerve edilmiş ilan varsa yeniden aktif hale gelecek.',
      primaryLabel: sellerCancellation ? 'Rezervasyonu iptal et' : 'Talebi geri çek',
    });
    if (confirmed) await runAction('cancel');
  };

  const toggleBlock = async () => {
    if (conversation.isBlocked && !conversation.blockedByMe) return;
    const removing = conversation.blockedByMe;
    const confirmed = await confirmNotice({
      tone: removing ? 'info' : 'warning',
      title: removing ? 'Engeli kaldırmak istiyor musun?' : `${conversation.counterpart.name} kullanıcısını engellemek istiyor musun?`,
      message: removing
        ? 'Bu kullanıcı ilanlarını yeniden görebilecek ve açık görüşmelerde iletişim kurulabilecek.'
        : 'Birbirinizin ilanlarını göremeyecek ve mesaj gönderemeyeceksiniz. Aktif rezervasyon varsa iptal edilecek.',
      primaryLabel: removing ? 'Engeli kaldır' : 'Kullanıcıyı engelle',
    });
    if (!confirmed) return;

    setBusy(true);
    try {
      await apiRequest(`/users/${conversation.counterpart.id}/block`, { method: removing ? 'DELETE' : 'POST', token });
      await onBlockChanged();
      showNotice({
        tone: 'success',
        title: removing ? 'Engel kaldırıldı' : 'Kullanıcı engellendi',
        message: removing ? 'Bu kullanıcıyla yeniden etkileşim kurabilirsin.' : 'Bu kullanıcı artık sana ilan veya mesaj yoluyla ulaşamaz.',
      });
    } catch (error) {
      showNotice({ tone: 'error', title: 'İşlem tamamlanamadı', message: error instanceof ApiError ? error.message : 'Engelleme servisine ulaşılamadı.' });
    } finally {
      setBusy(false);
    }
  };

  const complete = async () => {
    if (!/^\d{4}$/.test(deliveryCode)) {
      showNotice({ tone: 'warning', title: 'Teslim kodunu gir', message: 'Alıcının ekranında görünen 4 haneli teslim kodunu girmelisin.' });
      return;
    }
    await runAction('complete', { code: deliveryCode });
    setDeliveryCode('');
  };

  const copyDeliveryAddress = async () => {
    if (!conversation.exactAddress) return;
    try {
      const copied = await Clipboard.setStringAsync(conversation.exactAddress);
      showNotice(copied
        ? { tone: 'success', title: 'Adres kopyalandı', message: 'Teslimat adresi panoya kopyalandı.' }
        : { tone: 'error', title: 'Adres kopyalanamadı', message: 'Telefonun pano servisine ulaşılamadı.' });
    } catch {
      showNotice({ tone: 'error', title: 'Adres kopyalanamadı', message: 'Telefonun pano servisine ulaşılamadı.' });
    }
  };

  const openDeliveryAddress = async () => {
    if (!conversation.exactAddress) return;
    const hasCoordinates = typeof conversation.exactLatitude === 'number' && typeof conversation.exactLongitude === 'number';
    const latitude = conversation.exactLatitude;
    const longitude = conversation.exactLongitude;
    const encodedAddress = encodeURIComponent(conversation.exactAddress);
    const coordinateQuery = hasCoordinates ? `${latitude},${longitude}` : conversation.exactAddress;
    const nativeUrl = Platform.OS === 'ios'
      ? hasCoordinates
        ? `maps://?ll=${latitude},${longitude}&q=${encodedAddress}`
        : `maps://?q=${encodedAddress}`
      : hasCoordinates
        ? `geo:${latitude},${longitude}?q=${latitude},${longitude}(${encodedAddress})`
        : `geo:0,0?q=${encodedAddress}`;
    const fallbackUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(coordinateQuery)}`;

    try {
      await Linking.openURL(nativeUrl);
    } catch {
      try {
        await Linking.openURL(fallbackUrl);
      } catch {
        showNotice({ tone: 'error', title: 'Harita açılamadı', message: 'Adresi kopyalayıp harita uygulamanda arayabilirsin.' });
      }
    }
  };

  const submitReview = async () => {
    if (!rating) {
      showNotice({ tone: 'warning', title: 'Puanını seç', message: 'Değerlendirme göndermek için 1 ile 5 arasında yıldız seçmelisin.' });
      return;
    }
    setBusy(true);
    try {
      const response = await apiRequest<ConversationResponse>(`/pickup-requests/${conversation.id}/review`, {
        method: 'POST', token, body: { rating, comment: comment.trim() || null },
      });
      onUpdated(response.data);
      showNotice({ tone: 'success', title: 'Değerlendirmen yayınlandı', message: 'Puanın gerçek kullanıcı ortalamasına eklendi.' });
    } catch (error) {
      showNotice({ tone: 'error', title: 'Değerlendirme gönderilemedi', message: error instanceof ApiError ? error.message : 'Değerlendirme servisine ulaşılamadı.' });
    } finally {
      setBusy(false);
    }
  };


  const currentStatusText = conversation.isBlocked
    ? conversation.blockedByMe ? 'Bu kullanıcıyı engelledin' : 'Bu kullanıcı seni engelledi'
    : conversation.status === 'cancelled'
      ? conversation.cancelledByRole === 'seller'
        ? 'Satıcı rezervasyonu iptal etti'
        : 'Alım talebi geri çekildi'
      : statusText[conversation.status];
  const quickReplies = conversation.role === 'buyer'
    ? conversation.status === 'accepted'
      ? ['Bugün teslim alabilir miyim?', 'Konum size uygun mu?', 'Teslim için ne zaman uygunsunuz?']
      : ['Ürünler hâlâ mevcut mu?', 'Ne zaman teslim alabilirim?', 'Konum size uygun mu?']
    : conversation.status === 'accepted'
      ? ['Ne zaman gelebilirsiniz?', 'Konum sizin için uygun mu?', 'Teslim kodunu hazırlar mısınız?']
      : ['Ne zaman teslim alabilirsiniz?', 'Konum sizin için uygun mu?', 'Talebinizle ilgili bir şey soracağım.'];
  const conversationReadOnly = conversation.status === 'completed' || conversation.status === 'rejected' || conversation.isBlocked;

  return (
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} keyboardVerticalOffset={0} style={x.screen}>
      <View style={x.header}>
        <Pressable onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable>
        <Pressable accessibilityRole="button" accessibilityLabel="Kullanıcı profilini aç" onPress={openProfile} style={x.profileLink}>
          <UserAvatar uri={conversation.counterpart.avatarUrl} name={conversation.counterpart.name} size={46} style={{ marginRight: 10 }} />
          <View style={x.headerCopy}>
            <Text style={x.name}>{conversation.counterpart.name}</Text>
            <Text style={x.status}>{currentStatusText}</Text>
          </View>
        </Pressable>
        {(conversation.isBlocked || ['rejected', 'cancelled', 'completed'].includes(conversation.status)) && (
          <Pressable disabled={busy} onPress={() => void hideConversation()} style={x.hideButton}><Text style={x.hideButtonText}>Listeden kaldır</Text></Pressable>
        )}
        {(!conversation.isBlocked || conversation.blockedByMe) && (
          <Pressable disabled={busy} onPress={toggleBlock} style={x.blockButton}>
            <Text style={x.blockButtonText}>{conversation.blockedByMe ? 'Engeli kaldır' : 'Engelle'}</Text>
          </Pressable>
        )}
      </View>

      <View style={x.listing}>
        <View style={{ flex: 1 }}>
          <Text style={x.listingTitle}>{listingCount(conversation.listing)} ambalaj · {conversation.listing.items.map(item => item.material).join(' + ')}</Text>
          <Text style={x.listingSub}>{conversation.listing.district}</Text>
        </View>
        <Text style={x.price}>{money(listingPrice(conversation.listing))}</Text>
      </View>

      <ScrollView
        ref={scrollRef}
        style={x.messages}
        contentContainerStyle={x.messageContent}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode={Platform.OS === 'ios' ? 'interactive' : 'on-drag'}
        onContentSizeChange={() => scrollRef.current?.scrollToEnd({ animated: true })}
      >
        {conversation.status === 'pending' && conversation.role === 'seller' && (
        <View style={x.actionCard}>
          <Text style={x.actionTitle}>Bu alım talebini değerlendir</Text>
          <View style={x.actionRow}>
            <Pressable disabled={busy} onPress={reject} style={x.outlineButton}><Text style={x.outlineText}>Reddet</Text></Pressable>
            <Pressable disabled={busy} onPress={() => runAction('accept')} style={x.greenButton}><Text style={x.greenText}>Kabul et</Text></Pressable>
          </View>
        </View>
      )}


      {conversation.status === 'completed' && (
        <View style={x.reviewCard}>
          <Text style={x.completedTitle}>✓ Teslimat tamamlandı</Text>
          <Text style={x.completedText}>Güvenlik için bu görüşme yeni mesajlara kapatıldı. Mesaj geçmişin korunmaya devam edecek.</Text>
          {conversation.canReview ? (
            <>
              <Text style={x.reviewDeadline}>Değerlendirme için teslimattan sonra 24 saatin var.</Text>
              <Text style={x.actionTitle}>{conversation.counterpart.name} kullanıcısını değerlendir</Text>
              <View style={x.stars}>
                {[1, 2, 3, 4, 5].map(value => (
                  <Pressable key={value} onPress={() => setRating(value)}><Text style={[x.star, value <= rating && x.starActive]}>★</Text></Pressable>
                ))}
              </View>
              <TextInput value={comment} onChangeText={setComment} multiline maxLength={500} placeholder="Yorumun (isteğe bağlı)" style={x.reviewInput} />
              <Pressable disabled={busy} onPress={submitReview} style={x.greenButton}><Text style={x.greenText}>Değerlendirmeyi gönder</Text></Pressable>
            </>
          ) : (
            <Text style={x.reviewState}>{conversation.reviewed ? 'Değerlendirmen alındı. Teşekkür ederiz.' : '24 saatlik değerlendirme süresi sona erdi.'}</Text>
          )}
        </View>
      )}


        {hasMore && <Pressable disabled={loadingOlder} onPress={() => void loadOlder()} style={x.olderButton}>{loadingOlder ? <ActivityIndicator color={C.green} /> : <Text style={x.olderText}>Daha eski mesajları yükle</Text>}</Pressable>}
        {messages.map((message, index) => {
          const day = new Date(message.createdAt).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' });
          const previousDay = index ? new Date(messages[index - 1].createdAt).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' }) : null;
          return <Fragment key={message.id}>
            {day !== previousDay && <View style={x.dateSeparator}><Text style={x.dateText}>{day}</Text></View>}
            <View style={[x.bubbleRow, message.sender === 'me' && x.bubbleRowMe]}>
              {message.sender === 'me' && message.deliveryState !== 'sending' && <Pressable onPress={() => openMessageActions(message)} style={x.messageMenuButton}><Text style={x.messageMenuButtonText}>•••</Text></Pressable>}
              <Pressable disabled={message.sender === 'system' || message.deliveryState === 'sending'} onLongPress={() => openMessageActions(message)} onPress={() => openMessageActions(message)} style={[x.bubble, message.sender === 'me' && x.bubbleMe, message.sender === 'system' && x.bubbleSystem, message.deliveryState === 'failed' && x.bubbleFailed]}>
                <Text style={[x.bubbleText, message.sender === 'me' && x.bubbleTextMe]}>{message.text}</Text>
                <Text style={[x.bubbleTime, message.sender === 'me' && x.bubbleTimeMe]}>{message.time}{message.deliveryState === 'sending' ? ' · Gönderiliyor' : message.deliveryState === 'failed' ? ' · Gönderilemedi, tekrar dene' : message.sender === 'me' && message.readAt ? ' · Okundu' : ''}</Text>
              </Pressable>
              {message.sender === 'other' && <Pressable onPress={() => openMessageActions(message)} style={x.messageMenuButton}><Text style={x.messageMenuButtonText}>•••</Text></Pressable>}
            </View>
          </Fragment>;
        })}
        {(conversation.isBlocked || conversation.status === 'rejected') && (
          <View style={x.readOnlyCard}>
            <Text style={x.readOnlyTitle}>{conversation.isBlocked ? 'İletişim engellendi' : 'Talep satıcı tarafından reddedildi'}</Text>
            <Text style={x.readOnlyText}>{conversation.isBlocked
              ? 'Mesaj geçmişi korunuyor ancak bu kullanıcıyla yeni mesaj veya ilan etkileşimi kurulamıyor.'
              : 'Bu görüşme salt okunur. Aynı ilan için yeniden alım talebi gönderilemez.'}</Text>
          </View>
        )}
        {((conversation.role === 'buyer' && ['pending', 'accepted'].includes(conversation.status)) || (conversation.role === 'seller' && conversation.status === 'accepted')) && (
          <View style={x.cancelActionCard}>
            <View style={x.cancelActionCopy}>
              <Text style={x.cancelActionTitle}>{conversation.role === 'seller' ? 'Rezervasyon işlemi' : 'Alım talebi işlemi'}</Text>
              <Text style={x.cancelActionText}>{conversation.role === 'seller'
                ? 'İptal edersen ilan yeniden aktif olur ve teslim kodu geçersizleşir.'
                : 'Talebi geri çeksen bile satıcıyla mesajlaşmaya devam edebilirsin.'}</Text>
            </View>
            <Pressable disabled={busy} onPress={cancel} style={x.cancelActionButton}>
              <Text style={x.cancelActionButtonText}>{conversation.role === 'seller' ? 'Rezervasyonu iptal et' : 'Talebi geri çek'}</Text>
            </Pressable>
          </View>
        )}

        {conversation.status === 'accepted' && (
          <View style={x.deliveryCard}>
            {conversation.role === 'buyer' ? (
              <>
                <Text style={x.deliveryEyebrow}>TESLİM KODUN</Text>
                <Text style={x.deliveryCode}>{conversation.deliveryCode}</Text>
                <Text style={x.deliveryHelp}>Teslim sırasında bu kodu satıcıya göster.</Text>
              </>
            ) : (
              <>
                <Text style={x.deliveryEyebrow}>TESLİMATI TAMAMLA</Text>
                <Text style={x.deliveryHelp}>Alıcının ekranındaki kodu teslim sırasında gir.</Text>
                <View style={x.deliveryActionRow}>
                  <TextInput value={deliveryCode} onChangeText={value => setDeliveryCode(value.replace(/\D/g, '').slice(0, 4))} keyboardType="number-pad" placeholder="4 haneli kod" placeholderTextColor="#87948C" style={x.codeInput} />
                  <Pressable disabled={busy} onPress={complete} style={x.deliveryCompleteButton}><Text style={x.greenText}>Tamamla</Text></Pressable>
                </View>
              </>
            )}
            {!!conversation.exactAddress && (
              <View style={x.addressCard}>
                <Text style={x.addressLabel}>TESLİMAT ADRESİ</Text>
                <Text style={x.address}>⌖ {conversation.exactAddress}</Text>
                <View style={x.addressActions}>
                  <Pressable onPress={() => void copyDeliveryAddress()} style={x.addressSecondaryButton}><Text style={x.addressSecondaryText}>Adresi kopyala</Text></Pressable>
                  <Pressable onPress={() => void openDeliveryAddress()} style={x.addressPrimaryButton}><Text style={x.addressPrimaryText}>Haritada aç</Text></Pressable>
                </View>
              </View>
            )}
            {!!conversation.deliveryNotes && <Text style={x.deliveryHelp}>{conversation.deliveryNotes}</Text>}
          </View>
        )}
      </ScrollView>

      <View style={[x.composer, conversationReadOnly && x.composerDisabled, { paddingBottom: keyboardVisible ? 8 : Math.max(bottomInset, 8) }]}>
        {!conversationReadOnly && <ScrollView horizontal showsHorizontalScrollIndicator={false} keyboardShouldPersistTaps="handled" contentContainerStyle={x.quickReplies}>
          {quickReplies.map(reply => <Pressable key={reply} onPress={() => setDraft(reply)} style={x.quickReply}><Text style={x.quickReplyText}>{reply}</Text></Pressable>)}
        </ScrollView>}
        <View style={x.composerRow}>
          <TextInput editable={!conversationReadOnly} value={conversationReadOnly ? '' : draft} onChangeText={setDraft} multiline maxLength={1000} placeholder={conversation.isBlocked ? 'Bu kullanıcıyla mesajlaşamazsın' : conversation.status === 'completed' ? 'Teslimat tamamlandı; sohbet kapalı' : conversation.status === 'rejected' ? 'Talep reddedildi; sohbet kapalı' : 'Mesajını yaz...'} placeholderTextColor="#87948C" style={[x.input, conversationReadOnly && x.inputDisabled]} />
          <Pressable disabled={conversationReadOnly || busy || !draft.trim()} onPress={() => void send()} style={[x.send, (conversationReadOnly || !draft.trim() || busy) && x.sendDisabled]}>
            {busy ? <ActivityIndicator color={C.white} size="small" /> : <Text style={x.sendText}>➤</Text>}
          </Pressable>
        </View>
      </View>

      <Modal transparent visible={!!actionMessage} animationType='fade' onRequestClose={() => setActionMessage(null)}>
        <Pressable onPress={() => setActionMessage(null)} style={x.reportBackdrop}>
          <Pressable style={x.reportSheet}>
            <Text style={x.reportTitle}>Mesaj seçenekleri</Text>
            <Text style={x.reportHelp}>Bu mesaj için yapmak istediğin işlemi seç.</Text>
            <Pressable onPress={() => void copyMessage()} style={x.reportOption}><Text style={x.reportOptionText}>Mesajı kopyala</Text></Pressable>
            {actionMessage?.sender === 'other' && <Pressable onPress={chooseReportMessage} style={x.reportOption}><Text style={x.reportDangerText}>Mesajı bildir</Text></Pressable>}
            <Pressable onPress={() => setActionMessage(null)} style={x.reportCancel}><Text style={x.reportCancelText}>Vazgeç</Text></Pressable>
          </Pressable>
        </Pressable>
      </Modal>

      <Modal transparent visible={!!reportingMessage} animationType='fade' onRequestClose={() => setReportingMessage(null)}>
        <Pressable onPress={() => setReportingMessage(null)} style={x.reportBackdrop}>
          <Pressable style={x.reportSheet}>
            <Text style={x.reportTitle}>Bu mesajı neden bildiriyorsun?</Text>
            <Text style={x.reportHelp}>Karşı tarafa bildirim gönderilmez.</Text>
            {[['spam', 'Spam veya reklam'], ['harassment', 'Taciz veya hakaret'], ['fraud', 'Dolandırıcılık şüphesi'], ['personal_data', 'Kişisel bilgi paylaşımı'], ['other', 'Diğer']].map(([value, label]) => <Pressable key={value} onPress={() => void reportMessage(value)} style={x.reportOption}><Text style={x.reportOptionText}>{label}</Text></Pressable>)}
            <Pressable onPress={() => setReportingMessage(null)} style={x.reportCancel}><Text style={x.reportCancelText}>Vazgeç</Text></Pressable>
          </Pressable>
        </Pressable>
      </Modal>
    </KeyboardAvoidingView>
  );
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  header: { minHeight: 66, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { width: 40, height: 40, borderRadius: 20, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center' },
  backText: { color: C.ink, fontSize: 27 },
  avatar: { width: 42, height: 42, borderRadius: 21, backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center', marginHorizontal: 10 },
  avatarText: { color: C.dark, fontSize: 16, fontWeight: '900' },
  profileLink: { flex: 1, flexDirection: 'row', alignItems: 'center', minWidth: 0 },
  headerCopy: { flex: 1 },
  hideButton: { minHeight: 34, borderRadius: 11, backgroundColor: '#F1F3F1', justifyContent: 'center', paddingHorizontal: 8, marginRight: 6 },
  hideButtonText: { color: C.muted, fontSize: 11, fontWeight: '900' },
  blockButton: { minHeight: 34, borderRadius: 11, borderWidth: 1, borderColor: '#D6A79E', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 9 },
  blockButtonText: { color: '#913F32', fontSize: 11, fontWeight: '900' },
  name: { color: C.ink, fontSize: 16, fontWeight: '900' },
  status: { color: C.green, fontSize: 12, marginTop: 2 },
  listing: { margin: 10, marginBottom: 4, padding: 11, borderRadius: 15, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, flexDirection: 'row', alignItems: 'center' },
  listingTitle: { color: C.ink, fontSize: 12, fontWeight: '900' },
  listingSub: { color: C.muted, fontSize: 12, marginTop: 3 },
  price: { color: C.green, fontSize: 12, fontWeight: '900' },
  actionCard: { marginBottom: 12, padding: 13, borderRadius: 16, backgroundColor: '#FFF4D9', borderWidth: 1, borderColor: '#EFD89D' },
  actionTitle: { color: C.ink, fontSize: 12, fontWeight: '900', marginBottom: 10 },
  actionRow: { flexDirection: 'row', gap: 8 },
  outlineButton: { flex: 1, height: 43, borderRadius: 13, borderWidth: 1, borderColor: '#A54A3A', alignItems: 'center', justifyContent: 'center' },
  outlineText: { color: '#A54A3A', fontSize: 12, fontWeight: '900' },
  greenButton: { flex: 1, minHeight: 43, borderRadius: 13, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 12 },
  greenText: { color: C.white, fontSize: 12, fontWeight: '900' },
  deliveryCard: { marginTop: 10, padding: 14, borderRadius: 17, backgroundColor: C.soft, borderWidth: 1, borderColor: '#B9D7C1' },
  deliveryEyebrow: { color: C.green, fontSize: 11, fontWeight: '900', letterSpacing: 1.2 },
  deliveryCode: { color: C.dark, fontSize: 32, fontWeight: '900', letterSpacing: 8, marginVertical: 5 },
  deliveryHelp: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 5 },
  addressCard: { marginTop: 12, paddingTop: 12, borderTopWidth: 1, borderTopColor: '#C7DDCD' },
  addressLabel: { color: C.green, fontSize: 10, fontWeight: '900', letterSpacing: 1 },
  address: { color: C.ink, fontSize: 12, lineHeight: 18, fontWeight: '800', marginTop: 6 },
  addressActions: { flexDirection: 'row', gap: 8, marginTop: 10 },
  addressSecondaryButton: { flex: 1, minHeight: 40, borderRadius: 12, borderWidth: 1, borderColor: '#9DC5A8', alignItems: 'center', justifyContent: 'center', backgroundColor: C.white },
  addressSecondaryText: { color: C.green, fontSize: 12, fontWeight: '900' },
  addressPrimaryButton: { flex: 1, minHeight: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: C.green },
  addressPrimaryText: { color: C.white, fontSize: 12, fontWeight: '900' },
  deliveryActionRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 9 },
  codeInput: { flex: 1, height: 46, borderRadius: 13, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, paddingHorizontal: 12, fontSize: 18, letterSpacing: 3, textAlign: 'center' },
  deliveryCompleteButton: { height: 46, borderRadius: 13, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 18 },
  reviewCard: { marginBottom: 12, padding: 14, borderRadius: 17, backgroundColor: C.white, borderWidth: 1, borderColor: C.line },
  completedTitle: { color: C.green, fontSize: 14, fontWeight: '900', marginBottom: 6 },
  completedText: { color: C.muted, fontSize: 12, lineHeight: 18, marginBottom: 10 },
  reviewDeadline: { color: C.dark, fontSize: 12, fontWeight: '800', marginBottom: 12 },
  reviewState: { color: C.green, fontSize: 12, lineHeight: 18, fontWeight: '800', paddingVertical: 6 },
  stars: { flexDirection: 'row', gap: 8, marginBottom: 10 },
  star: { color: '#D5DAD6', fontSize: 30 },
  starActive: { color: '#F3A83B' },
  reviewInput: { minHeight: 58, borderRadius: 13, backgroundColor: C.bg, borderWidth: 1, borderColor: C.line, padding: 10, textAlignVertical: 'top', marginBottom: 9 },
  messages: { flex: 1 },
  messageContent: { padding: 12, paddingBottom: 18, flexGrow: 1, justifyContent: 'flex-end' },
  bubbleRow: { marginBottom: 9, flexDirection: 'row', alignItems: 'center', gap: 5 },
  bubbleRowMe: { justifyContent: 'flex-end' },
  messageMenuButton: { width: 31, height: 31, borderRadius: 16, alignItems: 'center', justifyContent: 'center', backgroundColor: '#E7ECE8', borderWidth: 1, borderColor: C.line },
  messageMenuButtonText: { color: C.muted, fontSize: 12, fontWeight: '900', letterSpacing: -1 },
  bubble: { maxWidth: '79%', borderRadius: 17, paddingHorizontal: 12, paddingVertical: 9, backgroundColor: C.white, borderWidth: 1, borderColor: C.line },
  bubbleMe: { backgroundColor: C.green, borderColor: C.green },
  bubbleSystem: { maxWidth: '100%', width: '100%', backgroundColor: C.soft, borderColor: '#C7DDCD' },
  bubbleText: { color: C.ink, fontSize: 14, lineHeight: 20 },
  bubbleTextMe: { color: C.white },
  bubbleTime: { color: C.muted, fontSize: 11, textAlign: 'right', marginTop: 5 },
  bubbleTimeMe: { color: '#C7DDD0' },
  bubbleFailed: { backgroundColor: '#A54A3A', borderColor: '#A54A3A' },
  dateSeparator: { alignItems: 'center', marginVertical: 12 },
  dateText: { color: C.muted, fontSize: 12, fontWeight: '800', backgroundColor: '#E7ECE8', borderRadius: 12, paddingHorizontal: 10, paddingVertical: 5 },
  olderButton: { alignSelf: 'center', minHeight: 36, justifyContent: 'center', paddingHorizontal: 14, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, marginBottom: 12 },
  olderText: { color: C.green, fontSize: 12, fontWeight: '900' },
  composer: { paddingHorizontal: 9, paddingTop: 7, backgroundColor: C.white, borderTopWidth: 1, borderTopColor: C.line },
  composerDisabled: { backgroundColor: '#F4F5F4' },
  inputDisabled: { backgroundColor: '#E8EAE8', color: C.muted },
  sendDisabled: { backgroundColor: '#AAB2AC', opacity: .75 },
  quickReplies: { gap: 7, paddingBottom: 7 },
  quickReply: { minHeight: 32, borderRadius: 16, borderWidth: 1, borderColor: '#BFD8C5', backgroundColor: C.soft, justifyContent: 'center', paddingHorizontal: 11 },
  quickReplyText: { color: C.dark, fontSize: 12, fontWeight: '800' },
  composerRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 7 },
  input: { flex: 1, minHeight: 46, maxHeight: 100, borderRadius: 16, backgroundColor: C.bg, borderWidth: 1, borderColor: C.line, paddingHorizontal: 13, paddingVertical: 11, color: C.ink, fontSize: 14, lineHeight: 19 },
  send: { width: 44, height: 44, borderRadius: 22, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center' },
  sendText: { color: C.white, fontSize: 18 },
  readOnlyCard: { marginTop: 5, padding: 13, borderRadius: 16, backgroundColor: '#F2F3F2', borderWidth: 1, borderColor: '#D5DAD6' },
  readOnlyTitle: { color: C.ink, fontSize: 12, fontWeight: '900' },
  readOnlyText: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 4 },
  cancelActionCard: { marginTop: 5, padding: 13, borderRadius: 16, backgroundColor: '#FFF4F1', borderWidth: 1, borderColor: '#E8C1B9' },
  cancelActionCopy: { marginBottom: 10 },
  cancelActionTitle: { color: '#873C30', fontSize: 12, fontWeight: '900' },
  cancelActionText: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 4 },
  cancelActionButton: { minHeight: 42, borderRadius: 13, borderWidth: 1, borderColor: '#A54A3A', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 12 },
  cancelActionButtonText: { color: '#A54A3A', fontSize: 12, fontWeight: '900' },
  reportBackdrop: { flex: 1, backgroundColor: 'rgba(11,25,17,.48)', justifyContent: 'flex-end', padding: 14 },
  reportSheet: { borderRadius: 22, backgroundColor: C.white, padding: 18 },
  reportTitle: { color: C.ink, fontSize: 17, fontWeight: '900' },
  reportHelp: { color: C.muted, fontSize: 12, marginTop: 4, marginBottom: 12 },
  reportOption: { minHeight: 45, justifyContent: 'center', borderBottomWidth: 1, borderBottomColor: C.line },
  reportOptionText: { color: C.ink, fontSize: 13, fontWeight: '800' },
  reportDangerText: { color: '#A23D32', fontSize: 13, fontWeight: '900' },
  reportCancel: { minHeight: 45, justifyContent: 'center', alignItems: 'center', marginTop: 8 },
  reportCancelText: { color: '#A23D32', fontSize: 12, fontWeight: '900' },
});
