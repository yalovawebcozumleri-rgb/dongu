import type { ImageSourcePropType } from 'react-native';

export type AvatarKey =
  | 'avatar_01' | 'avatar_02' | 'avatar_03' | 'avatar_04' | 'avatar_05'
  | 'avatar_06' | 'avatar_07' | 'avatar_08' | 'avatar_09' | 'avatar_10'
  | 'avatar_11' | 'avatar_12' | 'avatar_13' | 'avatar_14' | 'avatar_15';

export const AVATAR_OPTIONS: ReadonlyArray<{ key: AvatarKey; source: ImageSourcePropType }> = [
  { key: 'avatar_02', source: require('../../assets/avatars/avatar-02.webp') },
  { key: 'avatar_05', source: require('../../assets/avatars/avatar-05.webp') },
  { key: 'avatar_07', source: require('../../assets/avatars/avatar-07.webp') },
  { key: 'avatar_08', source: require('../../assets/avatars/avatar-08.webp') },
  { key: 'avatar_10', source: require('../../assets/avatars/avatar-10.webp') },
  { key: 'avatar_01', source: require('../../assets/avatars/avatar-01.webp') },
  { key: 'avatar_03', source: require('../../assets/avatars/avatar-03.webp') },
  { key: 'avatar_04', source: require('../../assets/avatars/avatar-04.webp') },
  { key: 'avatar_06', source: require('../../assets/avatars/avatar-06.webp') },
  { key: 'avatar_09', source: require('../../assets/avatars/avatar-09.webp') },
  { key: 'avatar_11', source: require('../../assets/avatars/avatar-11.webp') },
  { key: 'avatar_12', source: require('../../assets/avatars/avatar-12.webp') },
  { key: 'avatar_13', source: require('../../assets/avatars/avatar-13.webp') },
  { key: 'avatar_14', source: require('../../assets/avatars/avatar-14.webp') },
  { key: 'avatar_15', source: require('../../assets/avatars/avatar-15.webp') },

];
const AVATAR_BY_KEY = Object.fromEntries(AVATAR_OPTIONS.map(option => [option.key, option.source])) as Record<AvatarKey, ImageSourcePropType>;

export function avatarKeyFromUri(uri?: string | null): AvatarKey | null {
  if (!uri?.startsWith('preset://')) return null;
  const key = uri.slice('preset://'.length) as AvatarKey;
  return key in AVATAR_BY_KEY ? key : null;
}

export function avatarSourceFromUri(uri?: string | null): ImageSourcePropType | null {
  const key = avatarKeyFromUri(uri);
  return key ? AVATAR_BY_KEY[key] : null;
}
