import Constants from 'expo-constants';
import { Platform } from 'react-native';

type GoogleAdsModule = typeof import('react-native-google-mobile-ads');

let cachedModule: GoogleAdsModule | null | undefined;
let initialization: Promise<unknown> | null = null;

export function googleAds(): GoogleAdsModule | null {
  if (cachedModule !== undefined) return cachedModule;
  if (Constants.appOwnership === 'expo') {
    cachedModule = null;
    return cachedModule;
  }
  try {
    // Native modul Expo Go'da bulunmaz; development/production build'de yüklenir.
    cachedModule = require('react-native-google-mobile-ads') as GoogleAdsModule;
  } catch {
    cachedModule = null;
  }
  return cachedModule;
}

export function initializeGoogleAds() {
  const module = googleAds();
  if (!module) return Promise.resolve(null);
  if (!initialization) {
    initialization = module.AdsConsent.requestInfoUpdate()
      .then(() => module.AdsConsent.loadAndShowConsentFormIfRequired())
      .then(consent => consent.canRequestAds ? module.default().initialize().then(() => true) : false)
      .catch(() => false);
  }
  return initialization;
}

export function nativeUnitId() {
  const module = googleAds();
  if (!module) return null;
  const productionId = Platform.OS === 'ios'
    ? process.env.EXPO_PUBLIC_ADMOB_IOS_NATIVE_UNIT_ID
    : process.env.EXPO_PUBLIC_ADMOB_ANDROID_NATIVE_UNIT_ID;
  return __DEV__ ? module.TestIds.NATIVE : productionId || null;
}

export function interstitialUnitId() {
  const module = googleAds();
  if (!module) return null;
  const productionId = Platform.OS === 'ios'
    ? process.env.EXPO_PUBLIC_ADMOB_IOS_INTERSTITIAL_UNIT_ID
    : process.env.EXPO_PUBLIC_ADMOB_ANDROID_INTERSTITIAL_UNIT_ID;
  return __DEV__ ? module.TestIds.INTERSTITIAL : productionId || null;
}

export function rewardedUnitId() {
  const module = googleAds();
  if (!module) return null;
  const productionId = Platform.OS === 'ios'
    ? process.env.EXPO_PUBLIC_ADMOB_IOS_REWARDED_UNIT_ID
    : process.env.EXPO_PUBLIC_ADMOB_ANDROID_REWARDED_UNIT_ID;
  return __DEV__ ? module.TestIds.REWARDED : productionId || null;
}
