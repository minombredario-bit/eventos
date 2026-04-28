import { parseCollection, parsePaginatedCollection } from './collection-utils';

describe('parseCollection', () => {
  it('returns array when input is array', () => {
    const arr = [{ id: 1 }, { id: 2 }];
    const result = parseCollection<any>(arr);
    expect(result).toEqual(arr);
  });

  it('extracts member property', () => {
    const payload = { member: [{ id: 'a' }], 'hydra:totalItems': 1 };
    const result = parseCollection<any>(payload);
    expect(result).toEqual([{ id: 'a' }]);
  });

  it('extracts hydra:member property', () => {
    const payload = { 'hydra:member': [{ id: 'x' }] };
    const result = parseCollection<any>(payload);
    expect(result).toEqual([{ id: 'x' }]);
  });

  it('returns empty array for null or unexpected values', () => {
    expect(parseCollection<any>(null)).toEqual([]);
    expect(parseCollection<any>({})).toEqual([]);
    expect(parseCollection<any>('string' as unknown)).toEqual([]);
  });
});

describe('parsePaginatedCollection', () => {
  it('builds pagination metadata from hydra payload', () => {
    const payload = {
      'hydra:member': [{ id: 'x' }],
      'hydra:totalItems': 8,
      'hydra:view': { 'hydra:next': '/api/eventos?page=2' },
    };

    const result = parsePaginatedCollection<any>(payload);

    expect(result).toEqual({
      items: [{ id: 'x' }],
      totalItems: 8,
      hasNext: true,
      hasPrevious: false,
    });
  });

  it('falls back to parsed collection when pagination metadata is missing', () => {
    const payload = [{ id: 1 }, { id: 2 }];
    const result = parsePaginatedCollection<any>(payload);

    expect(result).toEqual({
      items: payload,
      totalItems: 2,
      hasNext: false,
      hasPrevious: false,
    });
  });
});
