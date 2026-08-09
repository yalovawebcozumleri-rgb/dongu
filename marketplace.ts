export type Material = 'PET' | 'Cam' | 'Alüminyum';
export type Coordinates = { latitude: number; longitude: number };
export type ListingItem = { material: Material; count: number; unitPrice: number };
export type RequestStatus = 'none' | 'pending' | 'reserved' | 'rejected' | 'cancelled';
export type ListingStatus = 'active' | 'reserved' | 'completed' | 'cancelled';
export type ListingOwnerState = 'published' | 'reserved' | 'completed' | 'removed' | 'expired';

export type Listing = {
  id: number;
  items: ListingItem[];
  latitude: number;
  longitude: number;
  district: string;
  provinceId?: number | null;
  seller: string;
  sellerId: number;
  sellerAvatarUrl?: string | null;
  sellerTransactions: number;
  rating: number | null;
  ratingCount: number;
  isFavorited?: boolean;
  time: string;
  note: string;
  status: ListingStatus;
  ownerState?: ListingOwnerState;
  requestStatus: RequestStatus;
  distanceKm?: number | null;
  photos?: string[];
  expiresAt?: string | null;
  boostedUntil?: string | null;
  isBoosted?: boolean;
  expiresInDays?: number | null;
};

export const MATERIALS: Material[] = ['PET', 'Cam', 'Alüminyum'];
export const RADII = [1, 3, 5, 10, 25, 50];
export const EMPTY_CENTER: Coordinates = { latitude: 0, longitude: 0 };
export const materialColor: Record<Material, string> = {
  PET: '#DFF2E4',
  Cam: '#F1EBDD',
  Alüminyum: '#E8EDF1',
};

export const money = (value: number) =>
  `${value.toFixed(2).replace('.', ',')} TL`;
export const listingCount = (listing: Listing) =>
  listing.items.reduce((sum, item) => sum + item.count, 0);
export const listingPrice = (listing: Listing) =>
  listing.items.reduce((sum, item) => sum + item.count * item.unitPrice, 0);
const radians = (value: number) => value * Math.PI / 180;
export const distanceKm = (a: Coordinates, b: Coordinates) => {
  const latitudeDifference = radians(b.latitude - a.latitude);
  const longitudeDifference = radians(b.longitude - a.longitude);
  const value =
    Math.sin(latitudeDifference / 2) ** 2 +
    Math.cos(radians(a.latitude)) *
      Math.cos(radians(b.latitude)) *
      Math.sin(longitudeDifference / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
};
export const distanceLabel = (distance: number) =>
  distance < 1
    ? `${Math.max(10, Math.round(distance * 100)) * 10} m`
    : `${distance.toFixed(1).replace('.', ',')} km`;
