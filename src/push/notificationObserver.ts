import Constants from 'expo-constants';

export type PushRouteData = {
  route?: unknown;
  conversationId?: unknown;
  listingId?: unknown;
  notificationId?: unknown;
};

export function observeNotificationResponses(open: (data: PushRouteData) => void): () => void {
  if (Constants.appOwnership === 'expo') return () => {};
  let disposed = false;
  let subscription: { remove: () => void } | null = null;

  void import('expo-notifications').then(Notifications => {
    if (disposed) return;
    const handle: Parameters<typeof Notifications.addNotificationResponseReceivedListener>[0] = response => {
      const rawData = response.notification.request.content.data;
      open(rawData && typeof rawData === 'object' ? rawData as PushRouteData : {});
      Notifications.clearLastNotificationResponse();
    };
    const last = Notifications.getLastNotificationResponse();
    if (last) handle(last);
    subscription = Notifications.addNotificationResponseReceivedListener(handle);
  }).catch(() => {});

  return () => {
    disposed = true;
    subscription?.remove();
  };
}
