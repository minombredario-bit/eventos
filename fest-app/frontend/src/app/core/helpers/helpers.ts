export function normalizeHtmlText(value?: string | null): string {
  return (value ?? '')
    .replace(/&nbsp;/g, ' ')
    .replace(/\u00a0/g, ' ');
}
