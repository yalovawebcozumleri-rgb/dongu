const configuredUrl = process.env.EXPO_PUBLIC_API_URL?.trim();

export const API_URL = (configuredUrl || 'http://192.168.1.102:8000/api/v1').replace(/\/$/, '');
export const isApiConfigured = Boolean(configuredUrl || __DEV__);
export type QuotaRewardOffer = {
  rewardKey: string;
  label: string;
  unit: string;
  amount: number;
  dailyLimit: number;
  adsUsed: number;
  adsRemaining: number;
  validHours: number;
  activeBonus: number;
  available: boolean;
  nextAvailableAt: string | null;
};

export class ApiError extends Error {
  public status: number;
  public details: Record<string, unknown>;
  public retryAt: string | null;
  public rewardOffer: QuotaRewardOffer | null;

  constructor(message: string, status: number, details: Record<string, unknown> = {}) {
    super(message);
    this.status = status;
    this.details = details;
    const quota = details.quota && typeof details.quota === 'object' ? details.quota as Record<string, unknown> : null;
    this.retryAt = typeof quota?.retryAt === 'string' ? quota.retryAt : null;
    this.rewardOffer = quota?.rewardOffer && typeof quota.rewardOffer === 'object'
      ? quota.rewardOffer as QuotaRewardOffer
      : null;
  }
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE';
  body?: Record<string, unknown> | FormData;
  token?: string | null;
  timeoutMs?: number;
  retry?: boolean;
};

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? 'GET';
  const attempts = method === 'GET' && options.retry !== false ? 2 : 1;
  let response: Response | null = null;

  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), options.timeoutMs ?? 12000);
    try {
      const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
      response = await fetch(`${API_URL}${path}`, {
        method,
        signal: controller.signal,
        headers: {
          Accept: 'application/json',
          ...(!isFormData ? { 'Content-Type': 'application/json' } : {}),
          ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
        },
        body: options.body ? (isFormData ? options.body as FormData : JSON.stringify(options.body)) : undefined,
      });
      break;
    } catch (error) {
      if (attempt === attempts) {
        if (error instanceof Error && error.name === 'AbortError') {
          throw new ApiError('Bağlantı zaman aşımına uğradı. İnternetini kontrol edip yeniden dene.', 408);
        }
        throw new ApiError('Sunucuya ulaşılamadı. İnternet bağlantını kontrol et.', 0);
      }
      await new Promise(resolve => setTimeout(resolve, 350));
    } finally {
      clearTimeout(timeout);
    }
  }

  if (!response) throw new ApiError('Sunucuya ulaşılamadı.', 0);

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const validationMessage = payload.errors
      ? Object.values(payload.errors as Record<string, string[]>).flat()[0]
      : null;
    throw new ApiError(validationMessage || payload.message || 'İşlem tamamlanamadı.', response.status, payload as Record<string, unknown>);
  }
  return payload as T;
}
