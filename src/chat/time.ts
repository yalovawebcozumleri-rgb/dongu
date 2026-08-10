export const formatLocalMessageTime = (createdAt?: string | null, fallback = '') => {
  if (!createdAt) return fallback;

  const date = new Date(createdAt);
  if (Number.isNaN(date.getTime())) return fallback;

  return date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
};
