import { getJson, postForm } from '../../lib/api';
import type { SubscriberPlan, SubscriberPlanInput } from './contracts';

const PLANS_ENDPOINT = '/api/v1/subscriber-plans';

export function listSubscriberPlans(signal?: AbortSignal): Promise<SubscriberPlan[]> {
  return getJson<SubscriberPlan[]>(PLANS_ENDPOINT, signal);
}

function planForm(input: SubscriberPlanInput): FormData {
  const form = new FormData();
  form.set('plan_name', input.plan_name);
  form.set('price', String(input.price));
  form.set('description', input.description ?? '');
  form.set('plan_type', input.plan_type);
  form.set('validity_days', String(input.plan_type === 'POSTPAID' ? 30 : input.validity_days));
  form.set('speed', String(input.speed_mbps));
  form.set('is_active', String(input.is_active));
  return form;
}

export async function createSubscriberPlan(input: SubscriberPlanInput): Promise<string> {
  const result = await postForm<Record<string, never>>(`${PLANS_ENDPOINT}/store`, planForm(input));
  return result.message;
}

export async function updateSubscriberPlan(id: number, input: SubscriberPlanInput): Promise<string> {
  const result = await postForm<Record<string, never>>(`${PLANS_ENDPOINT}/update/${id}`, planForm(input));
  return result.message;
}

export async function deleteSubscriberPlan(id: number): Promise<string> {
  const form = new FormData();
  form.set('id', String(id));
  const result = await postForm<Record<string, never>>(`${PLANS_ENDPOINT}/delete`, form);
  return result.message;
}
