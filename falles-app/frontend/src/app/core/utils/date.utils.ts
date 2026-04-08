export function normalizeDateKey(rawDate: string): string {
  return rawDate.includes('T') ? rawDate.slice(0, 10) : rawDate;
}

export function formatDateKey(date: Date): string {
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function formatLocalDate(
  rawDate: string,
  options?: Intl.DateTimeFormatOptions,
): string {
  const normalized = normalizeDateKey(rawDate);
  const [yearRaw, monthRaw, dayRaw] = normalized.split('-');
  const date = new Date(Number(yearRaw), Number(monthRaw) - 1, Number(dayRaw));

  if (Number.isNaN(date.getTime())) {
    return 'Fecha por confirmar';
  }

  return date.toLocaleDateString('es-ES', options ?? {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  });
}

export function formatTime(rawTime?: string | null): string {
  if (!rawTime) return 'Sin hora';
  return (
    rawTime.match(/^(\d{2}:\d{2})/)?.[1] ??
    rawTime.match(/T(\d{2}:\d{2})/)?.[1] ??
    'Sin hora'
  );
}

export function hasValidTime(time?: string | null): boolean {
  return typeof time === 'string' && /^\d{2}:\d{2}/.test(time);
}
