export interface BngSetting { id?: number; enabled?: number; host?: string; port?: number; username?: string; preferred_interface?: string; bng_parent_interface?: string; host_key_trusted?: boolean; host_key_fingerprint?: string; password?: string; }
export interface BngRuntimeRow { interface?: string; parent_interface?: string; svlan?: number | string; cvlan?: number | string; status?: string; clients?: unknown[]; local_address?: string; peer_address?: string; ppp_username?: string; subscriber_name?: string; service_id?: string | number; [key: string]: unknown; }
export interface BngRuntime { svlan_groups?: BngRuntimeRow[]; client_vlan_interfaces?: BngRuntimeRow[]; bng_interfaces?: BngRuntimeRow[]; }
export interface AccelProfile { id?: number; status?: string; config?: Record<string, unknown>; }
export interface AccelResponse { profile?: AccelProfile; defaults?: Record<string, unknown>; integration_warnings?: string[]; }
export interface HostKeyScan { fingerprint: string; host_key?: string; }
