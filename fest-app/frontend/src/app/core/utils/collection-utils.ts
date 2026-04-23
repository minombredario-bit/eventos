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

