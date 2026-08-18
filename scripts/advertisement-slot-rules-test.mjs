import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import ts from 'typescript';
import { MAX_NATIVE_ADS_PER_PAGE } from '../src/advertising/nativeAdManager.ts';

const sourceUrl = new URL('../src/advertising/listSlots.ts', import.meta.url);
const source = (await readFile(sourceUrl, 'utf8'))
  .replace("import { MAX_NATIVE_ADS_PER_PAGE } from './nativeAdManager';", 'const MAX_NATIVE_ADS_PER_PAGE = ' + MAX_NATIVE_ADS_PER_PAGE + ';');
const compiled = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 },
}).outputText;
const slotRules = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
const {
  advertisementSlotPositions,
  countAdvertisementSlots,
  insertAdvertisementSlots,
} = slotRules;

const meta = overrides => ({
  placement: 'home_feed',
  enabled: true,
  sourceOrder: ['admob'],
  firstAfter: 3,
  repeatEvery: 5,
  maxPerSession: 5,
  minItems: 3,
  adMobAndroidUnitId: 'android-unit',
  adMobIosUnitId: 'ios-unit',
  ...overrides,
});

assert.deepEqual(
  advertisementSlotPositions(0, meta({ firstAfter: 0, repeatEvery: 0, minItems: 0 })),
  [0],
  'İlk reklam ve minimum içerik 0 ise boş sayfada bir reklam yuvası olmalı.',
);
assert.equal(
  countAdvertisementSlots(0, meta({ firstAfter: 0, repeatEvery: 0, minItems: 1 })),
  0,
  'Minimum içerik 1 ise boş sayfada reklam olmamalı.',
);
assert.deepEqual(
  advertisementSlotPositions(13, meta({ firstAfter: 3, repeatEvery: 5, minItems: 3 })),
  [3, 8, 13],
  'Mevcut 3 içerikten sonra ve her 5 içerikte bir kuralı değişmemeli.',
);
assert.deepEqual(
  advertisementSlotPositions(13, meta({ firstAfter: 0, repeatEvery: 5, minItems: 0 })),
  [0, 5, 10],
  'Sıfırdan başlayan tekrarlı kural listenin başında ve her 5 içerikte çalışmalı.',
);
assert.deepEqual(
  advertisementSlotPositions(100, meta({ firstAfter: 0, repeatEvery: 1, minItems: 0, maxPerSession: 20 })),
  [0, 1, 2, 3, 4],
  'Panel değeri yüksek olsa bile mobil güvenlik üst sınırı beş reklam olmalı.',
);
assert.deepEqual(
  advertisementSlotPositions(13, meta({ enabled: false, firstAfter: 0, minItems: 0 })),
  [],
  'Kapalı reklam alanı hiçbir yuva üretmemeli.',
);

const oneItemRows = insertAdvertisementSlots([{ id: 7 }], item => String(item.id), meta({
  firstAfter: 0,
  repeatEvery: 0,
  minItems: 0,
}));
assert.deepEqual(
  oneItemRows.map(row => row.kind),
  ['advertisement', 'content'],
  'İlk reklam 0 ise reklam ilk içerikten önce gelmeli.',
);
assert.deepEqual(
  insertAdvertisementSlots([], item => String(item.id), meta({ firstAfter: 0, repeatEvery: 0, minItems: 0 })),
  [],
  'Boş FlatList verisi boş kalmalı; ekran reklamı footer ile göstererek boş durum mesajını korur.',
);

console.log('Advertisement slot rules: empty, leading, repeated, minimum-content and hard-cap cases passed.');