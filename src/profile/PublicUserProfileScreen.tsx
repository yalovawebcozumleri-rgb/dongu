import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { C } from '../../styles';
import { Coordinates, Listing } from '../../marketplace';
import ListingCard from '../listings/ListingCard';
import { ApiError, apiRequest } from '../lib/api';
import { readStaleCache, writeStaleCache } from '../lib/staleCache';
import { useNotice } from '../notice/NoticeProvider';
import UserAvatar from './UserAvatar';

type Badge = { code: string; name: string; description: string; icon: string; awardedAt: string | null };
type Review = { id: number; rating: number; comment: string | null; reviewer: { id: number; name: string; avatarUrl?: string | null }; createdAt: string | null };
type NextBadge = { code: string; name: string; description: string; icon: string; current: number; target: number; unit: 'puan' | 'teslimat'; progress: number };
type PublicProfile = {
  id: number;
  name: string;
  avatarUrl: string | null;
  memberSince: string | null;
  isNewUser: boolean;
  isOwnProfile: boolean;
  blockedByMe: boolean;
  rating: { average: number | null; count: number };
  completedDeliveries: number;
  cycle: { points: number; verifiedDeliveries: number };
  badges: Badge[];
  nextBadge: NextBadge | null;
  activeListings: Listing[];
};
type ReviewResponse = { data: Review[]; meta: { current_page: number; last_page: number; total: number } };

const PROFILE_CACHE_PREFIX = '@dongu/public-profile/v2/';
const REVIEW_CACHE_PREFIX = '@dongu/public-profile-reviews/v2/';
const PROFILE_CACHE_MAX_AGE = 24 * 60 * 60 * 1000;

const reportReasons = [
  ['fake_profile', 'Sahte profil'], ['harassment', 'Taciz veya zorbalık'],
  ['fraud', 'Dolandırıcılık şüphesi'], ['spam', 'Spam'],
  ['inappropriate', 'Uygunsuz profil'], ['other', 'Diğer'],
] as const;

function ProfileSkeleton({ back }: { back: () => void }) {
  return <View style={x.screen}>
    <View style={x.topBar}><Pressable accessibilityRole="button" accessibilityLabel="Geri dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable><Text style={x.topTitle}>Kullanıcı profili</Text><View style={x.topSpacer} /></View>
    <View accessibilityLabel="Kullanıcı profili hazırlanıyor" style={x.skeletonContent}>
      <View style={x.skeletonHero}>
        <View style={x.skeletonIdentity}><View style={x.skeletonAvatar} /><View style={x.skeletonIdentityCopy}><View style={x.skeletonPill} /><View style={x.skeletonName} /><View style={x.skeletonMeta} /></View></View>
        <View style={x.skeletonStats}><View style={x.skeletonStat} /><View style={x.skeletonStat} /><View style={x.skeletonStat} /></View>
      </View>
      <View style={x.skeletonSummary}><View style={x.skeletonSummaryItem} /><View style={x.skeletonSummaryItem} /></View>
      <View style={x.skeletonHeading} />
      <View style={x.skeletonCard} />
      <View style={x.skeletonHeading} />
      <View style={x.skeletonCardTall} />
    </View>
  </View>;
}

export default function PublicUserProfileScreen({ userId, token, currentUserId, center, favoritePendingIds, toggleFavorite, bottomInset, back, openListing, editOwnProfile, requireAuth, onBlockChanged, onBlocked }: {
  userId: number;
  token?: string | null;
  currentUserId: string;
  center: Coordinates | null;
  favoritePendingIds: Set<number>;
  toggleFavorite: (listing: Listing) => Promise<boolean>;
  bottomInset: number;
  back: () => void;
  openListing: (listing: Listing) => void;
  editOwnProfile: () => void;
  requireAuth?: () => void;
  onBlockChanged: () => Promise<void>;
  onBlocked: () => void;
}) {
  const { showNotice, confirmNotice } = useNotice();
  const [profile, setProfile] = useState<PublicProfile | null>(null);
  const [reviews, setReviews] = useState<Review[]>([]);
  const [reviewPage, setReviewPage] = useState(1);
  const [reviewLastPage, setReviewLastPage] = useState(1);
  const [reviewTotal, setReviewTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [reviewsLoading, setReviewsLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [actionPending, setActionPending] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);
  const [reportDetails, setReportDetails] = useState('');
  const [reportReason, setReportReason] = useState<string | null>(null);
  const requestSequence = useRef(0);

  const load = useCallback(async () => {
    const sequence = ++requestSequence.current;
    const viewerKey = token ? currentUserId || 'member' : 'guest';
    const profileCacheKey = `${PROFILE_CACHE_PREFIX}${viewerKey}/${userId}`;
    const reviewCacheKey = `${REVIEW_CACHE_PREFIX}${userId}`;
    setLoading(true);
    setReviewsLoading(true);
    setProfile(null);
    setReviews([]);

    const [cachedProfile, cachedReviews] = await Promise.all([
      readStaleCache<PublicProfile>(profileCacheKey, PROFILE_CACHE_MAX_AGE),
      readStaleCache<ReviewResponse>(reviewCacheKey, PROFILE_CACHE_MAX_AGE),
    ]);
    if (sequence !== requestSequence.current) return;

    if (cachedProfile) {
      setProfile(cachedProfile);
      setLoading(false);
    }
    if (cachedReviews) {
      setReviews(cachedReviews.data);
      setReviewPage(cachedReviews.meta.current_page);
      setReviewLastPage(cachedReviews.meta.last_page);
      setReviewTotal(cachedReviews.meta.total);
      setReviewsLoading(false);
    }

    const [profileResult, reviewResult] = await Promise.allSettled([
      apiRequest<{ data: PublicProfile }>(`/users/${userId}/public-profile`, { token }),
      apiRequest<ReviewResponse>(`/users/${userId}/reviews?per_page=10&page=1`, { token }),
    ]);
    if (sequence !== requestSequence.current) return;

    if (profileResult.status === 'fulfilled') {
      setProfile(profileResult.value.data);
      void writeStaleCache(profileCacheKey, profileResult.value.data);
    } else if (!cachedProfile) {
      const error = profileResult.reason;
      showNotice({ tone: 'error', title: 'Profil açılamadı', message: error instanceof ApiError && error.status === 404 ? 'Bu profile erişilemiyor.' : error instanceof ApiError ? error.message : 'Profil servisine ulaşılamadı.' });
    }
    setLoading(false);

    if (reviewResult.status === 'fulfilled') {
      setReviews(reviewResult.value.data);
      setReviewPage(reviewResult.value.meta.current_page);
      setReviewLastPage(reviewResult.value.meta.last_page);
      setReviewTotal(reviewResult.value.meta.total);
      void writeStaleCache(reviewCacheKey, reviewResult.value);
    } else if (!cachedReviews) {
      const error = reviewResult.reason;
      showNotice({ tone: 'error', title: 'Değerlendirmeler alınamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    }
    setReviewsLoading(false);
  }, [currentUserId, showNotice, token, userId]);

  useEffect(() => {
    void load();
    return () => { requestSequence.current += 1; };
  }, [load]);

  const loadMoreReviews = async () => {
    if (loadingMore || reviewPage >= reviewLastPage) return;
    setLoadingMore(true);
    try {
      const response = await apiRequest<ReviewResponse>(`/users/${userId}/reviews?per_page=10&page=${reviewPage + 1}`, { token });
      setReviews(current => [...current, ...response.data.filter(item => !current.some(existing => existing.id === item.id))]);
      setReviewPage(response.meta.current_page);
      setReviewLastPage(response.meta.last_page);
    } catch (error) {
      showNotice({ tone: 'error', title: 'Değerlendirmeler alınamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    } finally { setLoadingMore(false); }
  };

  const toggleBlock = async () => {
    if (!token) return requireAuth?.();
    if (!profile || actionPending) return;
    const blocking = !profile.blockedByMe;
    if (blocking && !await confirmNotice({ tone: 'warning', title: `${profile.name} engellensin mi?`, message: 'Aranızdaki aktif görüşmeler kapatılır ve birbirinizin profiline erişemezsiniz.', primaryLabel: 'Engelle' })) return;
    setActionPending(true);
    try {
      await apiRequest(`/users/${userId}/block`, { method: blocking ? 'POST' : 'DELETE', token });
      await onBlockChanged();
      if (blocking) {
        showNotice({ tone: 'success', title: 'Kullanıcı engellendi', message: 'Bu kullanıcıyla iletişim kapatıldı.' });
        onBlocked();
      } else {
        setProfile(current => current ? { ...current, blockedByMe: false } : current);
      }
    } catch (error) {
      showNotice({ tone: 'error', title: 'İşlem tamamlanamadı', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    } finally { setActionPending(false); }
  };

  const closeReport = () => {
    if (actionPending) return;
    setReportOpen(false);
    setReportReason(null);
    setReportDetails('');
  };

  const report = async () => {
    if (!token) { closeReport(); requireAuth?.(); return; }
    if (actionPending || !reportReason) return;
    setActionPending(true);
    try {
      const response = await apiRequest<{ message: string }>(`/users/${userId}/report`, { method: 'POST', token, body: { reason: reportReason, details: reportDetails.trim() || null } });
      setReportOpen(false);
      setReportReason(null);
      setReportDetails('');
      showNotice({ tone: 'success', title: 'Bildirimin alındı', message: response.message });
    } catch (error) {
      showNotice({ tone: 'error', title: 'Kullanıcı bildirilemedi', message: error instanceof ApiError ? error.message : 'Sunucuya ulaşılamadı.' });
    } finally { setActionPending(false); }
  };

  if (loading) return <ProfileSkeleton back={back} />;
  if (!profile) return <View style={x.screen}><View style={x.topBar}><Pressable accessibilityRole="button" accessibilityLabel="Geri dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable><Text style={x.topTitle}>Kullanıcı profili</Text><View style={x.topSpacer} /></View><View style={x.center}><Text style={x.emptyTitle}>Profil kullanılamıyor</Text><Pressable onPress={back} style={x.retry}><Text style={x.retryText}>Geri dön</Text></Pressable></View></View>;

  const memberYear = profile.memberSince ? new Date(profile.memberSince).getFullYear() : null;
  const ratingValue = profile.rating.count > 0 && profile.rating.average !== null ? profile.rating.average.toFixed(1).replace('.', ',') : 'Yeni';
  return (
    <View style={x.screen}>
      <View style={x.topBar}><Pressable accessibilityRole="button" accessibilityLabel="Geri dön" onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable><Text style={x.topTitle}>Kullanıcı profili</Text><View style={x.topSpacer} /></View>
      <ScrollView contentContainerStyle={[x.content, { paddingBottom: 32 + bottomInset }]} showsVerticalScrollIndicator={false}>
        <View style={x.hero}>
          <View style={x.heroGlowOne} /><View style={x.heroGlowTwo} />
          <View style={x.heroIdentity}>
            <UserAvatar uri={profile.avatarUrl} name={profile.name} size={78} style={x.avatar} />
            <View style={x.identityCopy}>
              <View style={x.memberPill}><Text style={x.memberPillText}>{profile.isNewUser ? 'YENİ KULLANICI' : 'DÖNGÜ ÜYESİ'}</Text></View>
              <Text style={x.name}>{profile.name}</Text>
              <Text style={x.member}>{memberYear ? `${memberYear} yılından beri Döngü’de` : 'Doğaya katkı sağlayan kullanıcı'}</Text>
            </View>
          </View>
          <View style={x.stats}>
            <View style={x.stat}><Text style={x.statSymbol}>★</Text><Text style={x.statValue}>{ratingValue}</Text><Text style={x.statLabel}>{profile.rating.count > 0 ? `${profile.rating.count} değerlendirme` : 'Değerlendirme yok'}</Text></View>
            <View style={x.divider} />
            <View style={x.stat}><Text style={x.statSymbol}>✓</Text><Text style={x.statValue}>{profile.completedDeliveries}</Text><Text style={x.statLabel}>Tamamlanan teslimat</Text></View>
            <View style={x.divider} />
            <View style={x.stat}><Text style={x.statSymbol}>♻</Text><Text style={x.statValue}>{profile.cycle.points.toLocaleString('tr-TR')}</Text><Text style={x.statLabel}>Döngü puanı</Text></View>
          </View>
          {profile.isOwnProfile && <Pressable onPress={editOwnProfile} style={x.outlineButton}><Text style={x.outlineButtonText}>Profili düzenle</Text></Pressable>}
        </View>

        <View style={x.impactDetail}>
          <View><Text style={x.impactLabel}>PUANLANAN TESLİMAT</Text><Text style={x.impactValue}>{profile.cycle.verifiedDeliveries}</Text></View>
          <View style={x.impactRight}><Text style={x.impactLabel}>AKTİF İLAN</Text><Text style={x.impactValue}>{profile.activeListings.length}</Text></View>
        </View>

        <View style={x.sectionHeading}><View><Text style={x.sectionEyebrow}>BAŞARILAR</Text><Text style={x.sectionTitle}>Başarı rozetleri</Text></View><Text style={x.sectionCount}>{profile.badges.length}</Text></View>
        {profile.badges.length ? <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={x.badgeRow}>{profile.badges.map(badge => <View key={badge.code} style={x.badge}><Text style={x.badgeIcon}>{badge.icon || '♻'}</Text><Text style={x.badgeName}>{badge.name}</Text><Text style={x.badgeDescription}>{badge.description}</Text></View>)}</ScrollView> : <View style={x.emptyCard}><Text style={x.emptyCopy}>Henüz kazanılmış rozet yok.</Text></View>}
        {profile.isOwnProfile && (profile.nextBadge ? <View style={x.nextBadge}><View style={x.nextTop}><View style={x.nextIcon}><Text style={x.nextIconText}>{profile.nextBadge.icon}</Text></View><View style={x.nextCopy}><Text style={x.nextEyebrow}>SIRADAKİ ROZET</Text><Text style={x.nextName}>{profile.nextBadge.name}</Text><Text style={x.nextDescription}>{profile.nextBadge.description}</Text></View></View><View style={x.progressTrack}><View style={[x.progressFill, { width: `${profile.nextBadge.progress}%` }]} /></View><View style={x.progressMeta}><Text style={x.progressText}>{profile.nextBadge.current.toLocaleString('tr-TR')} / {profile.nextBadge.target.toLocaleString('tr-TR')} {profile.nextBadge.unit}</Text><Text style={x.progressPercent}>%{profile.nextBadge.progress}</Text></View></View> : <View style={x.allBadges}><Text style={x.allBadgesTitle}>Tüm rozetleri kazandın</Text><Text style={x.allBadgesText}>Döngüye sağladığın katkı için teşekkür ederiz.</Text></View>)}

        <View style={x.sectionHeading}><View><Text style={x.sectionEyebrow}>PAYLAŞIMA HAZIR</Text><Text style={x.sectionTitle}>Aktif ilanlar</Text></View><Text style={x.sectionCount}>{profile.activeListings.length}</Text></View>
        {!!profile.activeListings.length && <View style={x.fullWidthListings}>{profile.activeListings.map(listing => (
          <ListingCard
            key={listing.id}
            item={listing}
            center={center}
            open={() => openListing(listing)}
            isOwn={String(listing.sellerId) === currentUserId}
            favoritePending={favoritePendingIds.has(listing.id)}
            toggleFavorite={() => void (async () => {
              const nextValue = await toggleFavorite(listing);
              setProfile(current => current ? { ...current, activeListings: current.activeListings.map(item => item.id === listing.id ? { ...item, isFavorited: nextValue } : item) } : current);
            })()}
          />
        ))}</View>}
        {!profile.activeListings.length && <View style={x.emptyCard}><Text style={x.emptyCopy}>Şu anda aktif ilanı yok.</Text></View>}

        <View style={x.sectionHeading}><View><Text style={x.sectionEyebrow}>TOPLULUK DENEYİMİ</Text><Text style={x.sectionTitle}>Değerlendirmeler</Text></View><Text style={x.sectionCount}>{reviewTotal}</Text></View>
        {reviewsLoading && <View accessibilityLabel="Değerlendirmeler hazırlanıyor" style={x.reviewSkeleton}><View style={x.reviewSkeletonTop}><View style={x.reviewSkeletonAvatar} /><View style={x.reviewSkeletonName} /><View style={x.reviewSkeletonStars} /></View><View style={x.reviewSkeletonLine} /><View style={x.reviewSkeletonLineShort} /></View>}
        {reviews.map(review => <View key={review.id} style={x.review}><View style={x.reviewTop}><View style={x.reviewerIdentity}><UserAvatar uri={review.reviewer.avatarUrl} name={review.reviewer.name} size={34} /><Text style={x.reviewer}>{review.reviewer.name}</Text></View><Text style={x.stars}>{'★'.repeat(review.rating)}{'☆'.repeat(5 - review.rating)}</Text></View>{review.comment ? <Text style={x.reviewComment}>{review.comment}</Text> : <Text style={x.reviewEmpty}>Yalnızca puan verdi.</Text>}<Text style={x.reviewDate}>{review.createdAt ? new Date(review.createdAt).toLocaleDateString('tr-TR') : ''}</Text></View>)}
        {!reviewsLoading && !reviews.length && <View style={x.emptyCard}><Text style={x.emptyCopy}>Henüz değerlendirilmedi.</Text></View>}
        {reviewPage < reviewLastPage && <Pressable disabled={loadingMore} onPress={() => void loadMoreReviews()} style={x.moreButton}>{loadingMore ? <ActivityIndicator color={C.green} /> : <Text style={x.moreText}>Daha fazla değerlendirme</Text>}</Pressable>}

        {!profile.isOwnProfile && (
          <View style={x.safetyCard}>
            <View style={x.safetyHeader}>
              <View style={x.safetyHeaderIcon}><Text style={x.safetyHeaderIconText}>✓</Text></View>
              <View style={x.safetyHeaderCopy}>
                <Text style={x.safetyEyebrow}>GÜVENLİK VE DESTEK</Text>
                <Text style={x.safetyTitle}>Bu profille ilgili bir sorun mu var?</Text>
                <Text style={x.safetyDescription}>Bildirimlerin gizli tutulur. Engelleme kararını istediğin zaman değiştirebilirsin.</Text>
              </View>
            </View>

            <View style={x.safetyActions}>
              <Pressable
                accessibilityRole="button"
                accessibilityLabel={`${profile.name} adlı kullanıcıyı bildir`}
                onPress={() => token ? setReportOpen(true) : requireAuth?.()}
                style={({ pressed }) => [x.safetyAction, pressed && x.safetyActionPressed]}
              >
                <View style={[x.safetyActionIcon, x.reportIcon]}><Text style={x.reportIconText}>!</Text></View>
                <View style={x.safetyActionCopy}>
                  <Text style={x.reportText}>Kullanıcıyı bildir</Text>
                  <Text style={x.safetyActionDescription}>Şüpheli veya uygunsuz davranışı güvenlik ekibine ilet.</Text>
                </View>
                <Text style={x.safetyChevron}>›</Text>
              </Pressable>

              <View style={x.safetyDivider} />

              <Pressable
                accessibilityRole="button"
                accessibilityLabel={profile.blockedByMe ? `${profile.name} adlı kullanıcının engelini kaldır` : `${profile.name} adlı kullanıcıyı engelle`}
                disabled={actionPending}
                onPress={() => void toggleBlock()}
                style={({ pressed }) => [x.safetyAction, pressed && x.safetyActionPressed, actionPending && x.safetyActionDisabled]}
              >
                <View style={[x.safetyActionIcon, profile.blockedByMe ? x.unblockIcon : x.blockIcon]}><Text style={profile.blockedByMe ? x.unblockIconText : x.blockIconText}>{profile.blockedByMe ? '✓' : '—'}</Text></View>
                <View style={x.safetyActionCopy}>
                  <Text style={profile.blockedByMe ? x.unblockText : x.blockText}>{profile.blockedByMe ? 'Engeli kaldır' : 'Kullanıcıyı engelle'}</Text>
                  <Text style={x.safetyActionDescription}>{profile.blockedByMe ? 'Bu kullanıcı profilini yeniden görebilir ve iletişim kurabilir.' : 'Bu kullanıcı seninle iletişim kuramaz ve profiline erişemez.'}</Text>
                </View>
                {actionPending ? <ActivityIndicator size="small" color={C.green} /> : <Text style={x.safetyChevron}>›</Text>}
              </Pressable>
            </View>
          </View>
        )}
      </ScrollView>

      <Modal transparent visible={reportOpen} animationType="fade" onRequestClose={closeReport}>
        <SafeAreaProvider style={x.modalProvider}>
          <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={x.modalBackdrop}>
            <Pressable accessibilityRole="button" accessibilityLabel="Bildirimi kapat" style={StyleSheet.absoluteFill} onPress={closeReport} />
            <SafeAreaView edges={['bottom']} style={x.sheet}>
            <Text style={x.sheetTitle}>Bu kullanıcıyı neden bildiriyorsun?</Text>
            <Text style={x.sheetCopy}>Bir neden seç. Bildirim yalnızca güvenlik ekibi tarafından görülür ve seçimin onay vermeden gönderilmez.</Text>
            <ScrollView style={x.reportScroll} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
              <View style={x.reasonList}>
                {reportReasons.map(([value, label]) => {
                  const selected = reportReason === value;
                  return <Pressable key={value} accessibilityRole="radio" accessibilityState={{ selected }} disabled={actionPending} onPress={() => setReportReason(value)} style={[x.reason, selected && x.reasonSelected]}><View style={[x.reasonRadio, selected && x.reasonRadioSelected]}>{selected && <View style={x.reasonRadioDot} />}</View><Text style={[x.reasonText, selected && x.reasonTextSelected]}>{label}</Text></Pressable>;
                })}
              </View>
              <TextInput value={reportDetails} onChangeText={setReportDetails} maxLength={500} multiline placeholder="Açıklama (isteğe bağlı)" placeholderTextColor="#87948C" style={x.input} />
            </ScrollView>
            <View style={x.reportActions}>
              <Pressable disabled={actionPending} onPress={closeReport} style={x.cancel}><Text style={x.cancelText}>Vazgeç</Text></Pressable>
              <Pressable disabled={actionPending || !reportReason} onPress={() => void report()} style={[x.reportSubmit, (actionPending || !reportReason) && x.reportSubmitDisabled]}>{actionPending ? <ActivityIndicator size="small" color="white" /> : <Text style={x.reportSubmitText}>Bildirimi gönder</Text>}</Pressable>
            </View>
            </SafeAreaView>
          </KeyboardAvoidingView>
        </SafeAreaProvider>
      </Modal>
    </View>
  );
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, backgroundColor: C.bg }, loadingText: { color: '#65736A', fontWeight: '700' }, emptyTitle: { color: '#173E2E', fontSize: 19, fontWeight: '900' }, retry: { backgroundColor: '#173E2E', borderRadius: 14, paddingHorizontal: 20, paddingVertical: 12 }, retryText: { color: 'white', fontWeight: '900' },
  topBar: { minHeight: 58, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: '#E4E7E1', backgroundColor: C.bg },
  skeletonContent: { padding: 18 }, skeletonHero: { borderRadius: 26, backgroundColor: '#DCE7DF', padding: 18 }, skeletonIdentity: { flexDirection: 'row', alignItems: 'center' }, skeletonAvatar: { width: 78, height: 78, borderRadius: 39, backgroundColor: '#C8D7CD' }, skeletonIdentityCopy: { flex: 1, marginLeft: 14, gap: 9 }, skeletonPill: { width: 88, height: 20, borderRadius: 10, backgroundColor: '#C4D4C9' }, skeletonName: { width: '72%', height: 23, borderRadius: 8, backgroundColor: '#BCCFC2' }, skeletonMeta: { width: '88%', height: 12, borderRadius: 6, backgroundColor: '#C8D7CD' }, skeletonStats: { flexDirection: 'row', gap: 12, marginTop: 22, paddingTop: 17, borderTopWidth: 1, borderTopColor: '#C6D5CB' }, skeletonStat: { flex: 1, height: 43, borderRadius: 12, backgroundColor: '#C8D7CD' }, skeletonSummary: { marginTop: 9, padding: 15, borderRadius: 17, backgroundColor: C.white, flexDirection: 'row', justifyContent: 'space-between' }, skeletonSummaryItem: { width: '37%', height: 31, borderRadius: 9, backgroundColor: '#E8ECE8' }, skeletonHeading: { width: '45%', height: 22, borderRadius: 8, backgroundColor: '#DDE5DF', marginTop: 24, marginBottom: 10 }, skeletonCard: { height: 96, borderRadius: 18, backgroundColor: C.white }, skeletonCardTall: { height: 154, borderRadius: 22, backgroundColor: C.white },
  back: { width: 42, height: 42, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: 'white' }, backText: { color: '#173E2E', fontSize: 34, lineHeight: 37 }, topTitle: { color: '#173E2E', fontSize: 17, fontWeight: '900' }, topSpacer: { width: 42 }, content: { padding: 18 },
  hero: { position: 'relative', overflow: 'hidden', borderRadius: 26, backgroundColor: C.dark, padding: 18 }, heroGlowOne: { position: 'absolute', width: 180, height: 180, borderRadius: 90, backgroundColor: '#275F48', right: -70, top: -85, opacity: .72 }, heroGlowTwo: { position: 'absolute', width: 110, height: 110, borderRadius: 55, backgroundColor: '#315F42', left: -55, bottom: -60, opacity: .55 }, heroIdentity: { flexDirection: 'row', alignItems: 'center' }, identityCopy: { flex: 1, marginLeft: 14 }, memberPill: { alignSelf: 'flex-start', borderRadius: 20, backgroundColor: '#315F49', paddingHorizontal: 9, paddingVertical: 5 }, memberPillText: { color: C.lime, fontSize: 10, letterSpacing: .8, fontWeight: '900' }, avatar: { borderWidth: 3, borderColor: '#5D8A72' }, name: { marginTop: 8, color: C.white, fontSize: 23, fontWeight: '900' }, member: { marginTop: 4, color: '#B9CEC1', fontSize: 12, lineHeight: 18, fontWeight: '700' }, outlineButton: { alignSelf: 'center', marginTop: 18, borderWidth: 1, borderColor: '#6E9A83', backgroundColor: '#244C39', borderRadius: 13, paddingHorizontal: 16, paddingVertical: 10 }, outlineButtonText: { color: C.white, fontWeight: '900' },
  stats: { flexDirection: 'row', alignItems: 'center', marginTop: 19, paddingTop: 16, borderTopWidth: 1, borderTopColor: '#416D57' }, stat: { flex: 1, alignItems: 'center', paddingHorizontal: 4 }, statSymbol: { color: C.lime, fontSize: 16, fontWeight: '900' }, statValue: { color: C.white, fontSize: 19, fontWeight: '900', marginTop: 3 }, statLabel: { marginTop: 3, color: '#B9CEC1', fontSize: 11, lineHeight: 16, textAlign: 'center', fontWeight: '700' }, divider: { width: 1, height: 48, backgroundColor: '#416D57' }, impactDetail: { padding: 15, marginTop: 9, borderRadius: 17, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, flexDirection: 'row', justifyContent: 'space-between' }, impactRight: { alignItems: 'flex-end' }, impactLabel: { color: C.muted, fontSize: 10, letterSpacing: .7, fontWeight: '900' }, impactValue: { color: C.ink, fontSize: 16, fontWeight: '900', marginTop: 3 }, sectionHeading: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 24, marginBottom: 10 }, sectionEyebrow: { color: C.green, fontSize: 10, letterSpacing: 1.1, fontWeight: '900' }, sectionTitle: { color: '#143C2B', fontSize: 18, fontWeight: '900', marginTop: 2 }, sectionCount: { minWidth: 32, height: 32, color: '#17613F', backgroundColor: '#E0F1E6', borderRadius: 16, paddingHorizontal: 10, textAlign: 'center', textAlignVertical: 'center', fontWeight: '900' },
  badgeRow: { gap: 10, paddingRight: 18 }, nextBadge: { padding: 15, borderRadius: 19, backgroundColor: '#EDF5E8', borderWidth: 1, borderColor: '#C9DDCD', marginTop: 10 }, nextTop: { flexDirection: 'row' }, nextIcon: { width: 46, height: 46, borderRadius: 15, backgroundColor: C.white, alignItems: 'center', justifyContent: 'center' }, nextIconText: { fontSize: 24 }, nextCopy: { flex: 1, marginLeft: 11 }, nextEyebrow: { color: C.green, fontSize: 10, letterSpacing: .8, fontWeight: '900' }, nextName: { color: C.ink, fontSize: 14, fontWeight: '900', marginTop: 2 }, nextDescription: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 3 }, progressTrack: { height: 8, borderRadius: 4, backgroundColor: '#D5E3D2', overflow: 'hidden', marginTop: 13 }, progressFill: { height: 8, borderRadius: 4, backgroundColor: C.green }, progressMeta: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 6 }, progressText: { color: C.muted, fontSize: 11, fontWeight: '800' }, progressPercent: { color: C.green, fontSize: 11, fontWeight: '900' }, allBadges: { padding: 15, borderRadius: 18, backgroundColor: C.soft, marginTop: 10, alignItems: 'center' }, allBadgesTitle: { color: C.dark, fontSize: 13, fontWeight: '900' }, allBadgesText: { color: C.muted, fontSize: 12, marginTop: 3 }, badge: { width: 170, borderRadius: 18, backgroundColor: '#E4F2E8', padding: 14 }, badgeIcon: { fontSize: 24 }, badgeName: { color: '#17412F', fontWeight: '900', marginTop: 6 }, badgeDescription: { color: '#5D7166', fontSize: 12, lineHeight: 18, marginTop: 4 }, emptyCard: { borderRadius: 16, backgroundColor: 'white', padding: 18 }, emptyCopy: { color: '#748179', fontWeight: '700' },
  fullWidthListings: { marginHorizontal: -18 },
  listingCard: { backgroundColor: 'white', borderRadius: 18, padding: 16, marginBottom: 10, borderWidth: 1, borderColor: '#E6E9E4' }, listingTop: { flexDirection: 'row', justifyContent: 'space-between', gap: 10 }, listingMaterials: { flex: 1, color: '#173E2E', fontWeight: '900' }, listingPrice: { color: '#137548', fontWeight: '900' }, listingMeta: { color: '#748179', fontSize: 12, marginTop: 7, fontWeight: '700' }, listingNote: { color: '#46564D', fontSize: 13, lineHeight: 18, marginTop: 8 },
  reviewSkeleton: { minHeight: 116, borderRadius: 18, backgroundColor: C.white, padding: 16 }, reviewSkeletonTop: { flexDirection: 'row', alignItems: 'center' }, reviewSkeletonAvatar: { width: 34, height: 34, borderRadius: 17, backgroundColor: '#E3E9E4' }, reviewSkeletonName: { width: 112, height: 13, borderRadius: 7, marginLeft: 9, backgroundColor: '#E3E9E4' }, reviewSkeletonStars: { width: 72, height: 12, borderRadius: 6, marginLeft: 'auto', backgroundColor: '#E9E4D8' }, reviewSkeletonLine: { width: '92%', height: 11, borderRadius: 6, marginTop: 15, backgroundColor: '#E7ECE8' }, reviewSkeletonLineShort: { width: '58%', height: 11, borderRadius: 6, marginTop: 8, backgroundColor: '#E7ECE8' }, review: { backgroundColor: 'white', borderRadius: 18, padding: 16, marginBottom: 10 }, reviewTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 }, reviewerIdentity: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 9 }, reviewer: { flex: 1, color: '#173E2E', fontWeight: '900' }, stars: { color: '#E59B24', letterSpacing: 1 }, reviewComment: { marginTop: 9, color: '#3E5046', lineHeight: 20 }, reviewEmpty: { marginTop: 9, color: '#8A958F', fontStyle: 'italic' }, reviewDate: { marginTop: 9, color: '#8A958F', fontSize: 12 }, moreButton: { alignItems: 'center', paddingVertical: 14 }, moreText: { color: '#17613F', fontWeight: '900' },
  safetyCard: { marginTop: 24, marginBottom: 6, overflow: 'hidden', borderRadius: 22, borderWidth: 1, borderColor: '#DCE5DF', backgroundColor: C.white }, safetyHeader: { flexDirection: 'row', alignItems: 'flex-start', padding: 16, backgroundColor: '#F2F8F4' }, safetyHeaderIcon: { width: 38, height: 38, borderRadius: 13, alignItems: 'center', justifyContent: 'center', backgroundColor: '#DCEFE3' }, safetyHeaderIconText: { color: '#17613F', fontSize: 18, fontWeight: '900' }, safetyHeaderCopy: { flex: 1, marginLeft: 12 }, safetyEyebrow: { color: '#287250', fontSize: 10, fontWeight: '900', letterSpacing: 1 }, safetyTitle: { marginTop: 3, color: '#173E2E', fontSize: 16, lineHeight: 21, fontWeight: '900' }, safetyDescription: { marginTop: 5, color: '#66766D', fontSize: 12, lineHeight: 18 }, safetyActions: { paddingHorizontal: 14 }, safetyAction: { minHeight: 76, flexDirection: 'row', alignItems: 'center', paddingVertical: 12 }, safetyActionPressed: { opacity: .64 }, safetyActionDisabled: { opacity: .55 }, safetyActionIcon: { width: 40, height: 40, borderRadius: 13, alignItems: 'center', justifyContent: 'center' }, reportIcon: { backgroundColor: '#FFF1DB' }, reportIconText: { color: '#A65A10', fontSize: 20, fontWeight: '900' }, blockIcon: { backgroundColor: '#FDE8E6' }, blockIconText: { color: '#B13D37', fontSize: 21, lineHeight: 22, fontWeight: '900' }, unblockIcon: { backgroundColor: '#DCEFE3' }, unblockIconText: { color: '#17613F', fontSize: 18, fontWeight: '900' }, safetyActionCopy: { flex: 1, marginLeft: 12, paddingRight: 8 }, safetyActionDescription: { marginTop: 3, color: '#718078', fontSize: 12, lineHeight: 17 }, safetyChevron: { color: '#8C9991', fontSize: 28, lineHeight: 29, fontWeight: '500' }, safetyDivider: { height: StyleSheet.hairlineWidth, marginLeft: 52, backgroundColor: '#E3E8E4' }, reportText: { color: '#8F4E12', fontSize: 14, fontWeight: '900' }, blockText: { color: '#A43832', fontSize: 14, fontWeight: '900' }, unblockText: { color: '#17613F', fontSize: 14, fontWeight: '900' }, bottomAction: { position: 'absolute', left: 0, right: 0, bottom: 0, paddingHorizontal: 18, paddingTop: 12, backgroundColor: 'white', borderTopWidth: 1, borderTopColor: '#E1E5E0' }, messageButton: { backgroundColor: '#17613F', borderRadius: 16, minHeight: 52, alignItems: 'center', justifyContent: 'center' }, messageButtonText: { color: 'white', fontSize: 16, fontWeight: '900' },
  modalProvider: { flex: 1 }, modalBackdrop: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(12,31,22,.42)' }, sheet: { maxHeight: '90%', backgroundColor: '#FDFDF9', borderTopLeftRadius: 28, borderTopRightRadius: 28, paddingHorizontal: 20, paddingTop: 20, paddingBottom: 16 }, reportScroll: { flexShrink: 1 }, sheetTitle: { color: '#173E2E', fontSize: 20, fontWeight: '900' }, sheetCopy: { color: '#68766E', marginTop: 6, marginBottom: 14, lineHeight: 20 }, reasonList: { gap: 8, marginBottom: 14 }, reason: { minHeight: 48, flexDirection: 'row', alignItems: 'center', gap: 11, borderWidth: 1, borderColor: '#D8DFDA', borderRadius: 13, paddingHorizontal: 13, backgroundColor: 'white' }, reasonSelected: { borderColor: '#2D7A50', backgroundColor: '#EDF7F0' }, reasonRadio: { width: 20, height: 20, borderRadius: 10, borderWidth: 1.5, borderColor: '#A6B1AA', alignItems: 'center', justifyContent: 'center' }, reasonRadioSelected: { borderColor: '#17613F' }, reasonRadioDot: { width: 10, height: 10, borderRadius: 5, backgroundColor: '#17613F' }, reasonText: { color: '#294C3C', fontWeight: '800' }, reasonTextSelected: { color: '#17613F' }, input: { minHeight: 74, borderWidth: 1, borderColor: '#D8DFDA', borderRadius: 14, padding: 12, textAlignVertical: 'top', backgroundColor: 'white', color: '#173E2E' }, reportActions: { flexDirection: 'row', gap: 10, marginTop: 16 }, cancel: { minHeight: 48, flex: 1, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#D5DDD7', borderRadius: 14, backgroundColor: 'white' }, cancelText: { color: '#5F6F66', fontWeight: '900' }, reportSubmit: { minHeight: 48, flex: 1.35, alignItems: 'center', justifyContent: 'center', borderRadius: 14, backgroundColor: '#17613F' }, reportSubmitDisabled: { opacity: 0.45 }, reportSubmitText: { color: 'white', fontSize: 14, fontWeight: '900' },
});
