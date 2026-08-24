import assert from 'node:assert/strict';
import { resolveAdPersonalization } from '../src/advertising/adPrivacyPolicy.ts';

const cases = [
  { name: 'iOS ATT granted', input: { platform: 'ios', gdprAllowsPersonalization: true, attStatus: 'granted' }, personalized: true },
  { name: 'iOS ATT denied', input: { platform: 'ios', gdprAllowsPersonalization: true, attStatus: 'denied' }, personalized: false },
  { name: 'iOS ATT restricted', input: { platform: 'ios', gdprAllowsPersonalization: true, attStatus: 'restricted' }, personalized: false },
  { name: 'iOS ATT error fallback', input: { platform: 'ios', gdprAllowsPersonalization: true, attStatus: 'error' }, personalized: false },
  { name: 'iOS no GDPR consent', input: { platform: 'ios', gdprAllowsPersonalization: false, attStatus: 'not-requested-no-gdpr-consent' }, personalized: false },
  { name: 'legacy iOS without ATT API', input: { platform: 'ios', gdprAllowsPersonalization: true, attStatus: 'unavailable' }, personalized: true },
  { name: 'Android GDPR consent', input: { platform: 'android', gdprAllowsPersonalization: true, attStatus: 'not-applicable' }, personalized: true },
  { name: 'Android no GDPR consent', input: { platform: 'android', gdprAllowsPersonalization: false, attStatus: 'not-applicable' }, personalized: false },
];

for (const testCase of cases) {
  const result = resolveAdPersonalization(testCase.input);
  assert.equal(result.personalizedAdsAllowed, testCase.personalized, testCase.name);
  assert.equal(result.requestNonPersonalizedAdsOnly, !testCase.personalized, testCase.name + ' NPA');
}

console.log('Ad privacy policy: UMP and ATT allow/deny/fallback cases passed.');