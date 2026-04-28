export function parseCollection<T>(r: unknown): T[] {
  try {
    if (Array.isArray(r)) return r as T[];

    const obj = r as Record<string, unknown> | null | undefined;
    if (!obj) return [];

    const member = obj['member'] ?? obj['hydra:member'];
    if (Array.isArray(member)) return member as T[];

    return [];
  } catch {
    return [];
  }
}

export interface PaginatedCollection<T> {
  items: T[];
  totalItems: number;
  hasNext: boolean;
  hasPrevious: boolean;
}

export function parsePaginatedCollection<T>(r: unknown): PaginatedCollection<T> {
  const items = parseCollection<T>(r);
  const obj = (r ?? {}) as Record<string, unknown>;
  const totalItems = Number(obj['totalItems'] ?? obj['hydra:totalItems'] ?? items.length);
  const view = (obj['view'] ?? obj['hydra:view'] ?? {}) as Record<string, unknown>;

  return {
    items,
    totalItems: Number.isFinite(totalItems) ? totalItems : items.length,
    hasNext: Boolean(view['next'] ?? view['hydra:next']),
    hasPrevious: Boolean(view['previous'] ?? view['hydra:previous']),
  };
}
