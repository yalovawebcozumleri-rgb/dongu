import EchoModule from 'laravel-echo';
import PusherModule from 'pusher-js/react-native';

export function subscribeToConversations(
  token: string,
  userId: string,
  onChange: (event: { conversationId: number; kind: string }) => void,
  onNotification?: (event: { unreadCount: number }) => void,
) {
  const key = process.env.EXPO_PUBLIC_REVERB_APP_KEY;
  const host = process.env.EXPO_PUBLIC_REVERB_HOST;
  if (!key || !host || !userId) return () => {};
  const apiUrl = process.env.EXPO_PUBLIC_API_URL || '';
  const authEndpoint = apiUrl.replace(/\/api\/v1\/?$/, '') + '/broadcasting/auth';
  try {
    const PusherConstructor = ((PusherModule as any).Pusher ?? (PusherModule as any).default ?? PusherModule) as any;
    const EchoConstructor = ((EchoModule as any).default ?? EchoModule) as any;
    const client = new PusherConstructor(key, {
      cluster: 'mt1',
      wsHost: host,
      wsPort: Number(process.env.EXPO_PUBLIC_REVERB_PORT || 8080),
      wssPort: Number(process.env.EXPO_PUBLIC_REVERB_PORT || 8080),
      forceTLS: process.env.EXPO_PUBLIC_REVERB_SCHEME === 'https',
      enabledTransports: ['ws', 'wss'],
      channelAuthorization: { endpoint: authEndpoint, transport: 'ajax', headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
    });
    const echo = new EchoConstructor({ broadcaster: 'reverb', client });
    const channel = echo.private(`users.${userId}`).listen('.conversation.changed', onChange);
    if (onNotification) channel.listen('.notification.changed', onNotification);
    return () => { echo.leave(`users.${userId}`); client.disconnect(); };
  } catch (error) {
    console.warn('Gerçek zamanlı mesaj bağlantısı başlatılamadı.', error);
    return () => {};
  }
}
