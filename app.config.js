const GOOGLE_TEST_ANDROID_APP_ID = 'ca-app-pub-3940256099942544~3347511713';
const GOOGLE_TEST_IOS_APP_ID = 'ca-app-pub-3940256099942544~1458002511';

const value = name => process.env[name]?.trim() || undefined;

module.exports = ({ config: base }) => {
  const plugins = base.plugins.map(plugin => {
  const name = Array.isArray(plugin) ? plugin[0] : plugin;
  const options = Array.isArray(plugin) ? plugin[1] : undefined;

  if (name === 'expo-notifications') {
    return [
      'expo-notifications',
      {
        icon: './assets/android-icon-monochrome.png',
        color: '#17613F',
        defaultChannel: 'default',
      },
    ];
  }

  if (name === 'react-native-google-mobile-ads') {
    return [
      name,
      {
        ...options,
        androidAppId: value('EXPO_PUBLIC_ADMOB_ANDROID_APP_ID') || GOOGLE_TEST_ANDROID_APP_ID,
        iosAppId: value('EXPO_PUBLIC_ADMOB_IOS_APP_ID') || GOOGLE_TEST_IOS_APP_ID,
      },
    ];
  }

  return plugin;
  });

  return {
    ...base,
    name: 'Döngü',
    description: 'Geri dönüştürülebilir ambalajları yakındaki kullanıcılarla güvenli şekilde döngüye kazandır.',
    scheme: 'dongu',
    plugins,
    ios: {
      ...base.ios,
      bundleIdentifier: 'com.yalovawebcozumleri.dongu',
      buildNumber: '1',
    },
    android: {
      ...base.android,
      package: 'com.yalovawebcozumleri.dongu',
      versionCode: 1,
      ...(value('GOOGLE_SERVICES_JSON') ? { googleServicesFile: value('GOOGLE_SERVICES_JSON') } : {}),
    },
    extra: {
      ...base.extra,
      ...(value('EXPO_PROJECT_ID') ? { eas: { projectId: value('EXPO_PROJECT_ID') } } : {}),
      legal: {
        termsUrl: value('EXPO_PUBLIC_TERMS_URL') || 'https://dongu.yalovawebcozumleri.com/kullanim-sartlari',
        privacyUrl: value('EXPO_PUBLIC_PRIVACY_URL') || 'https://dongu.yalovawebcozumleri.com/gizlilik-politikasi',
        accountDeletionUrl: value('EXPO_PUBLIC_ACCOUNT_DELETION_URL') || 'https://dongu.yalovawebcozumleri.com/hesap-silme',
      },
    },
  };
};
