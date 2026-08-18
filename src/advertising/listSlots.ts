import { MAX_NATIVE_ADS_PER_PAGE } from './nativeAdManager';
import type { AdvertisementCollectionResponse } from './types';

export type MonetizedListItem<T> =
  | { kind: 'content'; key: string; item: T }
  | { kind: 'advertisement'; key: string; slotIndex: number };

export function advertisementSlotPositions(
  itemCount: number,
  meta: AdvertisementCollectionResponse['meta'] | null | undefined,
) {
  if (!meta?.enabled || itemCount < meta.minItems || meta.maxPerSession <= 0) return [];

  const safeItemCount = Math.max(0, Math.floor(itemCount));
  const firstPosition = Math.max(0, Math.floor(meta.firstAfter));
  if (firstPosition > safeItemCount) return [];

  const safeMaximum = Math.min(meta.maxPerSession, MAX_NATIVE_ADS_PER_PAGE);
  const positions = [firstPosition];
  if (meta.repeatEvery <= 0) return positions;

  for (
    let position = firstPosition + meta.repeatEvery;
    position <= safeItemCount && positions.length < safeMaximum;
    position += meta.repeatEvery
  ) {
    positions.push(position);
  }

  return positions.slice(0, safeMaximum);
}

export function countAdvertisementSlots(
  itemCount: number,
  meta: AdvertisementCollectionResponse['meta'] | null | undefined,
) {
  return advertisementSlotPositions(itemCount, meta).length;
}

export function insertAdvertisementSlots<T>(
  items: T[],
  keyFor: (item: T) => string,
  meta: AdvertisementCollectionResponse['meta'] | null | undefined,
): MonetizedListItem<T>[] {
  const content = items.map(item => ({ kind: 'content' as const, key: `content-${keyFor(item)}`, item }));
  const positions = advertisementSlotPositions(items.length, meta);
  if (!positions.length || !items.length) return content;

  const result: MonetizedListItem<T>[] = [];
  let slotIndex = 0;
  const positionSet = new Set(positions);
  if (positionSet.has(0)) {
    slotIndex += 1;
    result.push({ kind: 'advertisement', key: `advertisement-${slotIndex}`, slotIndex });
  }
  items.forEach((item, index) => {
    result.push({ kind: 'content', key: `content-${keyFor(item)}`, item });
    const position = index + 1;
    if (positionSet.has(position)) {
      slotIndex += 1;
      result.push({ kind: 'advertisement', key: `advertisement-${slotIndex}`, slotIndex });
    }
  });

  return result;
}
