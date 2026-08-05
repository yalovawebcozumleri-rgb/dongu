import React, { useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text } from 'react-native';
import { Listing } from '../../marketplace';
import { C, s } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import { googleAds, initializeGoogleAds, rewardedUnitId } from './googleMobileAds';

type Challenge = { data: { token: string; clientCompletionAllowed: boolean } };

export default function RewardedListingBoostButton({ listing, token, userId, onBoosted }: {
  listing: Listing; token: string; userId: string; onBoosted: (listing: Listing) => void;
}) {
  const { showNotice } = useNotice();
  const [busy, setBusy] = useState(false);
  const earnedRef = useRef(false);

  const start = async () => {
    const module = googleAds();
    const unitId = rewardedUnitId();
    if (!module || !unitId) {
      showNotice({ tone: 'info', title: 'Development build gerekli', message: 'Ödüllü reklamlar Expo Go’da çalışmaz. Development build ile denediğinde Google test reklamı açılacak.' });
      return;
    }
    setBusy(true);
    try {
      const challenge = await apiRequest<Challenge>(`/listings/${listing.id}/rewarded-boost/challenge`, { method: 'POST', token });
      const adsReady = await initializeGoogleAds();
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
            const response = await apiRequest<{ data: Listing }>(`/listings/${listing.id}/rewarded-boost/complete`, { method: 'POST', token, body: { token: challenge.data.token } });
            onBoosted(response.data);
            showNotice({ tone: 'success', title: 'İlanın öne çıkarıldı', message: 'İlanın 24 saat boyunca sonuçlarda daha görünür olacak.' });
          } else {
            showNotice({ tone: 'info', title: 'Ödül doğrulanıyor', message: 'Google doğrulaması tamamlandığında ilanının 24 saatlik öne çıkarılması otomatik başlayacak.' });
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

  if (listing.isBoosted) return <Text style={local.active}>✓ İlanın 24 saatlik öne çıkarma avantajına sahip</Text>;
  return <Pressable disabled={busy} onPress={() => void start()} style={({ pressed }) => [local.button, busy && local.disabled, pressed && s.pressed]}>
    {busy ? <ActivityIndicator color={C.dark} /> : <Text style={local.buttonText}>Reklam izle · 24 saat öne çıkar</Text>}
  </Pressable>;
}

const local = StyleSheet.create({
  button: { minHeight: 50, borderRadius: 16, marginTop: 12, backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 },
  disabled: { opacity: 0.65 }, buttonText: { color: C.dark, fontSize: 14, fontWeight: '900' },
  active: { color: C.green, fontSize: 13, lineHeight: 19, fontWeight: '900', textAlign: 'center', marginTop: 12 },
});
