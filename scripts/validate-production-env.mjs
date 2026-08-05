const isProduction = process.env.APP_ENV === 'production';

if (!isProduction) {
  console.log('Production environment validation skipped.');
  process.exit(0);
}

const platform = process.env.EAS_BUILD_PLATFORM || process.env.BUILD_PLATFORM || 'android';
const shared = [
  'EXPO_PUBLIC_API_URL',
  'EXPO_PUBLIC_REVERB_APP_KEY',
  'EXPO_PUBLIC_REVERB_HOST',
  'EXPO_PUBLIC_REVERB_PORT',
  'EXPO_PUBLIC_REVERB_SCHEME',
  'EXPO_PROJECT_ID',
];
const android = [
  'GOOGLE_SERVICES_JSON',
  'EXPO_PUBLIC_ADMOB_ANDROID_APP_ID',
  'EXPO_PUBLIC_ADMOB_ANDROID_NATIVE_UNIT_ID',
  'EXPO_PUBLIC_ADMOB_ANDROID_INTERSTITIAL_UNIT_ID',
  'EXPO_PUBLIC_ADMOB_ANDROID_REWARDED_UNIT_ID',
];
const ios = [
  'EXPO_PUBLIC_ADMOB_IOS_APP_ID',
  'EXPO_PUBLIC_ADMOB_IOS_NATIVE_UNIT_ID',
  'EXPO_PUBLIC_ADMOB_IOS_INTERSTITIAL_UNIT_ID',
  'EXPO_PUBLIC_ADMOB_IOS_REWARDED_UNIT_ID',
];
const required = [...shared, ...(platform === 'ios' ? ios : android)];
const missing = required.filter(name => !process.env[name]?.trim());

if (missing.length) {
  throw new Error(`Production build blocked. Missing environment values: ${missing.join(', ')}`);
}

const apiUrl = new URL(process.env.EXPO_PUBLIC_API_URL);
const localHosts = /^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/;
if (apiUrl.protocol !== 'https:' || localHosts.test(apiUrl.hostname)) {
  throw new Error('Production API URL must use HTTPS and a public hostname.');
}
if (process.env.EXPO_PUBLIC_REVERB_SCHEME !== 'https' || process.env.EXPO_PUBLIC_REVERB_PORT !== '443') {
  throw new Error('Production realtime connection must use HTTPS/WSS on port 443.');
}

const googleTestPrefix = 'ca-app-pub-3940256099942544';
for (const name of required.filter(item => item.includes('ADMOB'))) {
  if (process.env[name].startsWith(googleTestPrefix)) {
    throw new Error(`Production build blocked. ${name} still contains a Google test identifier.`);
  }
}

console.log(`Production environment is valid for ${platform}.`);
