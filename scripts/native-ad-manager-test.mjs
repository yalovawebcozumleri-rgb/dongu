import assert from 'node:assert/strict';
import { acquireNativeAd, nativeAdManagerSnapshot } from '../src/advertising/nativeAdManager.ts';

const pendingLoads = [];
const module = {
  NativeAd: {
    createForAdRequest: unitId => new Promise(resolve => pendingLoads.push({ unitId, resolve })),
  },
};
const flush = () => new Promise(resolve => setImmediate(resolve));
const makeAd = responseId => ({ responseId, destroyed: 0, destroy() { this.destroyed += 1; } });

const firstPreload = acquireNativeAd(module, 'home', 'preload');
const secondPreload = acquireNativeAd(module, 'detail', 'preload');
const visible = acquireNativeAd(module, 'profile', 'visible');

assert.equal(pendingLoads.length, 1, 'Only one native ad request may load at once.');
assert.equal(pendingLoads[0].unitId, 'home');
assert.deepEqual(nativeAdManagerSnapshot(), { active: 0, queued: 2, loading: 1 });

const firstAd = makeAd('first');
pendingLoads[0].resolve(firstAd);
assert.equal((await firstPreload.promise)?.responseId, 'first');
await flush();
assert.equal(pendingLoads[1].unitId, 'profile', 'A visible request jumps ahead of queued preloads.');

const visibleAd = makeAd('visible');
pendingLoads[1].resolve(visibleAd);
assert.equal((await visible.promise)?.responseId, 'visible');
await flush();
assert.equal(pendingLoads[2].unitId, 'detail');

const secondAd = makeAd('second');
pendingLoads[2].resolve(secondAd);
assert.equal((await secondPreload.promise)?.responseId, 'second');
await flush();
assert.deepEqual(nativeAdManagerSnapshot(), { active: 3, queued: 0, loading: 0 });

const fourth = acquireNativeAd(module, 'next', 'preload');
assert.equal(pendingLoads.length, 3, 'A fourth request waits while three ads are active.');
assert.deepEqual(nativeAdManagerSnapshot(), { active: 3, queued: 1, loading: 0 });

firstPreload.release();
await flush();
assert.equal(firstAd.destroyed, 1, 'A released ad is destroyed exactly once.');
assert.equal(pendingLoads[3].unitId, 'next', 'Releasing one slot allows the next preload to start.');

fourth.release();
assert.equal(await fourth.promise, null, 'A released in-flight request no longer reaches its screen.');
const fourthAd = makeAd('fourth');
pendingLoads[3].resolve(fourthAd);
await flush();
assert.equal(fourthAd.destroyed, 1, 'An ad arriving after its screen closes is destroyed immediately.');

secondPreload.release();
visible.release();
assert.equal(secondAd.destroyed, 1);
assert.equal(visibleAd.destroyed, 1);
assert.deepEqual(nativeAdManagerSnapshot(), { active: 0, queued: 0, loading: 0 });

console.log('Native ad manager priority and lifecycle test passed.');