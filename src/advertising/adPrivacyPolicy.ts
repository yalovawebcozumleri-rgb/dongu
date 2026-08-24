export type AdPersonalizationInput = {
  platform: 'ios' | 'android';
  gdprAllowsPersonalization: boolean;
  attStatus: string;
};

export type AdPersonalizationDecision = {
  personalizedAdsAllowed: boolean;
  requestNonPersonalizedAdsOnly: boolean;
};

export function resolveAdPersonalization({
  platform,
  gdprAllowsPersonalization,
  attStatus,
}: AdPersonalizationInput): AdPersonalizationDecision {
  const attAllowsPersonalization = platform !== 'ios'
    || attStatus === 'granted'
    || attStatus === 'unavailable';
  const personalizedAdsAllowed = gdprAllowsPersonalization && attAllowsPersonalization;

  return {
    personalizedAdsAllowed,
    requestNonPersonalizedAdsOnly: !personalizedAdsAllowed,
  };
}