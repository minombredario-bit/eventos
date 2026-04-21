function parseDateKey(rawDate: string): Date | null {
  if (typeof rawDate !== 'string') {
    return null;
  }

  const normalized = normalizeDateKey(rawDate);
  const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/);

  if (!match) {
    return null;
  }

  const [, yearRaw, monthRaw, dayRaw] = match;
  const year = Number(yearRaw);
  const month = Number(monthRaw);
  const day = Number(dayRaw);

  const date = new Date(year, month - 1, day);

  if (
    Number.isNaN(date.getTime()) ||
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day
  ) {
    return null;
  }

  return date;
}

function parseTime(rawTime?: string | null): { hours: number; minutes: number } | null {
  if (typeof rawTime !== 'string') {
    return null;
  }

  const match = rawTime.trim().match(/(?:T|^)(\d{2}):(\d{2})/);

  if (!match) {
    return null;
  }

  const hours = Number(match[1]);
  const minutes = Number(match[2]);

  if (
    !Number.isInteger(hours) ||
    !Number.isInteger(minutes) ||
    hours < 0 ||
    hours > 23 ||
    minutes < 0 ||
    minutes > 59
  ) {
    return null;
  }

  return { hours, minutes };
}

export function normalizeDateKey(rawDate: string): string {
  if (typeof rawDate !== 'string') {
    return '';
  }

  const trimmed = rawDate.trim();
  if (!trimmed) {
    return '';
  }

  return trimmed.match(/^(\d{4}-\d{2}-\d{2})/)?.[1] ?? trimmed;
}

export function formatDateKey(date: Date): string {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
    return '';
  }

  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');

  return `${year}-${month}-${day}`;
}

export function formatLocalDate(
  rawDate: string,
  options?: Intl.DateTimeFormatOptions,
): string {
  const date = parseDateKey(rawDate);

  if (!date) {
    return 'Fecha por confirmar';
  }

  return date.toLocaleDateString(
    'es-ES',
    options ?? {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    },
  );
}

export function formatTime(rawTime?: string | null): string {
  const parsed = parseTime(rawTime);

  if (!parsed) {
    return 'Sin hora';
  }

  return `${String(parsed.hours).padStart(2, '0')}:${String(parsed.minutes).padStart(2, '0')}`;
}

export function hasValidTime(time?: string | null): boolean {
  return parseTime(time) !== null;
}

export function formatMonth(date: string): string {
  return formatLocalDate(date, { month: 'short' }).toUpperCase();
}

export function formatDay(date: string): string {
  return formatLocalDate(date, { day: '2-digit' });
}

export function getMonthKey(date: string): string {
  return (date ?? '').trim().substring(0, 7);
}

export function getCurrentMonthKey(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = `${now.getMonth() + 1}`.padStart(2, '0');
  return `${year}-${month}`;
}

export function formatDate(date: string): string {
  return formatLocalDate(date, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}
