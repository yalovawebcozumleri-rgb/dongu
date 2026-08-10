import React, { useRef, useState } from 'react';
import { ActivityIndicator, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { C, s } from '../../styles';
import { ApiError, apiRequest, QuotaRewardOffer } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import { googleAds, initializeGoogleAds, rewardedUnitId } from './googleMobileAds';

export type RewardOffer = QuotaRewardOffer;

type Challenge = { data: {
  token: string;
  offer: RewardOffer;
  testMode: boolean;
  clientCompletionAllowed: boolean;
  adMobAndroidUnitId: string | null;
  adMobIosUnitId: string | null;
} };

type Props = {
  offer: RewardOffer;
  token: string;
  userId: string;
  onRewarded?: () => void | Promise<void>;
  compact?: boolean;
};

const formatDate = (value: string | null) => value
  ? new Date(value).toLocaleString('tr-TR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
  : null;

export default function RewardedUsageRightButton({ offer, token, userId, onRewarded, compact = false }: Props) {
  const { showNotice } = useNotice();
  const [busy, setBusy] = useState(false);
  const earnedRef = useRef(false);

  const waitForVerification = async (initialBonus: number) => {
    for (let attempt = 0; attempt < 6; attempt += 1) {
      await new Promise(resolve => setTimeout(resolve, 1500));
      try {
        const response = await apiRequest<{ data: RewardOffer | null }>(`/rewarded-rights/${offer.rewardKey}/status`, { token, retry: false });
        if (response.data && response.data.activeBonus > initialBonus) {
          await onRewarded?.();
          showNotice({ tone: 'success', title: 'Ek hakkın tanımlandı', message: `+${offer.amount} ${offer.unit} hesabına eklendi.` });
          return;
        }
      } catch {
        // SSV birkaç saniye gecikebilir; kısa süre boyunca yeniden kontrol edilir.
      }
    }
    showNotice({ tone: 'info', title: 'Ödül doğrulanıyor', message: 'Google doğrulaması tamamlandığında ek hakkın otomatik olarak hesabına yansıyacak.' });
  };

  const start = async () => {
    const module = googleAds();
    if (!module) {
      showNotice({ tone: 'info', title: 'Development build gerekli', message: 'Ödüllü reklamlar Expo Go’da çalışmaz. Kapalı test veya development sürümünde kullanılabilir.' });
      return;
    }
    setBusy(true);
    try {
      const challenge = await apiRequest<Challenge>(`/rewarded-rights/${offer.rewardKey}/challenge`, { method: 'POST', token, body: { platform: Platform.OS } });
      const remoteUnitId = Platform.OS === 'ios' ? challenge.data.adMobIosUnitId : challenge.data.adMobAndroidUnitId;
      const unitId = rewardedUnitId(remoteUnitId);
      if (!unitId) throw new Error('Reklam birimi bulunamadı');
      const adsReady = await initializeGoogleAds(unitId, challenge.data.testMode);
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
          if (challenge.data.clientCompletionAllowed) {
            await apiRequest(`/rewarded-rights/${offer.rewardKey}/complete`, { method: 'POST', token, body: { token: challenge.data.token } });
            await onRewarded?.();
            showNotice({ tone: 'success', title: 'Ek hakkın tanımlandı', message: `+${offer.amount} ${offer.unit} hesabına eklendi.` });
          } else {
            await waitForVerification(challenge.data.offer.activeBonus);
          }
        }),
        ad.addAdEventListener(module.AdEventType.CLOSED, () => {
          clear();
          setBusy(false);
          if (!earnedRef.current) showNotice({ tone: 'info', title: 'Reklam tamamlanmadı', message: 'Ek hak tanımlanmadı. İstersen yeniden deneyebilirsin.' });
        }),
        ad.addAdEventListener(module.AdEventType.ERROR, () => {
          clear();
          setBusy(false);
          showNotice({ tone: 'error', title: 'Reklam hazırlanamadı', message: 'Şu anda uygun reklam bulunamadı. Biraz sonra yeniden deneyebilirsin.' });
        }),
      );
      earnedRef.current = false;
      ad.load();
    } catch (error) {
      setBusy(false);
      showNotice({ tone: 'error', title: 'Ek hak alınamadı', message: error instanceof ApiError ? error.message : 'Reklam servisine ulaşılamadı.' });
    }
  };

  if (!offer.available) {
    const next = formatDate(offer.nextAvailableAt);
    return <View style={local.unavailable}><Text style={local.unavailableText}>{next ? `Yeni reklam hakkın ${next} tarihinde açılır.` : 'Bu hak için günlük reklam sınırına ulaştın.'}</Text></View>;
  }

  return <Pressable disabled={busy} onPress={() => void start()} style={({ pressed }) => [local.button, compact && local.compact, busy && local.disabled, pressed && s.pressed]}>
    {busy ? <ActivityIndicator color={C.dark} /> : <Text style={local.buttonText}>Reklam izle · +{offer.amount} {offer.unit}</Text>}
  </Pressable>;
}

const local = StyleSheet.create({
  button: { minHeight: 48, borderRadius: 15, marginTop: 11, paddingHorizontal: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: C.lime },
  compact: { minHeight: 42, marginTop: 8 },
  disabled: { opacity: 0.6 },
  buttonText: { color: C.dark, fontSize: 12, fontWeight: '900', textAlign: 'center' },
  unavailable: { marginTop: 9, borderRadius: 12, backgroundColor: '#F4F1EA', padding: 10 },
  unavailableText: { color: '#7B6540', fontSize: 11, lineHeight: 17, fontWeight: '700', textAlign: 'center' },
});