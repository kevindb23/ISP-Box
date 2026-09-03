export interface SubscriberRow {
  id: number;
  account_number: string;
  full_name: string;
  contact_number: string;
  email: string;
  address: string;
  ppp_username: string;
  plan_id: number;
  plan_name: string;
  account_type: string;
  service_status: string;
  service_number: string;
  next_due_date: string;
  expires_at: string;
  nap_name: string;
  nap_splitter_port: string;
  ont_serial: string;
  acs_status?: string | null;
  acs_wan_ip?: string | null;
  wan_ip?: string | null;
  installed_at: string;
  cvlan: string;
  svlan: string;
  online: number;
  online_text: string;
  last_seen: string | null;
  [key: string]: unknown;
}

export interface SubscriberPlanOption {
  id: number;
  plan_name: string;
  plan_type?: string;
  is_active?: number;
}

export interface SubscriberInput {
  full_name: string;
  address: string;
  contact_number: string;
  email: string;
  plan_id: number;
}

export interface SubscriberCredentials {
  account_number?: string;
  service_number?: string;
  ppp_username?: string;
  ppp_password?: string;
  portal_username?: string;
  portal_password?: string;
}
