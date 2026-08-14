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

const first = acquireNativeAd(module, 'home');
const second = acquireNativeAd(module, 'detail');
const third = acquireNativeAd(module, 'profile');

assert.equal(pendingLoads.length, 1, 'Only one native ad request may load at once.');
assert.deepEqual(nativeAdManagerSnapshot(), { active: 0, queued: 2, loading: 1 });

const firstAd = makeAd('first');
pendingLoads[0].resolve(firstAd);
assert.equal((await first.promise)?.responseId, 'first');
await flush();
assert.equal(pendingLoads.length, 2, 'The second request starts only after the first completes.');

const secondAd = makeAd('second');
pendingLoads[1].resolve(secondAd);
assert.equal((await second.promise)?.responseId, 'second');
await flush();
assert.deepEqual(nativeAdManagerSnapshot(), { active: 2, queued: 1, loading: 0 });
assert.equal(pendingLoads.length, 2, 'A third request waits while two ads are active.');

first.release();
await flush();
assert.equal(firstAd.destroyed, 1, 'A released ad is destroyed exactly once.');
assert.equal(pendingLoads.length, 3, 'Releasing one slot allows the next request to start.');

third.release();
assert.equal(await third.promise, null, 'A released in-flight request no longer reaches its screen.');
const thirdAd = makeAd('third');
pendingLoads[2].resolve(thirdAd);
await flush();
assert.equal(thirdAd.destroyed, 1, 'An ad arriving after its screen closes is destroyed immediately.');

second.release();
assert.equal(secondAd.destroyed, 1, 'The remaining active ad is destroyed on release.');
assert.deepEqual(nativeAdManagerSnapshot(), { active: 0, queued: 0, loading: 0 });

console.log('Native ad manager lifecycle test passed.');
