import Constants from 'expo-constants';
import { Platform } from 'react-native';

type GoogleAdsModule = typeof import('react-native-google-mobile-ads');
let cachedModule: GoogleAdsModule | null | undefined;
let sdkInitialization: Promise<boolean> | null = null;
let consentInitialization: Promise<boolean> | null = null;

export function googleAds(): GoogleAdsModule | null {
  if (cachedModule !== undefined) return cachedModule;
  if (Constants.appOwnership === 'expo') { cachedModule = null; return cachedModule; }
  try { cachedModule = require('react-native-google-mobile-ads') as GoogleAdsModule; } catch { cachedModule = null; }
  return cachedModule;
}

function initializeSdk(module: GoogleAdsModule) {
  if (!sdkInitialization) {
    sdkInitialization = module.default().initialize().then(() => true).catch(() => false);
  }
  return sdkInitialization;
}

export function isGoogleTestUnitId(unitId?: string | null) {
  const module = googleAds();
  if (!module || !unitId) return false;
  return Object.values(module.TestIds).some(testId => Boolean(testId) && testId === unitId);
}

export function initializeGoogleAds(unitId?: string | null, testMode = false) {
  const module = googleAds();
  if (!module) return Promise.resolve(false);
  if (testMode || isGoogleTestUnitId(unitId)) return initializeSdk(module);
  if (!consentInitialization) {
    consentInitialization = module.AdsConsent.requestInfoUpdate()
      .then(() => module.AdsConsent.loadAndShowConsentFormIfRequired())
      .then(consent => consent.canRequestAds ? initializeSdk(module) : false)
      .catch(() => false);
  }
  return consentInitialization;
}

export function nativeUnitId(remoteUnitId?: string | null) {
  const module = googleAds();
  if (!module) return null;
  const productionId = Platform.OS === 'ios' ? process.env.EXPO_PUBLIC_ADMOB_IOS_NATIVE_UNIT_ID : process.env.EXPO_PUBLIC_ADMOB_ANDROID_NATIVE_UNIT_ID;
  return __DEV__ ? module.TestIds.NATIVE : remoteUnitId || productionId || null;
}

export function interstitialUnitId(remoteUnitId?: string | null) {
  const module = googleAds();
  if (!module) return null;
  const productionId = Platform.OS === 'ios' ? process.env.EXPO_PUBLIC_ADMOB_IOS_INTERSTITIAL_UNIT_ID : process.env.EXPO_PUBLIC_ADMOB_ANDROID_INTERSTITIAL_UNIT_ID;
  return __DEV__ ? module.TestIds.INTERSTITIAL : remoteUnitId || productionId || null;
}

export function rewardedUnitId(remoteUnitId?: string | null) {
  const module = googleAds();
  if (!module) return null;
  const productionId = Platform.OS === 'ios' ? process.env.EXPO_PUBLIC_ADMOB_IOS_REWARDED_UNIT_ID : process.env.EXPO_PUBLIC_ADMOB_ANDROID_REWARDED_UNIT_ID;
  return __DEV__ ? module.TestIds.REWARDED : remoteUnitId || productionId || null;
}
