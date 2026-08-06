import { StatusBar } from 'expo-status-bar';
import * as Location from 'expo-location';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  AppState,
  FlatList,
  BackHandler,
  KeyboardAvoidingView,
  Linking,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
  useWindowDimensions,
} from 'react-native';
import { SafeAreaProvider, SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Circle, Path } from 'react-native-svg';
import { C, s } from './styles';
import { ls } from './locationStyles';
import { ds } from './detailStyles';
import { ApiError, apiRequest } from './src/lib/api';
import AddressBookModal from './src/address/AddressBookModal';
import { DeliveryAddress } from './src/address/types';
import { useNotice } from './src/notice/NoticeProvider';
import ConversationList from './src/chat/ConversationList';
import ConversationScreen from './src/chat/ConversationScreen';
import { subscribeToConversations } from './src/chat/realtime';
import ProfileMenuScreen from './src/profile/ProfileMenuScreen';
import AccountDeletionScreen from './src/profile/AccountDeletionScreen';
import UserAvatar from './src/profile/UserAvatar';
import LegalDocumentScreen from './src/legal/LegalDocumentScreen';
import ProfileInfoModal from './src/profile/ProfileInfoModal';
import BlockedUsersScreen from './src/profile/BlockedUsersScreen';
import NotificationPreferencesScreen from './src/notifications/NotificationPreferencesScreen';
import PublicUserProfileScreen from './src/profile/PublicUserProfileScreen';
import FavoritesScreen from './src/favorites/FavoritesScreen';
import MyListingsScreen from './src/listings/MyListingsScreen';
import PurchaseHistoryScreen from './src/transactions/PurchaseHistoryScreen';
import ListingCard, { MaterialIcon } from './src/listings/ListingCard';
import MonetizedAdSlot from './src/advertising/MonetizedAdSlot';
import RewardedListingBoostButton from './src/advertising/RewardedListingBoostButton';
import { usePickupInterstitial } from './src/advertising/usePickupInterstitial';
import { useAdvertisements } from './src/advertising/useAdvertisements';
import NotificationCenter from './src/notifications/NotificationCenter';
import { AppNotification } from './src/notifications/types';
import { observeNotificationResponses } from './src/push/notificationObserver';
import LeaderboardScreen from './src/ranking/LeaderboardScreen';
import SupportersScreen, { SupporterRegion, SupportersDock } from './src/supporters/SupportersScreen';
import { Conversation, ConversationCollectionResponse, ConversationResponse } from './src/chat/types';
import {
  Coordinates,
  EMPTY_CENTER,
  distanceKm,
  distanceLabel,
  Listing,
  listingCount,
  listingPrice,
  MATERIALS,
  Material,
  money,
  RADII,
} from './marketplace';

type Tab = 'home' | 'ranking' | 'messages' | 'profile';
type Route = Tab | 'supporters' | 'detail' | 'chat' | 'favorites' | 'my-listings' | 'purchase-history' | 'notifications' | 'public-profile' | 'addresses' | 'profile-edit' | 'notification-preferences' | 'blocked-users' | 'legal-terms' | 'legal-privacy' | 'account-deletion';
type FormLine = { enabled: boolean; count: string; price: string };
type NewListing = Pick<Listing, 'items' | 'note'> & { conditionConfirmed: boolean };
type AddressCollectionResponse = { data: Omit<DeliveryAddress, 'saved'>[] };
type ListingCategory = 'Tümü' | Material;
type ListingSort = 'distance' | 'newest' | 'quantity_desc' | 'price_asc' | 'gain_desc';
type ListingLoadMode = 'replace' | 'refresh' | 'more';
type ListingCollectionResponse = {
  data: Listing[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};
type ListingResponse = { data: Listing };
type FeedItem =
  | { kind: 'listing'; listing: Listing }
  | { kind: 'ad-slot'; slotIndex: number };

const initialForm: Record<Material, FormLine> = {
  PET: { enabled: false, count: '', price: '' },
  Cam: { enabled: false, count: '', price: '' },
  Alüminyum: { enabled: false, count: '', price: '' },
};
const quickMessages = [
  'İlan hâlâ geçerli mi?',
  'Bugün gelip alabilirim.',
  'Hangi saat uygundur?',
  'Hepsini almak istiyorum.',
];
const MATERIAL_API_TYPES: Record<Material, string> = {
  PET: 'pet',
  Cam: 'glass',
  Alüminyum: 'aluminum',
};
const SORT_OPTIONS: { value: ListingSort; label: string }[] = [
  { value: 'distance', label: 'En yakın' },
  { value: 'newest', label: 'En yeni' },
  { value: 'quantity_desc', label: 'En yüksek adet' },
  { value: 'price_asc', label: 'En düşük fiyat' },
  { value: 'gain_desc', label: 'En yüksek fark' },
];

function Home({
  listings,
  center,
  radius,
  locationLabel,
  locationReady,
  category,
  setCategory,
  sort,
  setSort,
  openLocation,
  openListing,
  loading,
  refreshing,
  loadingMore,
  total,
  hasMore,
  error,
  reload,
  refresh,
  loadMore,
  currentUserId,
  favoritePendingIds,
  toggleFavorite,
  unreadNotificationCount,
  openNotifications,
  token,
}: {
  listings: Listing[];
  center: Coordinates | null;
  radius: number;
  locationLabel: string;
  locationReady: boolean;
  category: ListingCategory;
  setCategory: (value: ListingCategory) => void;
  sort: ListingSort;
  setSort: (value: ListingSort) => void;
  openLocation: () => void;
  openListing: (id: number) => void;
  loading: boolean;
  refreshing: boolean;
  loadingMore: boolean;
  total: number;
  hasMore: boolean;
  error: string;
  reload: () => void;
  refresh: () => void;
  loadMore: () => void;
  currentUserId: string;
  favoritePendingIds: Set<number>;
  toggleFavorite: (listing: Listing) => Promise<boolean>;
  unreadNotificationCount: number;
  openNotifications: () => void;
  token?: string | null;
}) {
  const [sortOpen, setSortOpen] = useState(false);
  const advertisementCollection = useAdvertisements('home_feed', token);
  const advertisementPolicy = advertisementCollection?.meta;
  const activeSortLabel = SORT_OPTIONS.find(option => option.value === sort)?.label || 'En yakın';
  const feedData = useMemo<FeedItem[]>(() => {
    const result: FeedItem[] = [];
    let slotCount = 0;
    const firstAfter = advertisementPolicy?.firstAfter || 3;
    const repeatEvery = advertisementPolicy?.repeatEvery || 8;
    const minItems = advertisementPolicy?.minItems || 3;
    listings.forEach((listing, index) => {
      result.push({ kind: 'listing', listing });
      const itemNumber = index + 1;
      const shouldInsert = listings.length >= minItems
        && itemNumber >= firstAfter
        && (itemNumber === firstAfter || (repeatEvery > 0 && (itemNumber - firstAfter) % repeatEvery === 0));
      if (shouldInsert) result.push({ kind: 'ad-slot', slotIndex: ++slotCount });
    });
    return result;
  }, [advertisementPolicy, listings]);

  const header = (
    <View>
      <View style={s.header}>
        <View>
          <Text style={s.eyebrow}>KEŞFET</Text>
          <Text style={s.logo}>döngü<Text style={s.logoDot}>.</Text></Text>
        </View>
        <Pressable accessibilityRole="button" accessibilityLabel={`Bildirimler${unreadNotificationCount ? `, ${unreadNotificationCount} okunmamış` : ''}`} onPress={openNotifications} style={s.notification}>
          <Text style={s.notificationIcon}>♢</Text>
          {unreadNotificationCount > 0 && <View style={s.notificationBadge}><Text style={s.notificationBadgeText}>{unreadNotificationCount > 99 ? '99+' : unreadNotificationCount}</Text></View>}
        </Pressable>
      </View>
      <View style={s.hero}>
        <View style={s.heroCopy}>
          <Text style={s.heroKicker}>BOŞ DURMASIN</Text>
          <Text style={s.heroTitle}>Ambalajını{`\n`}değere dönüştür.</Text>
          <Text style={s.heroText}>Yakınındaki ilanları keşfet,{`\n`}topla ve değerlendir.</Text>
        </View>
        <View style={s.heroArt}>
          <View style={s.bottle}><Text style={s.artSymbol}>♻</Text></View>
          <View style={s.can}><Text style={s.artSymbol}>↻</Text></View>
          <View style={s.coinOne}><Text style={s.coinText}>₺</Text></View>
          <View style={s.coinTwo}><Text style={s.coinText}>₺</Text></View>
        </View>
      </View>
      <Pressable accessibilityRole="button" accessibilityLabel={`Konum seçimi. ${locationLabel}. ${radius} kilometre çevresi`} accessibilityHint="Yakındaki ilanlar için konum ve mesafe seçimini açar" onPress={openLocation} style={({ pressed }) => [s.locationSelector, pressed && s.pressed]}>
        <View style={ls.selectorCopy}>
          <View style={ls.selectorTitleRow}>
            <Text style={s.selectorLabel}>Yakınımdaki ilanlar</Text>
            <View style={[ls.modeBadge, locationReady && ls.modeBadgeCurrent]}>
              <Text style={[ls.modeBadgeText, locationReady && ls.modeBadgeTextCurrent]}>
                {locationReady ? 'CANLI KONUM' : 'KONUM GEREKLİ'}
              </Text>
            </View>
          </View>
          <Text style={s.selectorValue} numberOfLines={1}>{locationLabel}</Text>
          <Text style={ls.radiusText}>{radius} km çevresi · değiştirmek için dokun</Text>
        </View>
        <View style={s.locationCircle}><Text style={s.locationCircleText}>⌖</Text></View>
      </Pressable>
      <View style={s.sectionHeading}>
        <View>
          <Text style={s.sectionTitle}>Yakınındaki ilanlar</Text>
          <Text style={s.sectionSub}>{total} ilan · {radius} km içinde · {activeSortLabel.toLocaleLowerCase('tr-TR')}</Text>
        </View>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="İlan sıralamasını değiştir"
          onPress={() => setSortOpen(current => !current)}
          style={({ pressed }) => [s.filterButton, pressed && s.pressed]}
        >
          <Text style={s.filterText}>Sırala  {sortOpen ? '⌃' : '⌄'}</Text>
        </Pressable>
      </View>
      <FlatList
        horizontal
        data={['Tümü' as ListingCategory, ...MATERIALS]}
        keyExtractor={item => item}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={s.chipRow}
        renderItem={({ item }) => (
          <Pressable accessibilityRole="button" accessibilityState={{ selected: category === item }} accessibilityLabel={`${item} ilanlarını göster`} onPress={() => setCategory(item)} style={[s.chip, category === item && s.chipActive]}>
            <Text style={[s.chipText, category === item && s.chipTextActive]}>{item}</Text>
          </Pressable>
        )}
      />
      {sortOpen && (
        <View style={s.sortPanel}>
          <Text style={s.sortPanelTitle}>İLANLARI SIRALA</Text>
          <View style={s.sortOptions}>
            {SORT_OPTIONS.map(option => (
              <Pressable
                key={option.value}
                onPress={() => {
                  setSort(option.value);
                  setSortOpen(false);
                }}
                style={[s.sortOption, sort === option.value && s.sortOptionActive]}
              >
                <Text style={[s.sortOptionText, sort === option.value && s.sortOptionTextActive]}>{option.label}</Text>
              </Pressable>
            ))}
          </View>
        </View>
      )}
    </View>
  );

  const empty = loading ? (
    <View style={ls.emptyState}>
      <ActivityIndicator color={C.green} />
      <Text style={ls.emptyTitle}>İlanlar yükleniyor</Text>
    </View>
  ) : error ? (
    <View style={ls.emptyState}>
      <Text style={ls.emptyTitle}>İlanlar alınamadı</Text>
      <Text style={ls.emptyText}>{error}</Text>
      <Pressable onPress={reload} style={ls.emptyButton}>
        <Text style={ls.emptyButtonText}>Tekrar dene</Text>
      </Pressable>
    </View>
  ) : (
    <View style={ls.emptyState}>
      <View style={ls.emptyIcon}><Text style={ls.emptyIconText}>⌖</Text></View>
      <Text style={ls.emptyTitle}>
        {locationReady
          ? category === 'Tümü' ? 'Bu mesafede henüz ilan yok' : `${category} için ilan bulunamadı`
          : 'Yakındaki ilanları görmek için konumunu kullan'}
      </Text>
      <Text style={ls.emptyText}>
        {locationReady
          ? category === 'Tümü' ? 'İlk gerçek ilan yayınlandığında burada görünecek.' : 'Malzeme filtresini değiştirebilir veya arama mesafesini artırabilirsin.'
          : 'Konumun yalnızca seçtiğin kilometre içindeki ilanları bulmak için kullanılır.'}
      </Text>
      <Pressable onPress={locationReady && category !== 'Tümü' ? () => setCategory('Tümü') : openLocation} style={ls.emptyButton}>
        <Text style={ls.emptyButtonText}>
          {locationReady ? category === 'Tümü' ? 'Arama mesafesini değiştir' : 'Tüm malzemeleri göster' : 'Konumumu kullan'}
        </Text>
      </Pressable>
    </View>
  );

  return (
    <FlatList
      data={feedData}
      keyExtractor={item => item.kind === 'listing' ? `listing-${item.listing.id}` : `ad-slot-${item.slotIndex}`}
      renderItem={({ item }) => item.kind === 'listing' ? (
        <ListingCard
          item={item.listing}
          center={center}
          isOwn={String(item.listing.sellerId) === currentUserId}
          open={() => openListing(item.listing.id)}
          favoritePending={favoritePendingIds.has(item.listing.id)}
          toggleFavorite={() => void toggleFavorite(item.listing)}
        />
      ) : (
        <MonetizedAdSlot placement="home_feed" slotIndex={item.slotIndex} token={token} />
      )}
      ListHeaderComponent={header}
      ListEmptyComponent={empty}
      ListFooterComponent={loadingMore ? <View style={s.listFooter}><ActivityIndicator color={C.green} /><Text style={s.listFooterText}>Daha fazla ilan yükleniyor</Text></View> : null}
      contentContainerStyle={[s.pageBottom, { paddingBottom: 124 }]}
      showsVerticalScrollIndicator={false}
      refreshing={refreshing}
      onRefresh={locationReady ? refresh : undefined}
      onEndReached={hasMore && !loading && !refreshing && !loadingMore ? loadMore : undefined}
      onEndReachedThreshold={0.35}
      initialNumToRender={6}
      maxToRenderPerBatch={6}
      updateCellsBatchingPeriod={50}
      windowSize={7}
      removeClippedSubviews={Platform.OS === 'android'}
    />
  );
}
function ListingDetail({
  listing,
  center,
  back,
  messageSeller,
  requestPickup,
  bottomInset,
  isOwn,
  token,
  requireAuth,
  openSellerProfile,
}: {
  listing: Listing;
  center: Coordinates | null;
  back: () => void;
  messageSeller: () => void;
  requestPickup: () => void;
  bottomInset: number;
  isOwn: boolean;
  token?: string | null;
  requireAuth?: () => void;
  openSellerProfile: () => void;
}) {
  const { showNotice } = useNotice();
  const [reportOpen, setReportOpen] = useState(false);
  const [reportDetails, setReportDetails] = useState('');
  const [reportReason, setReportReason] = useState<string | null>(null);
  const [reporting, setReporting] = useState(false);
  const count = listingCount(listing);
  const total = listingPrice(listing);
  const requestActive = listing.requestStatus === 'pending' || listing.requestStatus === 'reserved' || listing.requestStatus === 'rejected';
  const requestButtonLabel = listing.requestStatus === 'pending'
    ? 'Talep gönderildi'
    : listing.requestStatus === 'reserved'
      ? 'Senin için rezerve edildi'
      : listing.requestStatus === 'rejected'
        ? 'Satıcı talebi reddetti'
        : 'Almak istiyorum';
  const requestStatusLabel = listing.requestStatus === 'pending'
    ? 'TALEP GÖNDERİLDİ'
    : listing.requestStatus === 'reserved'
      ? 'REZERVE EDİLDİ'
      : listing.requestStatus === 'cancelled'
        ? 'TALEP GERİ ÇEKİLDİ'
        : listing.requestStatus === 'rejected'
          ? 'TALEP REDDEDİLDİ'
          : 'AKTİF İLAN';
  const closeReport = () => {
    if (reporting) return;
    setReportOpen(false);
    setReportReason(null);
    setReportDetails('');
  };
  const reportListing = async () => {
    if (!token) { requireAuth?.(); return; }
    if (reporting || !reportReason) return;
    setReporting(true);
    try {
      const response = await apiRequest<{ message: string }>(`/listings/${listing.id}/report`, { method: 'POST', token, body: { reason: reportReason, details: reportDetails.trim() || null } });
      setReportOpen(false);
      setReportReason(null);
      setReportDetails('');
      showNotice({ tone: 'success', title: 'Bildirimin alındı', message: response.message });
    } catch (error) {
      showNotice({ tone: 'error', title: 'İlan bildirilemedi', message: error instanceof ApiError ? error.message : 'Bildirim servisine ulaşılamadı.' });
    } finally { setReporting(false); }
  };
  return (
    <View style={ds.screen}>
      <View style={ds.topBar}>
        <Pressable onPress={back} style={ds.backButton}><Text style={ds.backText}>‹</Text></Pressable>
        <Text style={ds.topTitle}>İlan detayı</Text>
        <View style={ds.topSpacer} />
      </View>
      <ScrollView contentContainerStyle={ds.detailContent} showsVerticalScrollIndicator={false}>
        <View style={ds.visual}>
          <View style={ds.visualCircleOne} /><View style={ds.visualCircleTwo} />
          <Text style={ds.visualBottle}>♻</Text>
          <View style={ds.statusPill}><Text style={ds.statusPillText}>{requestStatusLabel}</Text></View>
          <Text style={ds.visualTitle}>{count} ambalaj</Text>
          <Text style={ds.visualSub}>{listing.items.map(item => item.material).join(' · ')} · {listing.time}</Text>
        </View>
        <View style={ds.section}>
          <Text style={ds.sectionLabel}>AMBALAJLAR VE FİYAT</Text>
          {listing.items.map(item => (
            <View key={item.material} style={ds.itemRow}>
              <MaterialIcon material={item.material} />
              <View style={ds.itemCopy}>
                <Text style={ds.itemName}>{item.count} adet {item.material}</Text>
                <Text style={ds.itemUnit}>{item.unitPrice.toFixed(2).replace('.', ',')} TL / adet</Text>
              </View>
              <Text style={ds.itemTotal}>{money(item.count * item.unitPrice)}</Text>
            </View>
          ))}
          <View style={ds.totals}>
            <View style={ds.totalBox}>
              <Text style={ds.totalLabel}>TOPLAM SATIŞ</Text>
              <Text style={ds.totalValue}>{money(total)}</Text>
            </View>
            <View style={[ds.totalBox, ds.gainTotalBox]}>
              <Text style={ds.totalLabel}>POTANSİYEL FARK</Text>
              <Text style={[ds.totalValue, ds.gainTotal]}>+{money(count - total)}</Text>
            </View>
          </View>
        </View>
        <View style={ds.section}>
          <Text style={ds.sectionLabel}>TESLİMAT BİLGİLERİ</Text>
          <View style={ds.infoRow}>
            <View style={ds.infoIcon}><Text style={ds.infoIconText}>⌖</Text></View>
            <View style={ds.infoCopy}>
              <Text style={ds.infoLabel}>Yaklaşık konum ve uzaklık</Text>
              <Text style={ds.infoValue}>
                {listing.district}{center ? ' · ' + distanceLabel(listing.distanceKm ?? distanceKm(center, listing)) : ''}
              </Text>
            </View>
          </View>
          <Text style={ds.safetyText}>Tam adres güvenlik nedeniyle gizlidir. Satıcı talebini kabul ettikten sonra sohbetten paylaşabilir.</Text>
        </View>
        <View style={ds.section}>
          <Text style={ds.sectionLabel}>SATICI</Text>
          <Pressable accessibilityRole="button" accessibilityLabel={`${listing.seller} profilini aç`} onPress={openSellerProfile} style={ds.seller}>
            <UserAvatar uri={listing.sellerAvatarUrl} name={listing.seller} size={51} style={{ marginRight: 12 }} />
            <View style={ds.sellerCopy}>
              <Text style={ds.sellerName}>{listing.seller}</Text>
              <Text style={ds.sellerMeta}>{listing.sellerTransactions} tamamlanan işlem</Text>
            </View>
            <Text style={ds.sellerRating}>{listing.ratingCount > 0 && listing.rating !== null ? '★ ' + listing.rating.toFixed(1) + ' · ' + listing.ratingCount : 'Henüz değerlendirilmedi'}</Text>
          </Pressable>
        </View>
        <View style={ds.section}>
          <Text style={ds.sectionLabel}>İLAN AÇIKLAMASI</Text>
          <Text style={ds.noteText}>{listing.note}</Text>
        </View>
        <MonetizedAdSlot placement="listing_detail" token={token} style={{ marginBottom: 14 }} />
        {!isOwn && <Pressable accessibilityRole="button" onPress={() => token ? setReportOpen(true) : requireAuth?.()} style={ds.reportButton}><Text style={ds.reportText}>İlanı bildir</Text><Text style={ds.reportHelp}>Yanıltıcı, uygunsuz veya şüpheli ilanları güvenlik ekibine gönder</Text></Pressable>}
      </ScrollView>
      <View style={[ds.detailActions, { paddingBottom: Math.max(bottomInset, 12) }]}>
        {isOwn ? (
          <View style={[ds.secondaryAction, { flex: 1 }]}><Text style={ds.secondaryActionText}>Bu ilan sana ait · Talepleri Mesajlar bölümünden yönetebilirsin</Text></View>
        ) : (
          <>
            <Pressable onPress={messageSeller} style={ds.secondaryAction}>
              <Text style={ds.secondaryActionText}>Satıcıya yaz</Text>
            </Pressable>
            <Pressable onPress={requestPickup} disabled={requestActive} style={[ds.primaryAction, requestActive && ds.requestSent]}>
              <Text style={ds.primaryActionText}>{requestButtonLabel}</Text>
            </Pressable>
          </>
        )}
      </View>
      <Modal transparent visible={reportOpen} animationType="fade" onRequestClose={closeReport}>
        <SafeAreaProvider style={ds.reportProvider}>
          <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={ds.reportBackdrop}>
            <Pressable style={ds.reportBackdropPress} onPress={closeReport} />
            <SafeAreaView edges={['bottom']} style={ds.reportSheet}>
            <Text style={ds.reportTitle}>Bu ilanı neden bildiriyorsun?</Text>
            <Text style={ds.reportSubtitle}>Seçimin ve açıklaman yalnızca güvenlik ekibi tarafından görülür.</Text>
            <ScrollView style={ds.reportScroll} contentContainerStyle={ds.reportScrollContent} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
              <View style={ds.reportOptions}>
                {[
                  ['misleading', 'Yanıltıcı bilgi'], ['prohibited', 'Uygunsuz veya yasak içerik'],
                  ['spam', 'Spam veya reklam'], ['duplicate', 'Tekrarlanan ilan'],
                  ['wrong_location', 'Yanlış konum'], ['other', 'Diğer'],
                ].map(([value, label]) => {
                  const selected = reportReason === value;
                  return <Pressable key={value} disabled={reporting} onPress={() => setReportReason(value)} style={[ds.reportOption, selected && ds.reportOptionSelected]}>
                    <View style={[ds.reportRadio, selected && ds.reportRadioSelected]}>{selected ? <View style={ds.reportRadioDot} /> : null}</View>
                    <Text style={[ds.reportOptionText, selected && ds.reportOptionTextSelected]}>{label}</Text>
                  </Pressable>;
                })}
              </View>
              <TextInput value={reportDetails} onChangeText={setReportDetails} maxLength={500} multiline placeholder="Eklemek istediğin açıklama (isteğe bağlı)" placeholderTextColor="#87948C" style={ds.reportInput} />
            </ScrollView>
            <View style={ds.reportActions}>
              <Pressable disabled={reporting} onPress={closeReport} style={ds.reportCancel}><Text style={ds.reportCancelText}>Vazgeç</Text></Pressable>
              <Pressable disabled={reporting || !reportReason} onPress={() => void reportListing()} style={[ds.reportSubmit, (reporting || !reportReason) && ds.reportSubmitDisabled]}>
                {reporting ? <ActivityIndicator size="small" color="white" /> : <Text style={ds.reportSubmitText}>Bildirimi gönder</Text>}
              </Pressable>
            </View>
            </SafeAreaView>
          </KeyboardAvoidingView>
        </SafeAreaProvider>
      </Modal>
    </View>
  );
}

function LocationPicker({
  visible,
  close,
  radius,
  setRadius,
  locationLabel,
  locationReady,
  permissionDenied,
  useCurrentLocation,
  requesting,
  resultCount,
  bottomInset,
}: {
  visible: boolean;
  close: () => void;
  radius: number;
  setRadius: (value: number) => void;
  locationLabel: string;
  locationReady: boolean;
  permissionDenied: boolean;
  useCurrentLocation: () => void;
  requesting: boolean;
  resultCount: number;
  bottomInset: number;
}) {
  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={close}>
      <View style={s.modalBackdrop}>
        <View style={[s.modalSheet, ls.locationSheet, { paddingBottom: Math.max(bottomInset, 14) + 10 }]}>
          <View style={s.handle} />
          <View style={s.modalHeader}>
            <View><Text style={s.screenEyebrow}>ARAMA ALANI</Text><Text style={s.modalTitle}>Konum ve mesafe</Text></View>
            <Pressable onPress={close} style={s.close}><Text style={s.closeText}>×</Text></Pressable>
          </View>
          <ScrollView showsVerticalScrollIndicator={false}>
            <View style={ls.currentSummary}>
              <View style={ls.summaryIcon}><Text style={ls.summaryIconText}>⌖</Text></View>
              <View style={ls.summaryCopy}>
                <Text style={ls.summaryLabel}>{locationReady ? 'Mevcut konum kullanılıyor' : 'Konum henüz alınmadı'}</Text>
                <Text style={ls.summaryValue}>{locationLabel}</Text>
              </View>
              {locationReady && <View style={ls.liveDot} />}
            </View>
            <Pressable onPress={useCurrentLocation} disabled={requesting} style={[ls.currentButton, requesting && ls.disabled]}>
              {requesting ? <ActivityIndicator color={C.white} /> : <Text style={ls.currentButtonIcon}>⌖</Text>}
              <View style={ls.currentButtonCopy}>
                <Text style={ls.currentButtonTitle}>{requesting ? 'Konumun alınıyor...' : locationReady ? 'Konumumu yenile' : 'Mevcut konumumu kullan'}</Text>
                <Text style={ls.currentButtonText}>Yalnızca uygulamayı kullanırken erişilir</Text>
              </View>
            </Pressable>
            {permissionDenied && (
              <View style={ls.permissionWarning}>
                <Text style={ls.permissionWarningTitle}>Konum izni kapalı</Text>
                <Text style={ls.permissionWarningText}>Yakındaki ilanlar için telefon ayarlarından konum iznini açmalısın.</Text>
                <Pressable onPress={() => Linking.openSettings()} style={ls.settingsButton}><Text style={ls.settingsButtonText}>Telefon ayarlarını aç</Text></Pressable>
              </View>
            )}
            <Text style={ls.groupLabel}>ARAMA MESAFESİ</Text>
            <View style={ls.radiusGrid}>
              {RADII.map(value => (
                <Pressable key={value} onPress={() => setRadius(value)} style={[ls.radiusOption, radius === value && ls.radiusOptionActive]}>
                  <Text style={[ls.radiusOptionValue, radius === value && ls.radiusOptionValueActive]}>{value}</Text>
                  <Text style={[ls.radiusOptionUnit, radius === value && ls.radiusOptionUnitActive]}>km</Text>
                </Pressable>
              ))}
            </View>
            <View style={ls.resultBanner}>
              <Text style={ls.resultNumber}>{resultCount}</Text>
              <Text style={ls.resultText}>aktif ilan {radius} km arama alanında gösterilecek</Text>
            </View>
            <Text style={ls.privacyNote}>Konumun yalnızca yakındaki ilanları hesaplamak için kullanılır. Arka planda takip yapılmaz.</Text>
            <Pressable onPress={close} style={s.primary}><Text style={s.primaryText}>Sonuçları göster</Text></Pressable>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

function ListingPublishedModal({
  listing,
  close,
  openListing,
  token,
  userId,
  onBoosted,
}: {
  listing: Listing;
  close: () => void;
  openListing: () => void;
  token: string;
  userId: string;
  onBoosted: (listing: Listing) => void;
}) {
  return (
    <Modal visible transparent animationType="fade" onRequestClose={close}>
      <View style={s.successBackdrop}>
        <View style={s.successCard}>
          <View style={s.successGlow} />
          <View style={s.successIcon}><Text style={s.successIconText}>✓</Text></View>
          <Text style={s.successEyebrow}>İLANIN YAYINDA</Text>
          <Text style={s.successTitle}>Ambalajların artık yakınındaki alıcılara görünüyor.</Text>
          <View style={s.successSummary}>
            <View style={s.successSummaryItem}>
              <Text style={s.successSummaryValue}>{listingCount(listing)}</Text>
              <Text style={s.successSummaryLabel}>AMBALAJ</Text>
            </View>
            <View style={s.successSummaryDivider} />
            <View style={s.successSummaryItem}>
              <Text style={s.successSummaryValue}>{money(listingPrice(listing))}</Text>
              <Text style={s.successSummaryLabel}>TOPLAM</Text>
            </View>
            <View style={s.successSummaryDivider} />
            <View style={s.successSummaryItem}>
              <Text style={s.successSummaryValue}>{listing.expiresInDays || 30}</Text>
              <Text style={s.successSummaryLabel}>GÜN YAYINDA</Text>
            </View>
          </View>
          <Text style={s.successHint}>Süresi dolmadan önce ilanını yenileyebilirsin.</Text>
          <RewardedListingBoostButton listing={listing} token={token} userId={userId} onBoosted={onBoosted} />
          <Pressable onPress={openListing} style={s.successPrimary}>
            <Text style={s.successPrimaryText}>İlanı görüntüle</Text>
          </Pressable>
          <Pressable onPress={close} style={s.successSecondary}>
            <Text style={s.successSecondaryText}>Ana sayfaya dön</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}
function CreateModal({
  visible,
  close,
  create,
  selectedAddress,
  openAddressPicker,
  bottomInset,
}: {
  visible: boolean;
  close: () => void;
  create: (listing: NewListing) => Promise<boolean>;
  selectedAddress: DeliveryAddress | null;
  openAddressPicker: () => void;
  bottomInset: number;
}) {
  const { showNotice } = useNotice();
  const [lines, setLines] = useState(initialForm);
  const [note, setNote] = useState('');
  const [conditionConfirmed, setConditionConfirmed] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const updateLine = (material: Material, patch: Partial<FormLine>) =>
    setLines(current => ({ ...current, [material]: { ...current[material], ...patch } }));
  const selectedItems = MATERIALS
    .filter(material => lines[material].enabled)
    .map(material => ({
      material,
      count: Number(lines[material].count) || 0,
      unitPrice: Number(lines[material].price.replace(',', '.')) || 0,
    }));
  const quantity = selectedItems.reduce((sum, item) => sum + item.count, 0);
  const total = selectedItems.reduce((sum, item) => sum + item.count * item.unitPrice, 0);
  const submit = async () => {
    if (!selectedAddress) {
      showNotice({ tone: 'warning', title: 'Teslimat adresini seç', message: 'Kayıtlı adreslerinden birini seç veya haritadan yeni bir teslimat noktası belirle.' });
      return;
    }
    if (!selectedItems.length || selectedItems.some(item => item.count < 1)) {
      showNotice({ tone: 'warning', title: 'Adetleri kontrol et', message: 'En az bir malzeme türü seç ve seçtiğin her tür için en az 1 adet gir.' });
      return;
    }
    if (selectedItems.some(item => item.unitPrice <= 0 || item.unitPrice > 1)) {
      showNotice({ tone: 'warning', title: 'Fiyatları kontrol et', message: 'Her adet fiyatı 0 TL’den büyük ve en fazla 1 TL olmalı.' });
      return;
    }
    const cleanNote = note.trim();
    if (cleanNote.length < 10) {
      showNotice({ tone: 'warning', title: 'Açıklamayı tamamla', message: 'İlan açıklaması en az 10 karakter olmalı.' });
      return;
    }
    if (!conditionConfirmed) {
      showNotice({ tone: 'warning', title: 'Ambalaj uygunluğunu onayla', message: 'İlanı yayınlamadan önce ambalajların DOA iade koşullarına uygun olduğunu onaylamalısın.' });
      return;
    }
    setSubmitting(true);
    const created = await create({
      items: selectedItems,
      note: cleanNote,
      conditionConfirmed,
    });
    setSubmitting(false);
    if (created) {
      setLines(initialForm);
      setNote('');
      setConditionConfirmed(false);
      close();
    }
  };
  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={close}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={s.modalBackdrop}>
        <View style={[s.modalSheet, { paddingBottom: Math.max(bottomInset, 14) + 10 }]}>
          <View style={s.handle} />
          <View style={s.modalHeader}>
            <View><Text style={s.screenEyebrow}>YENİ İLAN</Text><Text style={s.modalTitle}>Ambalajlarını ilana koy</Text></View>
            <Pressable onPress={close} style={s.close}><Text style={s.closeText}>×</Text></Pressable>
          </View>
          <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
            <Text style={s.inputLabel}>TESLİMAT ADRESİ</Text>
            <Pressable onPress={openAddressPicker} style={s.deliveryAddressCard}>
              <View style={s.deliveryAddressIcon}><Text style={s.deliveryAddressIconText}>⌖</Text></View>
              <View style={s.deliveryAddressCopy}>
                <View style={s.deliveryAddressTitleRow}>
                  <Text style={s.deliveryAddressTitle}>{selectedAddress?.label || 'Teslimat adresi seç'}</Text>
                  {selectedAddress?.isDefault && <Text style={s.deliveryDefaultBadge}>VARSAYILAN</Text>}
                </View>
                <Text style={s.deliveryAddressText} numberOfLines={2}>
                  {selectedAddress ? selectedAddress.publicArea + ' · ' + selectedAddress.fullAddress : 'Kayıtlı adreslerinden birini seç veya yeni bir teslimat adresi ekle.'}
                </Text>
              </View>
              <Text style={s.menuArrow}>›</Text>
            </Pressable>
            <Text style={s.formHint}>Aynı ilana bir veya birden fazla ambalaj türü ekleyebilirsin.</Text>
            {MATERIALS.map(material => {
              const line = lines[material];
              const enteredPrice = Number(line.price.replace(',', '.')) || 0;
              const priceTooHigh = enteredPrice > 1;
              const countTooLow = line.count !== '' && Number(line.count) < 1;
              return (
                <View key={material} style={[s.materialFormCard, line.enabled && s.materialFormCardActive]}>
                  <Pressable onPress={() => updateLine(material, { enabled: !line.enabled })} style={s.materialFormHeader}>
                    <View style={[s.checkBox, line.enabled && s.checkBoxActive]}><Text style={s.checkMark}>{line.enabled ? '✓' : ''}</Text></View>
                    <MaterialIcon material={material} />
                    <Text style={s.materialFormName}>{material}</Text>
                    <Text style={s.materialFormAction}>{line.enabled ? 'Eklendi' : 'Ekle'}</Text>
                  </Pressable>
                  {line.enabled && (
                    <View style={s.materialInputs}>
                      <View style={s.formHalf}>
                        <Text style={s.inputLabel}>Adet</Text>
                        <TextInput
                          value={line.count}
                          onChangeText={value => updateLine(material, { count: value.replace(/\D/g, '') })}
                          keyboardType="number-pad"
                          style={[s.input, countTooLow && s.invalidInput]}
                        />
                        {countTooLow && <Text style={s.validationText}>Adet en az 1 olmalıdır.</Text>}
                      </View>
                      <View style={s.formHalf}>
                        <Text style={s.inputLabel}>Adet fiyatı</Text>
                        <View style={[s.inputSuffix, priceTooHigh && { borderColor: '#C53D3D', borderWidth: 1.5 }]}>
                          <TextInput value={line.price} onChangeText={value => updateLine(material, { price: value })} keyboardType="decimal-pad" style={s.suffixInput} />
                          <Text style={s.suffix}>TL</Text>
                        </View>
                        {priceTooHigh && <Text style={{ color: '#C53D3D', fontSize: 11, fontWeight: '700', marginTop: 5 }}>En fazla 1,00 TL girebilirsin.</Text>}
                      </View>
                    </View>
                  )}
                </View>
              );
            })}
            <View style={{ marginTop: 4, marginBottom: 16 }}>
              <Text style={s.inputLabel}>İLAN AÇIKLAMASI</Text>
              <TextInput
                value={note}
                onChangeText={setNote}
                placeholder="Ambalajların durumu hakkında kısa bilgi yaz."
                placeholderTextColor="#89938e"
                multiline
                maxLength={300}
                style={[s.input, { height: 96, paddingTop: 12, textAlignVertical: 'top' }]}
              />
              <Text style={{ marginTop: 6, color: '#78827d', fontSize: 12, textAlign: 'right' }}>{note.length}/300</Text>
            </View>
            <Pressable
              accessibilityRole="checkbox"
              accessibilityState={{ checked: conditionConfirmed }}
              accessibilityLabel="Ambalaj uygunluk beyanını onayla"
              onPress={() => setConditionConfirmed(current => !current)}
              style={({ pressed }) => [s.conditionCard, conditionConfirmed && s.conditionCardChecked, pressed && s.pressed]}
            >
              <View style={[s.conditionCheckbox, conditionConfirmed && s.conditionCheckboxChecked]}>
                {conditionConfirmed && <Text style={s.conditionCheck}>✓</Text>}
              </View>
              <View style={s.conditionCopy}>
                <Text style={s.conditionTitle}>Ambalaj uygunluk beyanı</Text>
                <Text style={s.conditionText}>İlandaki tüm ambalajların üzerinde okunabilir DOA işareti bulunduğunu; ambalajların boş, deforme olmamış, kırılmamış ve bütünlüğü bozulmamış olduğunu onaylıyorum.</Text>
                <Text style={s.conditionHint}>Uygun olmayan ambalajlar teslim sırasında alıcı tarafından kabul edilmeyebilir.</Text>
              </View>
            </Pressable>
            <View style={s.calculation}>
              <View><Text style={s.calcLabel}>{quantity} AMBALAJIN SATIŞI</Text><Text style={s.calcValue}>{money(total)}</Text></View>
              <View style={s.calcDivider} />
              <View><Text style={s.calcLabel}>POTANSİYEL BRÜT FARK</Text><Text style={s.calcGain}>+{money(quantity - total)}</Text></View>
            </View>
            <Pressable onPress={submit} disabled={submitting} style={[s.primary, submitting && { opacity: .65 }]}>
              {submitting ? <ActivityIndicator color="#FFFFFF" /> : <Text style={s.primaryText}>İlanı yayınla</Text>}
            </Pressable>
          </ScrollView>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

function Profile({ fullName, onSignOut, openAddresses }: { fullName: string; onSignOut: () => void; openAddresses: () => void }) {
  return (
    <ScrollView style={s.screenPad} contentContainerStyle={s.pageBottom}>
      <Text style={s.screenEyebrow}>HESABIM</Text><Text style={s.screenTitle}>Profil</Text>
      <View style={s.profileCard}>
        <View style={s.profileAvatar}><Text style={s.profileAvatarText}>{fullName.trim()[0]?.toUpperCase() || 'D'}</Text></View>
        <Text style={s.profileName}>{fullName}</Text>
        <Text style={s.profileMeta}>Doğrulanmış hesap</Text>
      </View>
      <Pressable onPress={openAddresses} style={s.menuRow}>
        <Text style={s.menuText}>Kayıtlı adreslerim</Text><Text style={s.menuArrow}>›</Text>
      </Pressable>
      {['İlanlarım', 'Alım taleplerim', 'Favorilerim', 'Bildirim ayarları', 'Yardım ve güvenlik'].map(item => (
        <Pressable key={item} style={s.menuRow}><Text style={s.menuText}>{item}</Text><Text style={s.menuArrow}>›</Text></Pressable>
      ))}
      <Pressable onPress={onSignOut} style={s.menuRow}><Text style={[s.menuText, { color: '#A23D32' }]}>Çıkış yap</Text><Text style={[s.menuArrow, { color: '#A23D32' }]}>›</Text></Pressable>
    </ScrollView>
  );
}

type NavigationIconName = 'home' | 'ranking' | 'messages' | 'profile';

function NavigationIcon({ name, active }: { name: NavigationIconName; active: boolean }) {
  const color = active ? C.green : '#7F8D84';

  if (name === 'ranking') {
    return <Text style={[s.rankingNavIcon, active && s.navActive]}>♻</Text>;
  }

  const common = { fill: 'none', stroke: color, strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const };

  return (
    <Svg width={22} height={22} viewBox="0 0 24 24" accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
      {name === 'home' && (
        <>
          <Path d="M3.5 10.5 12 3l8.5 7.5" {...common} />
          <Path d="M5.5 9.4V21h13V9.4M9.2 21v-7h5.6v7" {...common} />
        </>
      )}
      {name === 'messages' && (
        <>
          <Path d="M20.5 11.2a7.8 7.8 0 0 1-8.1 7.4 9.2 9.2 0 0 1-3.2-.7L4 20l1.5-4a7 7 0 0 1-2-4.8c0-4.1 3.8-7.4 8.5-7.4s8.5 3.3 8.5 7.4Z" {...common} />
          <Path d="M8.2 10.9h.1m3.6 0h.1m3.6 0h.1" {...common} strokeWidth={2.4} />
        </>
      )}
      {name === 'profile' && (
        <>
          <Circle cx="12" cy="8" r="4" {...common} />
          <Path d="M4.5 21c.6-4.2 3.3-6.5 7.5-6.5s6.9 2.3 7.5 6.5" {...common} />
        </>
      )}
    </Svg>
  );
}
function NavButton({ icon, label, active, onPress, badgeCount = 0 }: {
  icon: NavigationIconName;
  label: string;
  active: boolean;
  onPress: () => void;
  badgeCount?: number;
}) {
  return (
    <Pressable accessibilityRole="tab" accessibilityLabel={`${label}${badgeCount > 0 ? `, ${badgeCount} okunmamış` : ''}`} accessibilityState={{ selected: active }} onPress={onPress} style={s.navItem}>
      <View style={s.navIconWrap}>
        <NavigationIcon name={icon} active={active} />
        {badgeCount > 0 && (
          <View style={s.navBadge}><Text style={s.navBadgeText}>{badgeCount > 99 ? '99+' : badgeCount}</Text></View>
        )}
      </View>
      <Text style={[s.navLabel, active && s.navActive]}>{label}{badgeCount > 0 ? ' (' + badgeCount + ')' : ''}</Text>
    </Pressable>
  );
}

type MarketplaceAppProps = { fullName: string; userEmail?: string; userEmailVerified?: boolean; userId?: string; token?: string | null; avatarUrl?: string | null; onProfileUpdated?: (fullName: string) => Promise<{ error?: string }>; onProfileRefresh?: () => Promise<void>; onSignOut: () => void; onDeleteAccount?: (confirmation: string) => Promise<{ error?: string }>;  isGuest?: boolean; onRequireAuth?: () => void };

function AppContent({ fullName, userEmail = '', userEmailVerified = false, userId, token, avatarUrl, onProfileUpdated, onProfileRefresh, onSignOut, onDeleteAccount, isGuest = false, onRequireAuth }: MarketplaceAppProps) {
  const { showNotice } = useNotice();
  const { fontScale } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const [route, setRoute] = useState<Route>('home');
  const [chatBackRoute, setChatBackRoute] = useState<'detail' | 'messages' | 'purchase-history' | 'notifications'>('detail');
  const [detailBackRoute, setDetailBackRoute] = useState<'home' | 'favorites' | 'my-listings' | 'notifications' | 'public-profile'>('home');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [publicProfileUserId, setPublicProfileUserId] = useState<number | null>(null);
  const [publicProfileListing, setPublicProfileListing] = useState<Listing | null>(null);
  const [publicProfileBackRoute, setPublicProfileBackRoute] = useState<'detail' | 'chat' | 'home' | 'profile'>('home');
  const [category, setCategory] = useState<ListingCategory>('Tümü');
  const [sort, setSort] = useState<ListingSort>('distance');
  const [listings, setListings] = useState<Listing[]>([]);

  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [realtimeVersion, setRealtimeVersion] = useState(0);
  const [notificationRefreshSignal, setNotificationRefreshSignal] = useState(0);
  const [unreadNotificationCount, setUnreadNotificationCount] = useState(0);
  const [activeConversationId, setActiveConversationId] = useState<number | null>(null);
  const [listingLoading, setListingLoading] = useState(false);
  const [listingRefreshing, setListingRefreshing] = useState(false);
  const [listingLoadingMore, setListingLoadingMore] = useState(false);
  const [listingPage, setListingPage] = useState(0);
  const [listingLastPage, setListingLastPage] = useState(0);
  const [listingTotal, setListingTotal] = useState(0);
  const [listingError, setListingError] = useState('');
  const [favoritePendingIds, setFavoritePendingIds] = useState<Set<number>>(new Set());
  const listingRequestId = useRef(0);
  const [publishedListing, setPublishedListing] = useState<Listing | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [addressMode, setAddressMode] = useState<'select' | 'manage' | null>(null);
  const [selectedAddress, setSelectedAddress] = useState<DeliveryAddress | null>(null);
  const [locationOpen, setLocationOpen] = useState(false);
  const [requesting, setRequesting] = useState(false);
  const [permissionDenied, setPermissionDenied] = useState(false);
  const [locationReady, setLocationReady] = useState(false);
  const [center, setCenter] = useState<Coordinates>(EMPTY_CENTER);
  const [radius, setRadius] = useState(10);
  const [locationLabel, setLocationLabel] = useState('Konumunu kullan');
  const [supporterRegion, setSupporterRegion] = useState<SupporterRegion>({});
  const showPickupInterstitial = usePickupInterstitial(!isGuest && !!token);

  useEffect(() => {
    if (!createOpen || !token || selectedAddress) return;
    let active = true;

    void apiRequest<AddressCollectionResponse>('/addresses', { token })
      .then(response => {
        const defaultAddress = response.data.find(address => address.isDefault);
        if (!active || !defaultAddress) return;
        setSelectedAddress(current => current ?? { ...defaultAddress, saved: true });
      })
      .catch(() => {
        // Adres seçme ekranı kullanılabilir kalır; otomatik seçim ilan formunu engellemez.
      });

    return () => { active = false; };
  }, [createOpen, selectedAddress, token]);


  const loadListings = useCallback(async (page = 1, mode: ListingLoadMode = 'replace') => {
    if (!locationReady) {
      listingRequestId.current += 1;
      setListings([]);
      setListingTotal(0);
      setListingPage(0);
      setListingLastPage(0);
      setListingError('');
      setListingLoading(false);
      setListingRefreshing(false);
      setListingLoadingMore(false);
      return;
    }

    const requestId = ++listingRequestId.current;
    if (mode === 'replace') {
      setListingLoading(true);
      setListingError('');
      setListings([]);
      setListingTotal(0);
    } else if (mode === 'refresh') {
      setListingRefreshing(true);
    } else {
      setListingLoadingMore(true);
    }

    const queryParts = [
      'latitude=' + encodeURIComponent(String(center.latitude)),
      'longitude=' + encodeURIComponent(String(center.longitude)),
      'radius=' + encodeURIComponent(String(radius)),
      'sort=' + encodeURIComponent(sort),
      'page=' + encodeURIComponent(String(page)),
      'per_page=20',
    ];
    if (supporterRegion.province) queryParts.push('province=' + encodeURIComponent(supporterRegion.province));
    if (category !== 'Tümü') queryParts.push('material=' + encodeURIComponent(MATERIAL_API_TYPES[category]));

    try {
      const response = await apiRequest<ListingCollectionResponse>('/listings?' + queryParts.join('&'), { token });
      if (requestId !== listingRequestId.current) return;
      if (mode === 'more') {
        setListings(current => {
          const knownIds = new Set(current.map(item => item.id));
          return [...current, ...response.data.filter(item => !knownIds.has(item.id))];
        });
      } else {
        setListings(response.data);
      }
      setListingPage(response.meta.current_page);
      setListingLastPage(response.meta.last_page);
      setListingTotal(response.meta.total);
      setListingError('');
    } catch (error) {
      if (requestId !== listingRequestId.current) return;
      const message = error instanceof ApiError ? error.message : 'İlan servisine ulaşılamadı.';
      if (mode === 'replace') {
        setListings([]);
        setListingTotal(0);
        setListingError(message);
      } else {
        showNotice({
          tone: 'error',
          title: mode === 'more' ? 'Diğer ilanlar yüklenemedi' : 'İlanlar yenilenemedi',
          message: 'Mevcut ilanları göstermeye devam ediyoruz. Biraz sonra yeniden deneyebilirsin.',
        });
      }
    } finally {
      if (requestId === listingRequestId.current) {
        if (mode === 'replace') setListingLoading(false);
        if (mode === 'refresh') setListingRefreshing(false);
        if (mode === 'more') setListingLoadingMore(false);
      }
    }
  }, [category, center.latitude, center.longitude, locationReady, radius, showNotice, sort, supporterRegion.province, token]);

  useEffect(() => {
    void loadListings(1, 'replace');
  }, [loadListings]);

  const refreshListings = useCallback(() => {
    if (!listingLoading && !listingRefreshing) void loadListings(1, 'refresh');
  }, [listingLoading, listingRefreshing, loadListings]);

  const loadMoreListings = useCallback(() => {
    if (!listingLoading && !listingRefreshing && !listingLoadingMore && listingPage < listingLastPage) {
      void loadListings(listingPage + 1, 'more');
    }
  }, [listingLastPage, listingLoading, listingLoadingMore, listingPage, listingRefreshing, loadListings]);

  const setListingFavorite = useCallback((listingId: number, isFavorited: boolean) => {
    setListings(current => current.map(item => item.id === listingId ? { ...item, isFavorited } : item));
  }, []);

  const toggleFavorite = useCallback(async (listing: Listing): Promise<boolean> => {
    if (!token || isGuest) {
      onRequireAuth?.();
      return false;
    }
    if (String(listing.sellerId) === userId) {
      showNotice({ tone: 'info', title: 'Bu senin ilanın', message: 'Kendi ilanını favorilerine eklemene gerek yok.' });
      return false;
    }
    if (favoritePendingIds.has(listing.id)) return false;

    const nextValue = !listing.isFavorited;
    setFavoritePendingIds(current => new Set(current).add(listing.id));
    setListingFavorite(listing.id, nextValue);
    try {
      await apiRequest(`/listings/${listing.id}/favorite`, {
        method: nextValue ? 'POST' : 'DELETE',
        token,
      });
      return true;
    } catch (error) {
      setListingFavorite(listing.id, !nextValue);
      showNotice({
        tone: 'error',
        title: nextValue ? 'Favoriye eklenemedi' : 'Favorilerden kaldırılamadı',
        message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.',
      });
      return false;
    } finally {
      setFavoritePendingIds(current => {
        const next = new Set(current);
        next.delete(listing.id);
        return next;
      });
    }
  }, [favoritePendingIds, isGuest, onRequireAuth, setListingFavorite, showNotice, token, userId]);

  useEffect(() => {
    const appState = AppState.addEventListener('change', state => {
      if (state === 'active' && locationReady) void loadListings(1, 'refresh');
    });
    return () => appState.remove();
  }, [loadListings, locationReady]);
  const loadNotificationCount = useCallback(async () => {
    if (!token) {
      setUnreadNotificationCount(0);
      return;
    }
    try {
      const response = await apiRequest<{ data: { unreadCount: number } }>('/notifications/unread-count', { token });
      setUnreadNotificationCount(response.data.unreadCount);
    } catch {
      // Sayaç hatası ana uygulama akışını engellemez.
    }
  }, [token]);

  const loadConversations = useCallback(async (quiet = false) => {
    if (!token) {
      setConversations([]);
      return;
    }
    try {
      const response = await apiRequest<ConversationCollectionResponse>('/conversations', { token });
      setConversations(response.data);
    } catch (error) {
      if (!quiet) showNotice({ tone: 'error', title: 'Görüşmeler alınamadı', message: error instanceof ApiError ? error.message : 'Mesaj servisine ulaşılamadı.' });
    }
  }, [showNotice, token]);

  useEffect(() => {
    if (!token) {
      setUnreadNotificationCount(0);
      return;
    }
    void loadConversations(true);
    void loadNotificationCount();
    const appState = AppState.addEventListener('change', state => {
      if (state === 'active') {
        void loadConversations(true);
        void loadNotificationCount();
      }
    });
    const unsubscribe = userId ? subscribeToConversations(token, userId, () => {
      setRealtimeVersion(version => version + 1);
      void loadConversations(true);
    }, event => {
      setUnreadNotificationCount(event.unreadCount);
      setNotificationRefreshSignal(version => version + 1);
    }) : () => {};
    return () => { appState.remove(); unsubscribe(); };
  }, [loadConversations, loadNotificationCount, token, userId]);

  useEffect(() => {
    if (Platform.OS !== 'android') return;
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => {
      if (route === 'chat') {
        void loadConversations(true);
        setRoute(chatBackRoute);
        return true;
      }
      if (route === 'detail') {
        setRoute(detailBackRoute);
        return true;
      }
      if (route === 'public-profile') {
        setRoute(publicProfileBackRoute);
        return true;
      }
      if (route === 'favorites' || route === 'my-listings' || route === 'purchase-history' || route === 'addresses' || route === 'profile-edit' || route === 'notification-preferences' || route === 'blocked-users' || route === 'legal-terms' || route === 'legal-privacy') {
        setRoute('profile');
        return true;
      }

      if (route === 'notifications') {
        setRoute('home');
        return true;
      }
      if (route === 'supporters') {
        setRoute('home');
        return true;
      }
      if (route === 'ranking' || route === 'messages' || route === 'profile') {
        setRoute('home');
        return true;
      }
      return false;
    });
    return () => subscription.remove();
  }, [chatBackRoute, detailBackRoute, loadConversations, publicProfileBackRoute, route]);

  const selected = listings.find(item => item.id === selectedId);
  const activeConversation = conversations.find(item => item.id === activeConversationId) || null;
  const unreadMessageCount = conversations.reduce((sum, item) => sum + item.unreadCount, 0);
  const radiusListings = listings.filter(item => String(item.sellerId) !== String(userId || ''));
  const openDetail = (id: number) => {
    setDetailBackRoute('home');
    setSelectedId(id);
    setRoute('detail');
  };
  const openFavoriteListing = (listing: Listing) => {
    setListings(current => [listing, ...current.filter(item => item.id !== listing.id)]);
    setDetailBackRoute('favorites');
    setSelectedId(listing.id);
    setRoute('detail');
  };
  const openMyListing = (listing: Listing) => {
    setListings(current => [listing, ...current.filter(item => item.id !== listing.id)]);
    setDetailBackRoute('my-listings');
    setSelectedId(listing.id);
    setRoute('detail');
  };
  const openAppNotification = useCallback(async (notification: AppNotification) => {
    if (!token) {
      setRoute('home');
      return;
    }
    if (notification.id > 0) {
      try {
        await apiRequest(`/notifications/${notification.id}/read`, { method: 'PATCH', token });
        void loadNotificationCount();
        setNotificationRefreshSignal(version => version + 1);
      } catch {}
    }
    const conversationId = notification.data.conversationId;
    if (conversationId) {
      try {
        const response = await apiRequest<ConversationCollectionResponse>('/conversations', { token });
        setConversations(response.data);
        const conversation = response.data.find(item => item.id === conversationId);
        if (conversation) {
          setActiveConversationId(conversation.id);
          setChatBackRoute('notifications');
          setRoute('chat');
          return;
        }
      } catch {}
    }
    if (notification.data.route === 'notifications') {
      setRoute('notifications');
      return;
    }
    const listingId = notification.data.listingId;
    if (listingId) {
      try {
        const response = await apiRequest<ListingResponse>(`/listings/${listingId}`, { token });
        setListings(current => [response.data, ...current.filter(item => item.id !== response.data.id)]);
        setSelectedId(response.data.id);
        setDetailBackRoute('notifications');
        setRoute('detail');
        return;
      } catch {}
    }
    showNotice({ tone: 'info', title: 'İçerik artık kullanılamıyor', message: 'Bildirim okundu olarak işaretlendi ancak ilgili görüşme veya ilan artık açılamıyor.' });
    setRoute('notifications');
  }, [loadNotificationCount, showNotice, token]);
  useEffect(() => {
    if (!token) return;
    return observeNotificationResponses(data => {
      const conversationId = Number(data.conversationId);
      const listingId = Number(data.listingId);
      void openAppNotification({
        id: Number(data.notificationId) || 0,
        type: 'push',
        title: '',
        body: '',
        data: {
          ...(Number.isFinite(conversationId) && conversationId > 0 ? { conversationId } : {}),
          ...(Number.isFinite(listingId) && listingId > 0 ? { listingId } : {}),
        },
        read: true,
        createdAt: null,
        time: null,
      });
    });
  }, [openAppNotification, token]);
  const openConversation = (conversation: Conversation, from: 'detail' | 'messages') => {
    setActiveConversationId(conversation.id);
    setChatBackRoute(from);
    setRoute('chat');
  };
  const openPurchaseHistoryConversation = (conversation: Conversation) => {
    setConversations(current => [conversation, ...current.filter(item => item.id !== conversation.id)]);
    setActiveConversationId(conversation.id);
    setChatBackRoute('purchase-history');
    setRoute('chat');
  };
  const markConversationRead = useCallback((conversationId: number) => {
    setConversations(current => current.map(item => item.id === conversationId ? { ...item, unreadCount: 0 } : item));
  }, []);
  const updateConversation = (conversation: Conversation) => {
    const requestStatus = conversation.status === 'accepted'
      ? 'reserved'
      : conversation.status === 'pending'
        ? 'pending'
        : conversation.status === 'rejected'
          ? 'rejected'
          : conversation.status === 'cancelled'
            ? 'cancelled'
            : 'none';
    setConversations(current => [conversation, ...current.filter(item => item.id !== conversation.id)]);
    setListings(current => current.map(item => item.id === conversation.listing.id
      ? { ...item, status: conversation.listing.status, requestStatus }
      : item));
  };
  const startConversation = async (listing: Listing, intent: 'message' | 'pickup') => {
    if (isGuest || !token) {
      onRequireAuth?.();
      return;
    }
    const existing = conversations.find(item => item.listing.id === listing.id && item.role === 'buyer');
    const hasActivePickup = existing && ['pending', 'accepted', 'rejected', 'completed'].includes(existing.status);
    if (existing && (intent === 'message' || hasActivePickup)) {
      openConversation(existing, 'detail');
      return;
    }
    try {
      const response = await apiRequest<ConversationResponse>('/listings/' + listing.id + '/pickup-requests', {
        method: 'POST', token, body: { intent },
      });
      updateConversation(response.data);
      openConversation(response.data, 'detail');
      if (intent === 'pickup' && response.monetization?.showInterstitial) {
        setTimeout(() => showPickupInterstitial(), 450);
      }
    } catch (error) {
      showNotice({ tone: 'error', title: intent === 'pickup' ? 'Alım talebi gönderilemedi' : 'Görüşme başlatılamadı', message: error instanceof ApiError ? error.message : 'Mesaj servisine ulaşılamadı.' });
    }
  };
  const requestPickup = (listing: Listing) => void startConversation(listing, 'pickup');
  const openPublicProfile = (listing: Listing) => {
    setPublicProfileUserId(listing.sellerId);
    setPublicProfileListing(listing);
    setPublicProfileBackRoute('detail');
    setRoute('public-profile');
  };
  const openPublicProfileListing = (listing: Listing) => {
    setListings(current => [listing, ...current.filter(item => item.id !== listing.id)]);
    setSelectedId(listing.id);
    setDetailBackRoute('public-profile');
    setRoute('detail');
  };
  const refreshAfterBlockChange = useCallback(async () => {
    await Promise.all([loadConversations(true), loadListings(1, 'refresh')]);
  }, [loadConversations, loadListings]);
  const updateLocationFromDevice = useCallback(async (requestPermission: boolean, closePicker: boolean) => {
    try {
      if (requestPermission) setRequesting(true);
      const permission = requestPermission
        ? await Location.requestForegroundPermissionsAsync()
        : await Location.getForegroundPermissionsAsync();
      if (permission.status !== 'granted') {
        if (requestPermission) setPermissionDenied(true);
        return;
      }

      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      const nextCenter = { latitude: position.coords.latitude, longitude: position.coords.longitude };
      setCenter(nextCenter);
      setLocationReady(true);
      setPermissionDenied(false);
      try {
        const [address] = await Location.reverseGeocodeAsync(nextCenter);
        const area = address?.district || address?.subregion || address?.city;
        const city = address?.region || address?.city;
        const administrativeDistrict = address?.subregion || address?.district || address?.city;
        setLocationLabel(area && city && area !== city ? area + ', ' + city : city || area || 'Mevcut konum');
        setSupporterRegion({ province: city || undefined, district: administrativeDistrict || undefined });
      } catch {
        setLocationLabel('Mevcut konum');
      }
      if (closePicker) setLocationOpen(false);
    } catch {
      if (requestPermission) {
        showNotice({ tone: 'error', title: 'Konum alınamadı', message: 'Telefonunun konum servisinin açık olduğundan emin ol ve yeniden dene.' });
      }
    } finally {
      if (requestPermission) setRequesting(false);
    }
  }, []);

  useEffect(() => {
    void (async () => {
      const permission = await Location.getForegroundPermissionsAsync();
      if (permission.status === 'granted') {
        await updateLocationFromDevice(false, false);
      }
    })();
  }, [updateLocationFromDevice]);

  const useCurrentLocation = () => {
    void updateLocationFromDevice(true, true);
  };

  const addListing = async (listing: NewListing): Promise<boolean> => {
    if (!token || !selectedAddress) {
      showNotice({ tone: 'warning', title: 'Adres ve oturum gerekli', message: 'İlan yayınlamak için hesabınla giriş yapmalı ve teslimat adresini seçmelisin.' });
      return false;
    }


    try {
      const response = await apiRequest<ListingResponse>('/listings', {
        method: 'POST',
        token,
        body: {
          materials: listing.items.map(item => ({
            type: MATERIAL_API_TYPES[item.material],
            quantity: item.count,
            unit_price: item.unitPrice,
          })),
          description: listing.note,
          packaging_condition_confirmed: listing.conditionConfirmed,
          ...(selectedAddress.saved && selectedAddress.id
            ? { address_id: selectedAddress.id }
            : {
                public_area: selectedAddress.publicArea,
                latitude: selectedAddress.latitude,
                longitude: selectedAddress.longitude,
                exact_address: selectedAddress.fullAddress,
                delivery_notes: selectedAddress.deliveryNotes || null,
              }),
        },
      });
      setListings(current => [response.data, ...current.filter(item => item.id !== response.data.id)]);
      setSelectedAddress(null);
      setRoute('home');
      setTimeout(() => setPublishedListing(response.data), 250);
      return true;
    } catch (error) {
      showNotice({ tone: 'error', title: 'İlan yayınlanamadı', message: error instanceof ApiError ? error.message : 'İlan servisine ulaşılamadı.' });
      return false;
    }
  };
  const publicProfileTab: Tab = publicProfileBackRoute === 'chat'
    ? 'messages'
    : publicProfileBackRoute === 'profile' || (publicProfileBackRoute === 'detail' && ['favorites', 'my-listings'].includes(detailBackRoute))
      ? 'profile'
      : 'home';
  const activeTab: Tab = route === 'detail'
    ? (['favorites', 'my-listings'].includes(detailBackRoute) ? 'profile' : detailBackRoute === 'public-profile' ? publicProfileTab : 'home')
    : route === 'favorites' || route === 'my-listings' || route === 'purchase-history' || route === 'addresses' || route === 'profile-edit' || route === 'notification-preferences' || route === 'blocked-users' || route === 'legal-terms' || route === 'legal-privacy' || route === 'account-deletion'
      ? 'profile'
      : route === 'notifications'
        ? 'home'
        : route === 'supporters'
          ? 'home'
          : route === 'public-profile'
          ? publicProfileTab
          : route === 'chat'
            ? 'messages'
            : route;
  const bottomNavHeight = 78 + Math.max(insets.bottom, 10) + Math.max(0, fontScale - 1) * 20;
  const tabActive = (tab: Tab) => activeTab === tab;
  const goTab = (tab: Tab) => {
    setRoute(tab);
    setSelectedId(null);
  };

  return (
    <View style={s.app}>
      <StatusBar style="dark" />
      <View style={[s.content, { paddingTop: Math.max(insets.top, 12) }]}>
        {route === 'home' && (
          <Home
            listings={radiusListings}
            center={locationReady ? center : null}
            radius={radius}
            locationLabel={locationLabel}
            locationReady={locationReady}
            category={category}
            setCategory={setCategory}
            sort={sort}
            setSort={setSort}
            openLocation={() => setLocationOpen(true)}
            openListing={openDetail}
            loading={listingLoading}
            refreshing={listingRefreshing}
            loadingMore={listingLoadingMore}
            total={listingTotal}
            hasMore={listingPage < listingLastPage}
            error={listingError}
            reload={() => void loadListings(1, 'replace')}
            refresh={refreshListings}
            loadMore={loadMoreListings}
            currentUserId={userId || ''}
            favoritePendingIds={favoritePendingIds}
            toggleFavorite={toggleFavorite}
            unreadNotificationCount={unreadNotificationCount}
            openNotifications={() => isGuest ? onRequireAuth?.() : setRoute('notifications')}
            token={token}
          />
        )}
        {route === 'ranking' && <LeaderboardScreen token={token} userId={userId} requireAuth={onRequireAuth} />}
        {route === 'supporters' && <SupportersScreen locationLabel={locationLabel} region={supporterRegion} back={() => setRoute('home')} />}
        {route === 'messages' && <ConversationList conversations={conversations} open={conversation => openConversation(conversation, 'messages')} />}
        {route === 'profile' && (token ?
          <ProfileMenuScreen
            fullName={fullName}
            avatarUrl={avatarUrl}
            email={userEmail}
            emailVerified={userEmailVerified}
            token={token}
            openPublicProfile={() => { setPublicProfileUserId(Number(userId)); setPublicProfileListing(null); setPublicProfileBackRoute('profile'); setRoute('public-profile'); }}
            openEditProfile={() => setRoute('profile-edit')}
            openAddresses={() => setRoute('addresses')}
            openFavorites={() => setRoute('favorites')}
            openMyListings={() => setRoute('my-listings')}
            openPurchaseHistory={() => setRoute('purchase-history')}
            openNotificationPreferences={() => setRoute('notification-preferences')}
            openBlockedUsers={() => setRoute('blocked-users')}
            openTerms={() => setRoute('legal-terms')}
            openPrivacy={() => setRoute('legal-privacy')}
            openDeleteAccount={() => setRoute('account-deletion')}
            onSignOut={onSignOut}
          />
          : <Profile fullName={fullName} onSignOut={onSignOut} openAddresses={() => onRequireAuth?.()} />
        )}
        {route === 'profile-edit' && !!token && (
          <ProfileInfoModal visible embedded token={token} initialName={fullName} initialEmail={userEmail} close={() => setRoute('profile')} saveName={onProfileUpdated || (async () => ({ error: 'Profil güncelleme servisi hazır değil.' }))} onAvatarChanged={onProfileRefresh} />
        )}
        {route === 'notification-preferences' && !!token && <NotificationPreferencesScreen token={token} back={() => setRoute('profile')} />}
        {route === 'blocked-users' && !!token && <BlockedUsersScreen token={token} back={() => setRoute('profile')} onBlocksChanged={refreshAfterBlockChange} />}
        {route === 'legal-terms' && <LegalDocumentScreen documentKey="terms" back={() => setRoute('profile')} />}
        {route === 'legal-privacy' && <LegalDocumentScreen documentKey="privacy" back={() => setRoute('profile')} />}
        {route === 'account-deletion' && !!token && !!onDeleteAccount && <AccountDeletionScreen back={() => setRoute('profile')} deleteAccount={onDeleteAccount} />}
        {route === 'addresses' && !!token && (
          <AddressBookModal
            visible
            embedded
            token={token}
            mode="manage"
            initialCoordinates={locationReady ? center : null}
            onClose={() => setRoute('profile')}
          />
        )}
        {route === 'purchase-history' && !!token && (
          <PurchaseHistoryScreen
            token={token}
            back={() => setRoute('profile')}
            openConversation={openPurchaseHistoryConversation}
          />
        )}
        {route === 'my-listings' && !!token && (
          <MyListingsScreen
            token={token}
            userId={userId || ''}
            back={() => setRoute('profile')}
            openListing={openMyListing}
            createListing={() => setCreateOpen(true)}
            onListingUpdated={listing => setListings(current => [listing, ...current.filter(item => item.id !== listing.id)])}
            onListingRemoved={listingId => setListings(current => current.filter(item => item.id !== listingId))}
          />
        )}
        {route === 'favorites' && !!token && (
          <FavoritesScreen
            token={token}
            userId={userId || ''}
            back={() => setRoute('profile')}
            openListing={openFavoriteListing}
            toggleFavorite={toggleFavorite}
            pendingIds={favoritePendingIds}
          />
        )}
        {route === 'notifications' && !!token && (
          <NotificationCenter
            token={token}
            back={() => setRoute('home')}
            onUnreadCount={setUnreadNotificationCount}
            openNotification={notification => void openAppNotification(notification)}
            refreshSignal={notificationRefreshSignal}
          />
        )}
        {route === 'detail' && !!selected && (
          <ListingDetail
            listing={selected}
            center={locationReady ? center : null}
            back={() => setRoute(detailBackRoute)}
            messageSeller={() => void startConversation(selected, 'message')}
            requestPickup={() => requestPickup(selected)}
            isOwn={String(selected.sellerId) === userId}
            bottomInset={0}
            token={token}
            requireAuth={onRequireAuth}
            openSellerProfile={() => openPublicProfile(selected)}
          />
        )}
        {route === 'public-profile' && publicProfileUserId !== null && (
          <PublicUserProfileScreen
            userId={publicProfileUserId}
            token={token}
            currentUserId={userId || ''}
            center={locationReady ? center : null}
            favoritePendingIds={favoritePendingIds}
            toggleFavorite={toggleFavorite}
            bottomInset={0}
            contextListing={publicProfileListing}
            back={() => setRoute(publicProfileBackRoute)}
            openListing={openPublicProfileListing}
            messageSeller={publicProfileListing ? () => void startConversation(publicProfileListing, 'message') : undefined}
            editOwnProfile={() => setRoute('profile-edit')}
            requireAuth={onRequireAuth}
            onBlockChanged={refreshAfterBlockChange}
            onBlocked={() => { setPublicProfileListing(null); setRoute('home'); }}
          />
        )}
        {route === 'chat' && !!activeConversation && !!token && (
          <ConversationScreen
            conversation={activeConversation}
            token={token}
            back={() => {
              void loadConversations(true);
              setRoute(chatBackRoute);
            }}
            onUpdated={updateConversation}
            onRead={markConversationRead}
            onBlockChanged={refreshAfterBlockChange}
            onHidden={() => { setConversations(current => current.filter(item => item.id !== activeConversation.id)); setActiveConversationId(null); setRoute(chatBackRoute === 'purchase-history' ? 'purchase-history' : 'messages'); }}
            openProfile={() => { setPublicProfileUserId(activeConversation.counterpart.id); setPublicProfileListing(null); setPublicProfileBackRoute('chat'); setRoute('public-profile'); }}
            refreshSignal={realtimeVersion}
            bottomInset={insets.bottom}
          />
        )}
      </View>
      {route === 'home' && <SupportersDock locationLabel={locationLabel} onPress={() => setRoute('supporters')} bottomOffset={bottomNavHeight} />}
      {route !== 'chat' && (
        <View accessibilityRole="tablist" style={[s.nav, { minHeight: bottomNavHeight, paddingBottom: Math.max(insets.bottom, 10) }]} >
          <NavButton icon="home" label="Ana sayfa" active={tabActive('home')} onPress={() => goTab('home')} />
          <NavButton icon="ranking" label="Sıralama" active={tabActive('ranking')} onPress={() => goTab('ranking')} />
          <View style={s.createWrap}>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Yeni ilan oluştur"
              accessibilityHint="İlan oluşturma formunu açar"
              onPress={() => {
                if (isGuest) return onRequireAuth?.();
                setCreateOpen(true);
              }}
              style={({ pressed }) => [s.createButton, pressed && s.createPressed]}
            ><Text style={s.plus}>＋</Text></Pressable>
            <Text style={s.createLabel}>İlan ver</Text>
          </View>
          <NavButton icon="messages" label="Mesajlar" badgeCount={unreadMessageCount} active={tabActive('messages')} onPress={() => isGuest ? onRequireAuth?.() : goTab('messages')} />
          <NavButton icon="profile" label="Profil" active={tabActive('profile')} onPress={() => goTab('profile')} />
        </View>
      )}
      {!!publishedListing && (
        <ListingPublishedModal
          listing={publishedListing}
          close={() => setPublishedListing(null)}
          token={token!}
          userId={userId!}
          onBoosted={boosted => {
            setPublishedListing(boosted);
            setListings(current => current.map(item => item.id === boosted.id ? boosted : item));
          }}
          openListing={() => {
            setSelectedId(publishedListing.id);
            setRoute('detail');
            setPublishedListing(null);
          }}
        />
      )}
      <LocationPicker
        visible={locationOpen}
        close={() => setLocationOpen(false)}
        radius={radius}
        setRadius={setRadius}
        locationLabel={locationLabel}
        locationReady={locationReady}
        permissionDenied={permissionDenied}
        useCurrentLocation={useCurrentLocation}
        requesting={requesting}
        resultCount={listingTotal}
        bottomInset={insets.bottom}
      />
      <CreateModal
        visible={createOpen}
        close={() => {
          setCreateOpen(false);
          setSelectedAddress(null);
        }}
        create={addListing}
        selectedAddress={selectedAddress}
        openAddressPicker={() => setAddressMode('select')}
        bottomInset={insets.bottom}
      />
      {!!token && (
        <AddressBookModal
          visible={addressMode === 'select'}
          token={token}
          mode="select"
          initialCoordinates={locationReady ? center : null}
          onClose={() => setAddressMode(null)}
          onSelect={address => setSelectedAddress(address)}
        />
      )}
    </View>
  );
}

export default function App(props: MarketplaceAppProps) {
  return <AppContent {...props} />;
}
