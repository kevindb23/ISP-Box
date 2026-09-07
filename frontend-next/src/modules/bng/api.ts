import { getJson, postForm, type ApiEnvelope } from '../../lib/api';
import type { AccelResponse, BngRuntime, BngSetting, HostKeyScan } from './contracts';

const ENDPOINT = '/api/v1/bng';
function formOf(values: Record<string, unknown>): FormData { const form = new FormData(); Object.entries(values).forEach(([key, value]) => form.set(key, Array.isArray(value) ? value.join(',') : String(value ?? ''))); return form; }

export const getBngSetting = () => getJson<BngSetting>(`${ENDPOINT}/setting`);
export const getBngSettings = () => getJson<BngSetting[]>(`${ENDPOINT}/settings`);
export const getBngRuntime = () => getJson<BngRuntime>(`${ENDPOINT}/runtime`);
export const getAccelConfig = () => getJson<AccelResponse>(`${ENDPOINT}/accel-ppp`);
export const previewAccelConfig = (values: Record<string, unknown>) => postForm<{ config?: string }>(`${ENDPOINT}/accel-ppp/preview-draft`, formOf(values));
export const scanBngHostKey = () => getJson<HostKeyScan>(`${ENDPOINT}/setting/host-key`);
export const saveBngSetting = (values: Record<string, unknown>) => postForm<BngSetting>(`${ENDPOINT}/setting`, formOf(values));
export const saveAccelConfig = (values: Record<string, unknown>) => postForm<Record<string, unknown>>(`${ENDPOINT}/accel-ppp`, formOf(values));
export const testBngConnection = () => postForm<Record<string, unknown>>(`${ENDPOINT}/setting/test`, new FormData());
export const deleteBngSetting = () => postForm<Record<string, never>>(`${ENDPOINT}/setting/delete`, new FormData());
export const stageAccelConfig = () => postForm<Record<string, unknown>>(`${ENDPOINT}/accel-ppp/stage`, new FormData());
export function trustBngHostKey(fingerprint: string): Promise<ApiEnvelope<Record<string, unknown>>> { return postForm(`${ENDPOINT}/setting/host-key/trust`, formOf({ fingerprint })); }
export function installBootRecovery(): Promise<ApiEnvelope<Record<string, unknown>>> { return postForm(`${ENDPOINT}/reconciliation/install`, formOf({ confirmation: 'INSTALL BNG BOOT RECOVERY' })); }
export function activateAccelConfig(): Promise<ApiEnvelope<Record<string, unknown>>> { return postForm(`${ENDPOINT}/accel-ppp/activate-maintenance`, formOf({ confirmation: 'ACTIVATE DURING MAINTENANCE', acknowledge_disconnect: 1 })); }
