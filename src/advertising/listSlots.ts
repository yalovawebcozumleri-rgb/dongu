import { MAX_NATIVE_ADS_PER_PAGE } from './nativeAdManager';
import { AdvertisementCollectionResponse } from './types';

export type MonetizedListItem<T> =
  | { kind: 'content'; key: string; item: T }
  | { kind: 'advertisement'; key: string; slotIndex: number };

export function countAdvertisementSlots(
  itemCount: number,
  meta: AdvertisementCollectionResponse['meta'] | null | undefined,
) {
  if (!meta?.enabled || itemCount < meta.minItems || meta.firstAfter <= 0 || meta.maxPerSession <= 0) return 0;
  const safeMaximum = Math.min(meta.maxPerSession, MAX_NATIVE_ADS_PER_PAGE);
  let count = 0;
  for (let position = 1; position <= itemCount && count < safeMaximum; position += 1) {
    const matchesFirst = position === meta.firstAfter;
    const matchesRepeat = meta.repeatEvery > 0
      && position > meta.firstAfter
      && (position - meta.firstAfter) % meta.repeatEvery === 0;
    if (matchesFirst || matchesRepeat) count += 1;
  }
  return count;
}

export function insertAdvertisementSlots<T>(
  items: T[],
  keyFor: (item: T) => string,
  meta: AdvertisementCollectionResponse['meta'] | null | undefined,
): MonetizedListItem<T>[] {
  const content = items.map(item => ({ kind: 'content' as const, key: `content-${keyFor(item)}`, item }));
  const slotLimit = countAdvertisementSlots(items.length, meta);
  if (!slotLimit) return content;

  const result: MonetizedListItem<T>[] = [];
  let slotIndex = 0;
  items.forEach((item, index) => {
    result.push({ kind: 'content', key: `content-${keyFor(item)}`, item });
    const position = index + 1;
    const matchesFirst = position === meta!.firstAfter;
    const matchesRepeat = meta!.repeatEvery > 0
      && position > meta!.firstAfter
      && (position - meta!.firstAfter) % meta!.repeatEvery === 0;
    if ((matchesFirst || matchesRepeat) && slotIndex < slotLimit) {
      slotIndex += 1;
      result.push({ kind: 'advertisement', key: `advertisement-${slotIndex}`, slotIndex });
    }
  });

  return result;
}
