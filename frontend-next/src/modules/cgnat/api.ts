import { getJson, postForm } from '../../lib/api';
import type { CgnatConfig, CgnatResponse } from './contracts';

const ENDPOINT = '/api/v1/cgnat';

function formOf(values: Record<string, unknown>): FormData {
  const form = new FormData();
  Object.entries(values).forEach(([key, value]) => form.set(key, String(value ?? '')));
  return form;
}

export const getCgnat = () => getJson<CgnatResponse>(`${ENDPOINT}/config`);
export const saveCgnat = (config: CgnatConfig) => postForm<unknown>(`${ENDPOINT}/config`, formOf({ ...config, enabled: Number(config.enabled) }));
export const applyCgnat = () => postForm<unknown>(`${ENDPOINT}/config/apply`, new FormData());
export function removePostroutingRules(ruleHashes: string[]) {
  const form = new FormData();
  ruleHashes.forEach((hash) => form.append('rule_hashes[]', hash));
  return postForm<unknown>(`${ENDPOINT}/postrouting/remove`, form);
}
