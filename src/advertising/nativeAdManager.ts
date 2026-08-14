type NativeAdInstance = {
  destroy: () => void;
  responseId?: string;
};

type NativeAdModule = {
  NativeAd: {
    createForAdRequest: (unitId: string, options: { requestNonPersonalizedAdsOnly: boolean }) => Promise<NativeAdInstance>;
  };
};

type NativeAdTask = {
  id: number;
  module: NativeAdModule;
  unitId: string;
  priority: NativeAdPriority;
  cancelled: boolean;
  settled: boolean;
  resolve: (ad: NativeAdInstance | null) => void;
  reject: (error: unknown) => void;
};

export type NativeAdLease = {
  promise: Promise<NativeAdInstance | null>;
  release: () => void;
};

export type NativeAdPriority = 'visible' | 'preload';

const MAX_ACTIVE_NATIVE_ADS = 3;
const queue: NativeAdTask[] = [];
const activeAds = new Map<number, NativeAdInstance>();
const destroyedAds = new WeakSet<object>();
let nextTaskId = 1;
let inFlightTask: NativeAdTask | null = null;

function destroyOnce(ad: NativeAdInstance | null | undefined) {
  if (!ad || destroyedAds.has(ad as object)) return;
  destroyedAds.add(ad as object);
  try { ad.destroy(); } catch { /* Native SDK cleanup must never crash navigation. */ }
}

function settle(task: NativeAdTask, value: NativeAdInstance | null) {
  if (task.settled) return;
  task.settled = true;
  task.resolve(value);
}

function fail(task: NativeAdTask, error: unknown) {
  if (task.settled) return;
  task.settled = true;
  task.reject(error);
}

function removeQueuedTask(taskId: number) {
  const index = queue.findIndex(task => task.id === taskId);
  if (index >= 0) queue.splice(index, 1);
}

function processQueue() {
  if (inFlightTask || activeAds.size >= MAX_ACTIVE_NATIVE_ADS) return;
  const visibleTaskIndex = queue.findIndex(task => !task.cancelled && task.priority === 'visible');
  const task = visibleTaskIndex >= 0 ? queue.splice(visibleTaskIndex, 1)[0] : queue.shift();
  if (!task) return;
  if (task.cancelled) {
    settle(task, null);
    processQueue();
    return;
  }

  inFlightTask = task;
  void task.module.NativeAd.createForAdRequest(task.unitId, { requestNonPersonalizedAdsOnly: true })
    .then(ad => {
      if (task.cancelled) {
        destroyOnce(ad);
        settle(task, null);
        return;
      }
      activeAds.set(task.id, ad);
      settle(task, ad);
    })
    .catch(error => {
      if (task.cancelled) settle(task, null);
      else fail(task, error);
    })
    .finally(() => {
      if (inFlightTask?.id === task.id) inFlightTask = null;
      processQueue();
    });
}

export function acquireNativeAd(module: NativeAdModule, unitId: string, priority: NativeAdPriority = 'visible'): NativeAdLease {
  const id = nextTaskId++;
  let resolve!: (ad: NativeAdInstance | null) => void;
  let reject!: (error: unknown) => void;
  const promise = new Promise<NativeAdInstance | null>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });
  const task: NativeAdTask = { id, module, unitId, priority, cancelled: false, settled: false, resolve, reject };
  queue.push(task);
  processQueue();

  return {
    promise,
    release: () => {
      if (task.cancelled) return;
      task.cancelled = true;
      removeQueuedTask(id);
      const activeAd = activeAds.get(id);
      if (activeAd) {
        activeAds.delete(id);
        destroyOnce(activeAd);
      }
      settle(task, null);
      processQueue();
    },
  };
}

export function nativeAdManagerSnapshot() {
  return {
    active: activeAds.size,
    queued: queue.filter(task => !task.cancelled).length,
    loading: inFlightTask ? 1 : 0,
  };
}
