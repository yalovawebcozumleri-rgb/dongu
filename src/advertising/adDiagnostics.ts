import Constants from 'expo-constants';
import { Platform } from 'react-native';
import { apiRequest } from '../lib/api';

export type AdEnvironment = 'test' | 'production' | 'unknown';
export type AdFormat = 'sdk' | 'consent' | 'native' | 'interstitial' | 'rewarded';

export type AdDiagnosticContext = {
  environment?: AdEnvironment;
  format: AdFormat;
  placement?: string;
  unitId?: string | null;
};

function errorDetails(error: unknown) {
  if (!error || typeof error !== 'object') {
    return { errorMessage: typeof error === 'string' ? error : 'Unknown error' };
  }
  const candidate = error as Record<string, unknown>;
  return {
    errorCode: typeof candidate.code === 'string' ? candidate.code : undefined,
    errorDomain: typeof candidate.domain === 'string' ? candidate.domain : undefined,
    errorMessage: typeof candidate.message === 'string' ? candidate.message : String(error),
  };
}

export function reportAdDiagnostic(
  event: string,
  context: AdDiagnosticContext,
  details: Record<string, unknown> = {},
  error?: unknown,
) {
  const payload = {
    event,
    platform: Platform.OS === 'ios' ? 'ios' : 'android',
    environment: context.environment ?? 'unknown',
    format: context.format,
    placement: context.placement ?? null,
    unitId: context.unitId ?? null,
    appVersion: Constants.expoConfig?.version ?? null,
    buildVersion: Constants.nativeBuildVersion ?? null,
    ...details,
    ...(error === undefined ? {} : errorDetails(error)),
  };

  void apiRequest('/admob/client-events', {
    method: 'POST',
    body: payload,
    timeoutMs: 4000,
    retry: false,
  }).catch(() => undefined);
}