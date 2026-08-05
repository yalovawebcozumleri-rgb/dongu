export type DeliveryAddress = {
  id?: number;
  label: string;
  publicArea: string;
  fullAddress: string;
  latitude: number;
  longitude: number;
  deliveryNotes?: string | null;
  isDefault?: boolean;
  saved: boolean;
};