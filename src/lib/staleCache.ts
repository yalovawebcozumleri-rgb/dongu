import AsyncStorage from '@react-native-async-storage/async-storage';

type CacheEnvelope<T> = { savedAt: number; value: T };

const memoryCache = new Map<string, CacheEnvelope<unknown>>();

export async function readStaleCache<T>(key: string, maxAgeMs = 7 * 24 * 60 * 60 * 1000): Promise<T | null> {
  const inMemory = memoryCache.get(key) as CacheEnvelope<T> | undefined;
  if (inMemory && Date.now() - inMemory.savedAt <= maxAgeMs) return inMemory.value;

  try {
    const raw = await AsyncStorage.getItem(key);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as CacheEnvelope<T>;
    if (!parsed || typeof parsed.savedAt !== 'number' || Date.now() - parsed.savedAt > maxAgeMs) {
      void AsyncStorage.removeItem(key);
      return null;
    }
    memoryCache.set(key, parsed);
    return parsed.value;
  } catch {
    return null;
  }
}

export async function writeStaleCache<T>(key: string, value: T): Promise<void> {
  const envelope: CacheEnvelope<T> = { savedAt: Date.now(), value };
  memoryCache.set(key, envelope);
  try {
    await AsyncStorage.setItem(key, JSON.stringify(envelope));
  } catch {
    // Önbellek hatası ekranın güncel API verisini göstermesini engellemez.
  }
}
