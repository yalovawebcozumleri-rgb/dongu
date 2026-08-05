import assert from 'node:assert/strict';

process.env.EXPO_PUBLIC_API_URL = 'http://slow-network.test/api/v1';
const { apiRequest, ApiError } = await import('../src/lib/api.ts');

let calls = 0;
globalThis.fetch = async () => {
  calls += 1;
  await new Promise(resolve => setTimeout(resolve, 120));
  return new Response(JSON.stringify({ data: { ok: true } }), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  });
};

const slowStartedAt = performance.now();
const slowResponse = await apiRequest('/slow-success', { timeoutMs: 500, retry: false });
assert.deepEqual(slowResponse, { data: { ok: true } });
assert.ok(performance.now() - slowStartedAt >= 100, 'Gecikmeli yanıt beklenmeden tamamlandı.');
assert.equal(calls, 1);

calls = 0;
globalThis.fetch = async () => {
  calls += 1;
  if (calls === 1) throw new TypeError('Geçici bağlantı kesintisi');
  return new Response(JSON.stringify({ data: { retried: true } }), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  });
};

const retryResponse = await apiRequest('/retry', { timeoutMs: 500 });
assert.deepEqual(retryResponse, { data: { retried: true } });
assert.equal(calls, 2, 'GET isteği geçici bağlantı hatasından sonra bir kez denenmeliydi.');

globalThis.fetch = async (_url, options = {}) => new Promise((_resolve, reject) => {
  options.signal?.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true });
});

await assert.rejects(
  () => apiRequest('/timeout', { timeoutMs: 30, retry: false }),
  error => error instanceof ApiError
    && error.status === 408
    && error.message.includes('zaman aşımına'),
);

console.log('Yavaş ağ kontrolü geçti: gecikmeli yanıt, tek GET tekrarı ve Türkçe zaman aşımı doğrulandı.');
