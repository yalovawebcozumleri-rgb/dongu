import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';
import { ApiError, apiRequest } from '../lib/api';

const TOKEN_KEY = '@dongu/api-token';

export type AppUser = {
  id: string;
  email: string;
  emailVerified: boolean;
  fullName: string;
  profileComplete: boolean;
  avatarUrl: string | null;
};

export type CodeIntent = 'login' | 'register';
export type CodeRequest = {
  intent: CodeIntent;
  email: string;
  name?: string;
  termsAccepted?: boolean;
  termsVersion?: string;
  privacyNoticeVersion?: string;
};

type AuthResult = { error?: string };
type ApiUser = { id: number | string; name: string; email: string; email_verified: boolean; profile_complete: boolean; avatar_url?: string | null; avatar_thumbnail_url?: string | null };
type SessionResponse = { data: { user: ApiUser; token: string } };
type UserResponse = { data: { user: ApiUser } };

type AuthContextValue = {
  loading: boolean;
  user: AppUser | null;
  token: string | null;
  requestCode: (request: CodeRequest) => Promise<AuthResult>;
  verifyCode: (email: string, code: string) => Promise<AuthResult>;
  completeProfile: (fullName: string) => Promise<AuthResult>;
  updateProfile: (fullName: string) => Promise<AuthResult>;
  refreshSession: () => Promise<void>;
  signOut: () => Promise<void>;
  deleteAccount: (confirmation: string) => Promise<AuthResult>;
};

const AuthContext = createContext<AuthContextValue | null>(null);
const mapUser = (user: ApiUser): AppUser => ({
  id: String(user.id),
  email: user.email,
  emailVerified: user.email_verified,
  fullName: user.name,
  profileComplete: user.profile_complete,
  avatarUrl: user.avatar_thumbnail_url || user.avatar_url || null,
});
const errorMessage = (error: unknown) => {
  if (error instanceof ApiError) return error.message;
  return 'Yerel API’ye ulaşılamadı. Laravel sunucusunun çalıştığını kontrol et.';
};

export function AuthProvider({ children }: React.PropsWithChildren) {
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState<AppUser | null>(null);
  const [token, setToken] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;
    const restore = async () => {
      const savedToken = await AsyncStorage.getItem(TOKEN_KEY);
      if (savedToken) {
        try {
          const response = await apiRequest<UserResponse>('/auth/me', { token: savedToken });
          if (mounted) {
            setToken(savedToken);
            setUser(mapUser(response.data.user));
          }
        } catch (error) {
          if (error instanceof ApiError && error.status === 401) await AsyncStorage.removeItem(TOKEN_KEY);
        }
      }
      if (mounted) setLoading(false);
    };
    restore().catch(() => mounted && setLoading(false));
    return () => { mounted = false; };
  }, []);

  const refreshSession = useCallback(async () => {
    if (!token) return;
    const response = await apiRequest<UserResponse>('/auth/me', { token });
    setUser(mapUser(response.data.user));
  }, [token]);

  const requestCode = useCallback(async (request: CodeRequest): Promise<AuthResult> => {
    try {
      await apiRequest('/auth/code/request', {
        method: 'POST',
        body: {
          intent: request.intent,
          email: request.email.trim().toLowerCase(),
          ...(request.intent === 'register' ? {
            name: request.name?.trim(),
            terms_accepted: request.termsAccepted,
            terms_version: request.termsVersion,
            privacy_notice_version: request.privacyNoticeVersion,
          } : {}),
        },
      });
      return {};
    } catch (error) {
      return { error: errorMessage(error) };
    }
  }, []);

  const verifyCode = useCallback(async (email: string, code: string): Promise<AuthResult> => {
    try {
      const response = await apiRequest<SessionResponse>('/auth/code/verify', {
        method: 'POST',
        body: {
          email: email.trim().toLowerCase(),
          code,
          device_name: `${Platform.OS} telefon`,
        },
      });
      await AsyncStorage.setItem(TOKEN_KEY, response.data.token);
      setToken(response.data.token);
      setUser(mapUser(response.data.user));
      return {};
    } catch (error) {
      return { error: errorMessage(error) };
    }
  }, []);

  const completeProfile = useCallback(async (fullName: string): Promise<AuthResult> => {
    try {
      const response = await apiRequest<UserResponse>('/auth/profile', {
        method: 'PATCH', body: { name: fullName }, token,
      });
      setUser(mapUser(response.data.user));
      return {};
    } catch (error) {
      return { error: errorMessage(error) };
    }
  }, [token]);

  const deleteAccount = useCallback(async (confirmation: string): Promise<AuthResult> => {
    try {
      await apiRequest('/auth/account', { method: 'DELETE', body: { confirmation }, token });
      await AsyncStorage.removeItem(TOKEN_KEY);
      setToken(null);
      setUser(null);
      return {};
    } catch (error) {
      return { error: errorMessage(error) };
    }
  }, [token]);

  const signOut = useCallback(async () => {
    if (token) {
      try { await apiRequest('/auth/logout', { method: 'POST', token }); } catch {}
    }
    await AsyncStorage.removeItem(TOKEN_KEY);
    setToken(null);
    setUser(null);
  }, [token]);

  const value = useMemo<AuthContextValue>(() => ({
    loading, user, token, requestCode, verifyCode, completeProfile, updateProfile: completeProfile, refreshSession, signOut, deleteAccount,
  }), [completeProfile, deleteAccount, loading, refreshSession, requestCode, signOut, token, user, verifyCode]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth, AuthProvider içinde kullanılmalıdır.');
  return context;
}
