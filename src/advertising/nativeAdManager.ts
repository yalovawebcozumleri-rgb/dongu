type NativeAdInstance = {
  destroy: () => void;
  responseId?: string;
};

type NativeAdModule = {
  NativeAd: {
    createForAdRequest: (unitId: string, options: { requestNonPersonalizedAdsOnly: boolean }) => Promise<NativeAdInstance>;
  };
};

type NativeAdEntry = {
  key: string;
  sessionKey: string;
  slotIndex: number;
  module: NativeAdModule;
  unitId: string;
  priority: NativeAdPriority;
  cancelled: boolean;
  settled: boolean;
  state: 'queued' | 'loading' | 'ready' | 'failed';
  ad: NativeAdInstance | null;
  promise: Promise<NativeAdInstance | null>;
  resolve: (ad: NativeAdInstance | null) => void;
  reject: (error: unknown) => void;
};

export type NativeAdLease = {
  promise: Promise<NativeAdInstance | null>;
  release: () => void;
};

export type NativeAdPriority = 'visible' | 'preload';
export const MAX_NATIVE_ADS_PER_PAGE = 5;

const queue: NativeAdEntry[] = [];
const entries = new Map<string, NativeAdEntry>();
const sessionEntries = new Map<string, Set<string>>();
const destroyedAds = new WeakSet<object>();
let inFlightEntry: NativeAdEntry | null = null;

function cacheKey(sessionKey: string, unitId: string, slotIndex: number) {
  return `${sessionKey}::${unitId}::${slotIndex}`;
}

function destroyOnce(ad: NativeAdInstance | null | undefined) {
  if (!ad || destroyedAds.has(ad as object)) return;
  destroyedAds.add(ad as object);
  try { ad.destroy(); } catch { /* Native SDK cleanup must never crash navigation. */ }
}

function settle(entry: NativeAdEntry, value: NativeAdInstance | null) {
  if (entry.settled) return;
  entry.settled = true;
  entry.resolve(value);
}

function fail(entry: NativeAdEntry, error: unknown) {
  if (entry.settled) return;
  entry.settled = true;
  entry.reject(error);
}

function removeQueuedEntry(key: string) {
  const index = queue.findIndex(entry => entry.key === key);
  if (index >= 0) queue.splice(index, 1);
}

function processQueue() {
  if (inFlightEntry) return;
  const visibleIndex = queue.findIndex(entry => !entry.cancelled && entry.priority === 'visible');
  const entry = visibleIndex >= 0 ? queue.splice(visibleIndex, 1)[0] : queue.shift();
  if (!entry) return;
  if (entry.cancelled) {
    settle(entry, null);
    processQueue();
    return;
  }

  inFlightEntry = entry;
  entry.state = 'loading';
  void entry.module.NativeAd.createForAdRequest(entry.unitId, { requestNonPersonalizedAdsOnly: true })
    .then(ad => {
      if (entry.cancelled || entries.get(entry.key) !== entry) {
        destroyOnce(ad);
        settle(entry, null);
        return;
      }
      entry.ad = ad;
      entry.state = 'ready';
      settle(entry, ad);
    })
    .catch(error => {
      if (entry.cancelled) settle(entry, null);
      else {
        entry.state = 'failed';
        fail(entry, error);
      }
    })
    .finally(() => {
      if (inFlightEntry === entry) inFlightEntry = null;
      processQueue();
    });
}

function getOrCreateEntry(module: NativeAdModule, unitId: string, sessionKey: string, slotIndex: number, priority: NativeAdPriority) {
  const key = cacheKey(sessionKey, unitId, slotIndex);
  const existing = entries.get(key);
  if (existing) {
    if (priority === 'visible' && existing.priority !== 'visible') existing.priority = 'visible';
    return existing;
  }
  let resolve!: (ad: NativeAdInstance | null) => void;
  let reject!: (error: unknown) => void;
  const promise = new Promise<NativeAdInstance | null>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });
  void promise.catch(() => undefined);
  const entry: NativeAdEntry = {
    key, sessionKey, slotIndex, module, unitId, priority,
    cancelled: false, settled: false, state: 'queued', ad: null, promise, resolve, reject,
  };
  entries.set(key, entry);
  const keys = sessionEntries.get(sessionKey) ?? new Set<string>();
  keys.add(key);
  sessionEntries.set(sessionKey, keys);
  queue.push(entry);
  processQueue();
  return entry;
}

export function prepareNativeAdSession(module: NativeAdModule, unitId: string, sessionKey: string, slotCount: number) {
  const safeCount = Math.max(0, Math.min(MAX_NATIVE_ADS_PER_PAGE, Math.floor(slotCount)));
  for (let slotIndex = 1; slotIndex <= safeCount; slotIndex += 1) {
    getOrCreateEntry(module, unitId, sessionKey, slotIndex, 'preload');
  }
}

export function acquireNativeAd(module: NativeAdModule, unitId: string, sessionKey: string, slotIndex: number, priority: NativeAdPriority = 'visible'): NativeAdLease {
  const entry = getOrCreateEntry(module, unitId, sessionKey, slotIndex, priority);
  processQueue();
  return {
    promise: entry.promise,
    // FlatList may unmount a visual cell while its page session remains active.
    // The session, not the cell, owns and eventually destroys the cached ad.
    release: () => undefined,
  };
}

export function peekNativeAd(sessionKey: string, unitId: string, slotIndex: number) {
  return entries.get(cacheKey(sessionKey, unitId, slotIndex))?.ad ?? null;
}

export function releaseNativeAdSession(sessionKey: string) {
  const keys = sessionEntries.get(sessionKey);
  if (!keys) return;
  for (const key of keys) {
    const entry = entries.get(key);
    if (!entry) continue;
    entry.cancelled = true;
    removeQueuedEntry(key);
    destroyOnce(entry.ad);
    entry.ad = null;
    settle(entry, null);
    entries.delete(key);
  }
  sessionEntries.delete(sessionKey);
  processQueue();
}

export function nativeAdManagerSnapshot() {
  return {
    active: [...entries.values()].filter(entry => entry.state === 'ready').length,
    queued: queue.filter(entry => !entry.cancelled).length,
    loading: inFlightEntry ? 1 : 0,
    sessions: sessionEntries.size,
  };
}
