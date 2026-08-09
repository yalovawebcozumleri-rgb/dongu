export type DeliveryAddress = {
  id?: number;
  label: string;
  provinceId?: number | null;
  provinceName?: string | null;
  districtId?: number | null;
  districtName?: string | null;
  neighborhood?: string | null;
  publicArea: string;
  fullAddress: string;
  latitude: number;
  longitude: number;
  deliveryNotes?: string | null;
  isDefault?: boolean;
  activeListingsCount?: number;
  saved: boolean;
};