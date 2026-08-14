import assert from 'node:assert/strict';
import {
  acquireNativeAd,
  nativeAdManagerSnapshot,
  peekNativeAd,
  prepareNativeAdSession,
  releaseNativeAdSession,
} from '../src/advertising/nativeAdManager.ts';

const flush = () => new Promise(resolve => setImmediate(resolve));
const requests = [];
const ads = [];
let requestCount = 0;

const module = {
  NativeAd: {
    createForAdRequest: async unitId => {
      requestCount += 1;
      return await new Promise(resolve => requests.push({ resolve, unitId }));
    },
  },
};

prepareNativeAdSession(module, 'unit-1', 'session-panel-4', 4);
assert.equal(requestCount, 1, 'Only one network request may run at once.');
assert.deepEqual(nativeAdManagerSnapshot(), { active: 0, queued: 3, loading: 1, sessions: 1 });

for (let index = 1; index <= 4; index += 1) {
  const ad = {
    responseId: 'ad-' + index,
    destroyed: 0,
    destroy() { this.destroyed += 1; },
  };
  ads.push(ad);
  requests[index - 1].resolve(ad);
  await flush();
  await flush();
  if (index < 4) {
    assert.equal(requestCount, index + 1, 'The next request must begin automatically.');
  }
}

assert.deepEqual(nativeAdManagerSnapshot(), { active: 4, queued: 0, loading: 0, sessions: 1 });
assert.equal(peekNativeAd('session-panel-4', 'unit-1', 1), ads[0]);

const remounted = acquireNativeAd(module, 'unit-1', 'session-panel-4', 1);
assert.equal(await remounted.promise, ads[0]);
remounted.release();
assert.equal(ads[0].destroyed, 0, 'Unmounting a list cell must not destroy its page ad.');

releaseNativeAdSession('session-panel-4');
assert.deepEqual(nativeAdManagerSnapshot(), { active: 0, queued: 0, loading: 0, sessions: 0 });
assert.deepEqual(ads.map(ad => ad.destroyed), [1, 1, 1, 1]);

prepareNativeAdSession(module, 'unit-2', 'session-hard-cap', 20);
assert.equal(requestCount, 5);
assert.deepEqual(nativeAdManagerSnapshot(), { active: 0, queued: 4, loading: 1, sessions: 1 });
releaseNativeAdSession('session-hard-cap');
assert.equal(nativeAdManagerSnapshot().queued, 0);

const cancelledAd = {
  destroyed: 0,
  destroy() { this.destroyed += 1; },
};
requests[4].resolve(cancelledAd);
await flush();
await flush();
assert.equal(cancelledAd.destroyed, 1, 'An ad completing after session release must be destroyed once.');
assert.deepEqual(nativeAdManagerSnapshot(), { active: 0, queued: 0, loading: 0, sessions: 0 });

console.log('Native ad manager: sequential loading, panel count, hard cap, cache retention and cleanup passed.');
