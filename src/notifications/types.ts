export type AppNotification = {
  id: number;
  type: string;
  title: string;
  body: string;
  data: {
    route?: string;
    conversationId?: number;
    listingId?: number;
    campaignId?: number;
  };
  read: boolean;
  createdAt: string | null;
  time: string | null;
};

export type NotificationPreferences = {
  messagesEnabled: boolean;
  pickupRequestsEnabled: boolean;
  deliveryEnabled: boolean;
  reviewsEnabled: boolean;
  listingUpdatesEnabled: boolean;
  marketingEnabled: boolean;
};
