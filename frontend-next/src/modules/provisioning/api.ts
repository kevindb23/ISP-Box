import { getJson, postForm } from '../../lib/api';

export type Row = Record<string, any>;

export const list = <T = Row[]>(url: string) => getJson<T>(url);

export async function post<T = Row>(url: string, payload: Row = {}): Promise<T> {
  const form = new FormData();
  Object.entries(payload).forEach(([key, value]) => form.set(key, value == null ? '' : String(value)));
  return (await postForm<T>(url, form)).data;
}

export function rows(value: any): Row[] {
  return Array.isArray(value) ? value : Array.isArray(value?.items) ? value.items : [];
}
