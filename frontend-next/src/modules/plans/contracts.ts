export type PlanType = 'PREPAID' | 'POSTPAID';

export interface SubscriberPlan {
  id: number;
  plan_name: string;
  price: number;
  description: string | null;
  plan_type: PlanType;
  validity_days: number;
  speed_down: number;
  speed_up: number;
  speed_mbps: number;
  is_active: 0 | 1;
}

export interface SubscriberPlanInput {
  plan_name: string;
  price: number;
  description: string | null;
  plan_type: PlanType;
  validity_days: number;
  speed_mbps: number;
  is_active: 0 | 1;
}
