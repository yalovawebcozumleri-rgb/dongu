import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';
import { apiRequest } from '../lib/api';

export type PushNotificationState = 'checking' | 'expo_go' | 'physical_device_required' | 'not_configured' | 'disabled' | 'blocked' | 'enabled';

const projectId = () => Constants.easConfig?.projectId ?? Constants.expoConfig?.extra?.eas?.projectId;

export async function getPushNotificationState(): Promise<PushNotificationState> {
  if (Constants.executionEnvironment === ExecutionEnvironment.StoreClient) return 'expo_go';
  if (!Device.isDevice) return 'physical_device_required';
  if (!projectId()) return 'not_configured';
  try {
    const Notifications = await import('expo-notifications');
    const permission = await Notifications.getPermissionsAsync();
    if (permission.status === 'granted') return 'enabled';
    return permission.canAskAgain === false ? 'blocked' : 'disabled';
  } catch {
    return 'not_configured';
  }
}

export async function enablePushNotifications(token: string): Promise<{ ok: boolean; message: string }> {
  try {
    if (Constants.executionEnvironment === ExecutionEnvironment.StoreClient) return { ok: false, message: 'Uzaktan bildirimler Expo Go yerine development build ile açılabilir.' };
    if (!Device.isDevice) return { ok: false, message: 'Push bildirimleri fiziksel bir telefonda açılabilir.' };
    const Notifications = await import('expo-notifications');
    Notifications.setNotificationHandler({
      handleNotification: async () => ({ shouldPlaySound: true, shouldSetBadge: true, shouldShowBanner: true, shouldShowList: true }),
    });
    if (Platform.OS === 'android') await Notifications.setNotificationChannelAsync('messages', { name: 'Mesajlar', importance: Notifications.AndroidImportance.HIGH });
    const current = await Notifications.getPermissionsAsync();
    const permission = current.status === 'granted' ? current : await Notifications.requestPermissionsAsync();
    if (permission.status !== 'granted') return { ok: false, message: 'Telefon ayarlarından bildirim izni vermen gerekiyor.' };
    const easProjectId = projectId();
    if (!easProjectId) return { ok: false, message: 'EAS proje kimliği henüz tanımlı değil.' };
    const pushToken = (await Notifications.getExpoPushTokenAsync({ projectId: easProjectId })).data;
    await apiRequest('/push-tokens', { method: 'POST', token, body: { token: pushToken, platform: Platform.OS } });
    return { ok: true, message: '' };
  } catch (error) {
    if (__DEV__) console.warn('[push] registration failed', error instanceof Error ? error.message : error);
    return { ok: false, message: 'Telefon bildirim kaydı tamamlanamadı. Bağlantını kontrol edip tekrar dene.' };
  }
}
