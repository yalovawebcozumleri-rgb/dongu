import { AdvertisementCollectionResponse } from './types';

export type MonetizedListItem<T> =
  | { kind: 'content'; key: string; item: T }
  | { kind: 'advertisement'; key: string; slotIndex: number };

export function insertAdvertisementSlots<T>(
  items: T[],
  keyFor: (item: T) => string,
  meta: AdvertisementCollectionResponse['meta'] | null | undefined,
): MonetizedListItem<T>[] {
  const content = items.map(item => ({ kind: 'content' as const, key: `content-${keyFor(item)}`, item }));
  if (!meta?.enabled || items.length < meta.minItems || meta.firstAfter <= 0 || meta.maxPerSession <= 0) return content;

  const result: MonetizedListItem<T>[] = [];
  let slotIndex = 0;
  items.forEach((item, index) => {
    result.push({ kind: 'content', key: `content-${keyFor(item)}`, item });
    const position = index + 1;
    const matchesFirst = position === meta.firstAfter;
    const matchesRepeat = meta.repeatEvery > 0 && position > meta.firstAfter && (position - meta.firstAfter) % meta.repeatEvery === 0;
    if ((matchesFirst || matchesRepeat) && slotIndex < meta.maxPerSession) {
      slotIndex += 1;
      result.push({ kind: 'advertisement', key: `advertisement-${slotIndex}`, slotIndex });
    }
  });

  return result;
}
