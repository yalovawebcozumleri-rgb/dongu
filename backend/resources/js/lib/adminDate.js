const alreadyFormatted = /^\d{2}\.\d{2}\.\d{4}(?:\s+\d{2}:\d{2}(?::\d{2})?)?$/;

export const formatAdminDate = (value, fallback = '—') => {
    if (value === null || value === undefined || value === '') return fallback;

    const raw = String(value).trim();
    if (alreadyFormatted.test(raw)) return raw;

    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return raw;

    return new Intl.DateTimeFormat('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: 'Europe/Istanbul',
    }).format(date).replace(',', '');
};
