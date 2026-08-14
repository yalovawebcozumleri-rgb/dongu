import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Platform, Pressable, StyleSheet, Text } from 'react-native';
import { Listing } from '../../marketplace';
import { C, s } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import { googleAds, initializeGoogleAds, rewardedUnitId } from './googleMobileAds';
const completeWithRetry = async <T,>(request: () => Promise<T>): Promise<T> => {
  let lastError: unknown;
  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      return await request();
    } catch (error) {
      lastError = error;
      const retryable = !(error instanceof ApiError) || error.status === 0 || error.status === 408 || error.status >= 500;
      if (!retryable || attempt === 2) throw error;
      await new Promise(resolve => setTimeout(resolve, 500 * (attempt + 1)));
    }
  }
  throw lastError;
};

type Challenge = { data: { token: string; testMode: boolean; boostHours: number; dailyLimit: number; adMobAndroidUnitId: string | null; adMobIosUnitId: string | null } };
type BoostStatus = { data: { isBoosted: boolean; boostedUntil: string | null; enabled: boolean; boostHours: number; dailyLimit: number } };

export default function RewardedListingBoostButton({ listing, token, userId, onBoosted }: {
  listing: Listing; token: string; userId: string; onBoosted: (listing: Listing) => void;
}) {
  const { showNotice } = useNotice();
  const [busy, setBusy] = useState(false);
  const [boostHours, setBoostHours] = useState(24);
  const [enabled, setEnabled] = useState(true);
  const platform = Platform.OS === 'ios' ? 'ios' : 'android';

  useEffect(() => {
    void apiRequest<BoostStatus>(`/listings/${listing.id}/rewarded-boost/status?platform=${platform}`, { token })
      .then(status => {
        setEnabled(status.data.enabled);
        setBoostHours(status.data.boostHours);
      }).catch(() => undefined);
  }, [listing.id, platform, token]);
  const earnedRef = useRef(false);


  const start = async () => {
    const module = googleAds();
    const fallbackUnitId = rewardedUnitId();
    if (!module || !fallbackUnitId) {
      showNotice({ tone: 'info', title: 'Development build gerekli', message: 'Ödüllü reklamlar Expo Go’da çalışmaz. Development build ile denediğinde Google test reklamı açılacak.' });
      return;
    }
    setBusy(true);
    try {
      const challenge = await apiRequest<Challenge>(`/listings/${listing.id}/rewarded-boost/challenge`, { method: 'POST', token, body: { platform } });
      let adsReady = false;
      setBoostHours(challenge.data.boostHours);
      const remoteUnitId = Platform.OS === 'ios' ? challenge.data.adMobIosUnitId : challenge.data.adMobAndroidUnitId;
      const unitId = rewardedUnitId(remoteUnitId);
      if (!unitId) throw new Error('Reklam birimi bulunamadı');
      adsReady = await initializeGoogleAds(unitId, challenge.data.testMode);
      if (!adsReady) throw new Error('Reklam izni alınamadı');
      const ad = module.RewardedAd.createForAdRequest(unitId, {
        requestNonPersonalizedAdsOnly: true,
        serverSideVerificationOptions: { userId, customData: challenge.data.token },
      });
      const cleanups: (() => void)[] = [];
      const clear = () => cleanups.splice(0).forEach(cleanup => cleanup());
      cleanups.push(
        ad.addAdEventListener(module.RewardedAdEventType.LOADED, () => void ad.show()),
        ad.addAdEventListener(module.RewardedAdEventType.EARNED_REWARD, async () => {
          earnedRef.current = true;
          try {
            const response = await completeWithRetry(() => apiRequest<{ data: Listing }>(`/listings/${listing.id}/rewarded-boost/complete`, { method: 'POST', token, body: { token: challenge.data.token } }));
            onBoosted(response.data);
            showNotice({ tone: 'success', title: 'İlanın öne çıkarıldı', message: `İlanın ${challenge.data.boostHours} saat boyunca sonuçlarda daha görünür olacak.` });
          } catch (error) {
            showNotice({ tone: 'error', title: 'Öne çıkarma hesabına işlenemedi', message: error instanceof ApiError ? error.message : 'Bağlantı kurulamadı. Lütfen yeniden dene.' });
          } finally {
            setBusy(false);
          }
        }),
        ad.addAdEventListener(module.AdEventType.CLOSED, () => {
          clear(); setBusy(false);
          if (!earnedRef.current) showNotice({ tone: 'info', title: 'Reklam tamamlanmadı', message: 'İlanın öne çıkarılmadı. İstersen yeniden deneyebilirsin.' });
        }),
        ad.addAdEventListener(module.AdEventType.ERROR, () => {
          clear(); setBusy(false);
          showNotice({ tone: 'error', title: 'Reklam hazırlanamadı', message: 'Şu anda uygun reklam bulunamadı. Biraz sonra yeniden deneyebilirsin.' });
        }),
      );
      earnedRef.current = false;
      ad.load();
    } catch (error) {
      setBusy(false);
      showNotice({ tone: 'error', title: 'Öne çıkarma başlatılamadı', message: error instanceof ApiError ? error.message : 'Reklam servisine ulaşılamadı.' });
    }
  };

  const boostedUntil = listing.boostedUntil ? Date.parse(listing.boostedUntil) : null;
  const boostActive = Boolean(listing.isBoosted && (!boostedUntil || boostedUntil > Date.now()));
  const remainingHours = boostedUntil ? Math.max(1, Math.ceil((boostedUntil - Date.now()) / 3_600_000)) : 24;
  if (!enabled) return null;
  if (boostActive) {
    return <Text style={local.active}>✓ Öne çıkarma aktif · {remainingHours} saat kaldı</Text>;
  }
  return <Pressable disabled={busy} onPress={() => void start()} style={({ pressed }) => [local.button, busy && local.disabled, pressed && s.pressed]}>
    {busy ? <ActivityIndicator color={C.dark} /> : <Text style={local.buttonText}>Reklam izle · {boostHours} saat öne çıkar</Text>}
  </Pressable>;
}

const local = StyleSheet.create({
  button: { minHeight: 50, borderRadius: 16, marginTop: 12, backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 },
  disabled: { opacity: 0.65 }, buttonText: { color: C.dark, fontSize: 14, fontWeight: '900' },
  active: { color: C.green, fontSize: 13, lineHeight: 19, fontWeight: '900', textAlign: 'center', marginTop: 12 },
});