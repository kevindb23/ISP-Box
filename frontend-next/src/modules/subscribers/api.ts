import { getJson, postForm } from '../../lib/api';
import type { ApiEnvelope } from '../../lib/api';
import type { SubscriberCredentials, SubscriberInput, SubscriberPlanOption, SubscriberRow } from './contracts';

const ENDPOINT = '/api/v1/subscribers';

function inputForm(input: SubscriberInput): FormData {
  const form = new FormData();
  form.set('full_name', input.full_name);
  form.set('address', input.address);
  form.set('contact_number', input.contact_number);
  form.set('email', input.email);
  form.set('plan_id', String(input.plan_id));
  return form;
}

export const listSubscribers = (signal?: AbortSignal): Promise<SubscriberRow[]> => getJson<SubscriberRow[]>(ENDPOINT, signal);
export const listSubscriberPlans = (signal?: AbortSignal): Promise<SubscriberPlanOption[]> => getJson<SubscriberPlanOption[]>(`${ENDPOINT}/plans`, signal);
export const getSubscriber = (id: number, signal?: AbortSignal): Promise<SubscriberRow> => getJson<SubscriberRow>(`${ENDPOINT}/${id}`, signal);

export async function createSubscriber(input: SubscriberInput): Promise<ApiEnvelope<SubscriberCredentials>> {
  return postForm<SubscriberCredentials>(`${ENDPOINT}/create`, inputForm(input));
}

export async function updateSubscriber(id: number, input: SubscriberInput): Promise<ApiEnvelope<Record<string, never>>> {
  return postForm<Record<string, never>>(`${ENDPOINT}/update/${id}`, inputForm(input));
}

async function idAction<T = Record<string, never>>(path: string, id: number): Promise<ApiEnvelope<T>> {
  const form = new FormData();
  form.set('id', String(id));
  return postForm<T>(`${ENDPOINT}/${path}`, form);
}

export const suspendSubscriber = (id: number) => idAction('suspend', id);
export const reactivateSubscriber = (id: number) => idAction('reactivate', id);
export const resetPppPassword = (id: number) => idAction<{ ppp_password?: string }>('reset-password', id);
export const resetPortalPassword = (id: number) => idAction<SubscriberCredentials>('reset-portal-password', id);
export const deleteSubscriber = (id: number) => idAction('delete', id);
