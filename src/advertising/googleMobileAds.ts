import Constants from 'expo-constants';
import { Platform } from 'react-native';
import { reportAdDiagnostic } from './adDiagnostics';
import type { AdDiagnosticContext, AdEnvironment } from './adDiagnostics';

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

function initializeSdk(module: GoogleAdsModule, context: AdDiagnosticContext) {
  if (!sdkInitialization) {
    reportAdDiagnostic('sdk_initialize_started', { ...context, format: 'sdk' });
    sdkInitialization = module.default().initialize()
      .then(statuses => {
        reportAdDiagnostic('sdk_initialize_succeeded', { ...context, format: 'sdk' }, {
          adapterCount: statuses.length,
        });
        return true;
      })
      .catch(error => {
        sdkInitialization = null;
        reportAdDiagnostic('sdk_initialize_failed', { ...context, format: 'sdk' }, {}, error);
        return false;
      });
  }
  return sdkInitialization;
}
export function isGoogleTestUnitId(unitId?: string | null) {
  const module = googleAds();
  if (!module || !unitId) return false;
  return Object.values(module.TestIds).some(testId => Boolean(testId) && testId === unitId);
}

export function adEnvironmentForUnitId(unitId?: string | null): AdEnvironment {
  if (!unitId) return 'unknown';
  return isGoogleTestUnitId(unitId) ? 'test' : 'production';
}

async function initializeConsent(module: GoogleAdsModule, context: AdDiagnosticContext) {
  let cachedSdkInitialization: Promise<boolean> | null = null;

  try {
    const cachedConsent = await module.AdsConsent.getConsentInfo();
    reportAdDiagnostic('consent_cached_checked', { ...context, format: 'consent' }, {
      consentStatus: cachedConsent.status,
      canRequestAds: cachedConsent.canRequestAds,
    });
    if (cachedConsent.canRequestAds) cachedSdkInitialization = initializeSdk(module, context);
  } catch (error) {
    reportAdDiagnostic('consent_cached_failed', { ...context, format: 'consent' }, {}, error);
  }

  try {
    const updatedConsent = await module.AdsConsent.requestInfoUpdate();
    reportAdDiagnostic('consent_info_updated', { ...context, format: 'consent' }, {
      consentStatus: updatedConsent.status,
      canRequestAds: updatedConsent.canRequestAds,
    });

    const gatheredConsent = await module.AdsConsent.loadAndShowConsentFormIfRequired();
    reportAdDiagnostic('consent_form_completed', { ...context, format: 'consent' }, {
      consentStatus: gatheredConsent.status,
      canRequestAds: gatheredConsent.canRequestAds,
    });

    if (gatheredConsent.canRequestAds) return initializeSdk(module, context);
    if (cachedSdkInitialization) return cachedSdkInitialization;

    reportAdDiagnostic('consent_ads_blocked', { ...context, format: 'consent' }, {
      consentStatus: gatheredConsent.status,
      canRequestAds: false,
    });
    return false;
  } catch (error) {
    reportAdDiagnostic('consent_update_failed', { ...context, format: 'consent' }, {}, error);

    try {
      const fallbackConsent = await module.AdsConsent.getConsentInfo();
      if (fallbackConsent.canRequestAds) return initializeSdk(module, context);
      reportAdDiagnostic('consent_fallback_blocked', { ...context, format: 'consent' }, {
        consentStatus: fallbackConsent.status,
        canRequestAds: false,
      });
    } catch (fallbackError) {
      reportAdDiagnostic('consent_fallback_failed', { ...context, format: 'consent' }, {}, fallbackError);
    }

    return cachedSdkInitialization ? cachedSdkInitialization : false;
  }
}

export function initializeGoogleAds(context: AdDiagnosticContext = { format: 'sdk' }) {
  const module = googleAds();
  if (!module) {
    reportAdDiagnostic('sdk_module_unavailable', context);
    return Promise.resolve(false);
  }

  if (!consentInitialization) {
    consentInitialization = initializeConsent(module, context).then(ready => {
      if (!ready) consentInitialization = null;
      return ready;
    });
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
