import Constants from 'expo-constants';
import { AppState, Platform } from 'react-native';
import { reportAdDiagnostic } from './adDiagnostics';
import type { AdDiagnosticContext, AdEnvironment } from './adDiagnostics';
import { resolveAdPersonalization } from './adPrivacyPolicy';

type GoogleAdsModule = typeof import('react-native-google-mobile-ads');
type TrackingTransparencyModule = typeof import('expo-tracking-transparency');

export type GoogleAdsPrivacyState = {
  canRequestAds: boolean;
  gdprApplies: boolean | null;
  attStatus: string;
  personalizedAdsAllowed: boolean;
  requestNonPersonalizedAdsOnly: boolean;
};

let cachedModule: GoogleAdsModule | null | undefined;
let sdkInitialization: Promise<boolean> | null = null;
let consentInitialization: Promise<boolean> | null = null;
let privacyState: GoogleAdsPrivacyState = {
  canRequestAds: false,
  gdprApplies: null,
  attStatus: Platform.OS === 'ios' ? 'not-requested' : 'not-applicable',
  personalizedAdsAllowed: false,
  requestNonPersonalizedAdsOnly: true,
};

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

function reportPrivacyState(context: AdDiagnosticContext) {
  reportAdDiagnostic('privacy_state_resolved', { ...context, format: 'consent' }, {
    canRequestAds: privacyState.canRequestAds,
    gdprApplies: privacyState.gdprApplies,
    attStatus: privacyState.attStatus,
    personalizedAdsAllowed: privacyState.personalizedAdsAllowed,
    requestNonPersonalizedAdsOnly: privacyState.requestNonPersonalizedAdsOnly,
  });
}

function waitUntilAppIsActive() {
  if (AppState.currentState === 'active') return Promise.resolve();
  return new Promise<void>(resolve => {
    let subscription: ReturnType<typeof AppState.addEventListener> | null = null;
    const timeout = setTimeout(() => {
      subscription?.remove();
      resolve();
    }, 5000);
    subscription = AppState.addEventListener('change', state => {
      if (state !== 'active') return;
      clearTimeout(timeout);
      subscription?.remove();
      resolve();
    });
  });
}

async function requestIosTrackingPermission(context: AdDiagnosticContext) {
  if (Platform.OS !== 'ios') return 'not-applicable';
  try {
    const tracking = require('expo-tracking-transparency') as TrackingTransparencyModule;
    if (!tracking.isAvailable()) return 'unavailable';

    let permission = await tracking.getTrackingPermissionsAsync();
    reportAdDiagnostic('att_status_checked', { ...context, format: 'consent' }, {
      attStatus: permission.status,
    });
    if (permission.status === tracking.PermissionStatus.UNDETERMINED) {
      await waitUntilAppIsActive();
      permission = await tracking.requestTrackingPermissionsAsync();
      reportAdDiagnostic('att_prompt_completed', { ...context, format: 'consent' }, {
        attStatus: permission.status,
      });
    }
    return permission.status;
  } catch (error) {
    reportAdDiagnostic('att_request_failed', { ...context, format: 'consent' }, {}, error);
    return 'error';
  }
}

async function gdprAllowsPersonalizedAds(module: GoogleAdsModule, gdprApplies: boolean) {
  if (!gdprApplies) return true;
  const choices = await module.AdsConsent.getUserChoices();
  return choices.storeAndAccessInformationOnDevice
    && choices.createAPersonalisedAdsProfile
    && choices.selectPersonalisedAds;
}

function setPrivacyState(next: GoogleAdsPrivacyState, context: AdDiagnosticContext) {
  privacyState = next;
  reportPrivacyState(context);
}

export function googleAdRequestOptions() {
  return { requestNonPersonalizedAdsOnly: privacyState.requestNonPersonalizedAdsOnly };
}

export function getGoogleAdsPrivacyState() {
  return { ...privacyState };
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

    if (!gatheredConsent.canRequestAds) {
      setPrivacyState({
        canRequestAds: false,
        gdprApplies: null,
        attStatus: Platform.OS === 'ios' ? 'not-requested' : 'not-applicable',
        personalizedAdsAllowed: false,
        requestNonPersonalizedAdsOnly: true,
      }, context);
      return false;
    }

    const gdprApplies = await module.AdsConsent.getGdprApplies();
    const gdprAllowsPersonalization = await gdprAllowsPersonalizedAds(module, gdprApplies);
    const attStatus = gdprAllowsPersonalization
      ? await requestIosTrackingPermission(context)
      : Platform.OS === 'ios' ? 'not-requested-no-gdpr-consent' : 'not-applicable';
    const personalization = resolveAdPersonalization({
      platform: Platform.OS === 'ios' ? 'ios' : 'android',
      gdprAllowsPersonalization,
      attStatus,
    });

    setPrivacyState({
      canRequestAds: true,
      gdprApplies,
      attStatus,
      ...personalization,
    }, context);
    return initializeSdk(module, context);
  } catch (error) {
    reportAdDiagnostic('consent_update_failed', { ...context, format: 'consent' }, {}, error);

    try {
      const fallbackConsent = await module.AdsConsent.getConsentInfo();
      if (fallbackConsent.canRequestAds) {
        setPrivacyState({
          canRequestAds: true,
          gdprApplies: null,
          attStatus: Platform.OS === 'ios' ? 'not-requested-consent-fallback' : 'not-applicable',
          personalizedAdsAllowed: false,
          requestNonPersonalizedAdsOnly: true,
        }, context);
        return initializeSdk(module, context);
      }
      reportAdDiagnostic('consent_fallback_blocked', { ...context, format: 'consent' }, {
        consentStatus: fallbackConsent.status,
        canRequestAds: false,
      });
    } catch (fallbackError) {
      reportAdDiagnostic('consent_fallback_failed', { ...context, format: 'consent' }, {}, fallbackError);
    }

    setPrivacyState({
      canRequestAds: false,
      gdprApplies: null,
      attStatus: Platform.OS === 'ios' ? 'not-requested-consent-error' : 'not-applicable',
      personalizedAdsAllowed: false,
      requestNonPersonalizedAdsOnly: true,
    }, context);
    return false;
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
