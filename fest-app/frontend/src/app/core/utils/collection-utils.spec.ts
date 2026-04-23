import { parseCollection } from './collection-utils';

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

