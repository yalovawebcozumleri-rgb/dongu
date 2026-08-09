import { Listing } from '../../marketplace';

export type ConversationStatus = 'inquiry' | 'pending' | 'accepted' | 'rejected' | 'cancelled' | 'completed' | 'closed';

export type ConversationListingSummary = Pick<Listing, 'id' | 'sellerId' | 'seller' | 'district' | 'items'>;

export type Conversation = {
  id: number;
  status: ConversationStatus;
  role: 'buyer' | 'seller';
  counterpart: {
    id: number;
    name: string;
    avatarUrl?: string | null;
    rating: number | null;
    ratingCount: number;
  };
  listing: Listing | null;
  listingSummary: ConversationListingSummary | null;
  listingAvailable: boolean;
  closureReason: 'listing_unavailable' | 'listing_removed' | 'listing_expired' | null;
  closedAt: string | null;
  lastMessage: { body: string; time: string } | null;
  unreadCount: number;
  conversationHidden: boolean;
  hasMessages: boolean;
  canOpenConversation: boolean;
  canSendMessage: boolean;
  isBlocked: boolean;
  blockedByMe: boolean;
  deliveryCode: string | null;
  exactAddress: string | null;
  exactLatitude: number | null;
  exactLongitude: number | null;
  deliveryNotes: string | null;
  canReview: boolean;
  reviewed: boolean;
  reviewExpiresAt: string | null;
  cancelledByRole: 'buyer' | 'seller' | null;
  cancelledAt: string | null;
  acceptedAt: string | null;
  completedAt: string | null;
  updatedAt: string;
};

export type ConversationMessage = {
  id: number | string;
  clientId?: string | null;
  readAt?: string | null;
  deliveryState?: 'sending' | 'failed';
  sender: 'me' | 'other' | 'system';
  text: string;
  time: string;
  createdAt: string;
};

export type ConversationResponse = { data: Conversation; monetization?: { showInterstitial: boolean; dailyPickupOrdinal: number } };
export type ConversationCollectionResponse = { data: Conversation[] };
export type MessageCollectionResponse = { data: ConversationMessage[]; meta: { hasMore: boolean; nextCursor: number | null } };
export type BlockedUser = { id: number; name: string; blockedAt: string | null };
